<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceOrganizer;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\Venue;
use App\Services\EventStatsCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo account for the Google Play review of the AmBilet scan app.
 *
 * Everything here stays out of the money: events are unpublished (not on the
 * site), every ticket type is a TEST type and every order is pos_test, so
 * decont, invoices and reports skip them (App\Support\TestPos). Rows are
 * written quietly, so no admin notification, email or tracking job fires.
 * Safe to run again: it updates the same rows.
 */
class PlayReviewDemoCommand extends Command
{
    protected $signature = 'ambilet:play-review-demo
        {--client=1 : Marketplace client id (Ambilet)}
        {--email=playreview@ambilet.ro : Login email for the reviewer}
        {--password= : Login password (random if empty)}';

    protected $description = 'Creates or refreshes the Google Play review demo organizer, events and test tickets';

    // Fixed codes so the review instructions never change.
    public const MANUAL_CODES = ['GPDEMO000001', 'GPDEMO000002', 'GPDEMO000003', 'GPDEMO000004', 'GPDEMO000005'];

    public function handle(): int
    {
        $client = MarketplaceClient::find((int) $this->option('client'));
        if (!$client) {
            $this->error('Marketplace client not found.');
            return self::FAILURE;
        }

        $email = strtolower(trim($this->option('email')));
        $password = $this->option('password') ?: null;

        $tenantId = $client->tenants()->first()?->id ?? Tenant::query()->value('id');

        DB::transaction(function () use ($client, $email, &$password, $tenantId) {
            $organizer = $this->organizer($client, $email, $password);
            $venue = $this->venue($client);

            $today = now()->startOfDay();
            $events = [
                // Range that always covers "today", so the app opens it as live.
                'live' => $this->event($client, $organizer, $venue, 'google-play-demo-festival', [
                    'ro' => 'Festival Demo AmBilet',
                    'en' => 'AmBilet Demo Festival',
                ], [
                    'duration_mode' => 'range',
                    'range_start_date' => $today->copy()->subDays(3)->toDateString(),
                    'range_end_date' => $today->copy()->addDays(120)->toDateString(),
                    'range_start_time' => '18:00',
                    'range_end_time' => '23:30',
                ]),
                'upcoming' => $this->event($client, $organizer, $venue, 'google-play-demo-stand-up', [
                    'ro' => 'Stand-up Comedy Demo',
                    'en' => 'Stand-up Comedy Demo',
                ], [
                    'duration_mode' => 'single_day',
                    'event_date' => $today->copy()->addDays(21)->toDateString(),
                    'door_time' => '19:00',
                    'start_time' => '20:00',
                    'end_time' => '22:00',
                ]),
            ];

            $types = [
                'live' => [
                    ['Acces General', 60, 500],
                    ['VIP', 150, 80],
                    ['Student', 35, 200],
                ],
                'upcoming' => [
                    ['Bilet Standard', 70, 300],
                    ['Primul rând', 120, 20],
                ],
            ];

            $codeIndex = 0;
            $orderNo = 0;
            foreach ($events as $key => $event) {
                $ticketTypes = [];
                foreach ($types[$key] as $sort => [$name, $price, $quota]) {
                    $ticketTypes[] = $this->ticketType($event, $name, $price, $quota, $sort);
                }

                // Sample door sales; a few are already checked in. The first
                // tickets of the live event get the fixed manual-entry codes,
                // left unscanned so the reviewer can validate them.
                $plan = $key === 'live'
                    ? [[0, 2, 'cash'], [1, 1, 'card'], [0, 3, 'card'], [2, 2, 'cash'], [0, 1, 'cash'], [1, 2, 'card'], [2, 1, 'cash'], [0, 4, 'cash']]
                    : [[0, 2, 'card'], [1, 1, 'cash'], [0, 1, 'cash']];

                foreach ($plan as $i => [$typeIdx, $qty, $method]) {
                    $orderNo++;
                    $this->order($client, $event, $ticketTypes[$typeIdx], $qty, $method, $tenantId, $orderNo, $i, $key === 'live', $codeIndex);
                }

                EventStatsCache::forget($event->id);
            }

            // A reviewer may have scanned them already; run again before each
            // submission so the next one finds them valid.
            DB::table('tickets')
                ->whereIn('code', self::MANUAL_CODES)
                ->where('event_id', $events['live']->id)
                ->update(['checked_in_at' => null, 'checked_in_by' => null, 'checked_in_via' => null, 'status' => 'valid']);
        });

        $this->info('Demo ready.');
        $this->line('Email:    ' . $email);
        $this->line('Password: ' . ($password ?? '(unchanged)'));
        $this->line('Manual entry codes (live event): ' . implode(', ', self::MANUAL_CODES));

        return self::SUCCESS;
    }

    private function organizer(MarketplaceClient $client, string $email, ?string &$password): MarketplaceOrganizer
    {
        $organizer = MarketplaceOrganizer::withTrashed()
            ->where('marketplace_client_id', $client->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first() ?? new MarketplaceOrganizer();

        // pending = not listed on the site; login only rejects 'suspended'.
        // verified_at stays null so auto-deconts never pick it up.
        // The password only changes on first run or when passed, so the one
        // given to Google keeps working.
        if (!$organizer->exists && $password === null) {
            $password = Str::password(14, symbols: false);
        }
        if ($password !== null) {
            $organizer->password = $password;
        }

        $organizer->forceFill([
            'marketplace_client_id' => $client->id,
            'email' => $email,
            'name' => 'Demo Google Play',
            'slug' => $organizer->slug ?: 'demo-google-play-review',
            'contact_name' => 'Google Play Review',
            'status' => 'pending',
            'verified_at' => null,
            'commission_rate' => 0,
            'test_pos_enabled' => true,
            'deleted_at' => null,
        ]);
        $organizer->saveQuietly();

        return $organizer;
    }

    private function venue(MarketplaceClient $client): Venue
    {
        // Not linked in marketplace_venue_partners, so it never shows publicly.
        $venue = Venue::where('slug', 'sala-demo-google-play')->first() ?? new Venue();
        $venue->forceFill([
            'slug' => 'sala-demo-google-play',
            'tenant_id' => null,
            'marketplace_client_id' => $client->id,
            'name' => ['ro' => 'Sala Demo AmBilet', 'en' => 'AmBilet Demo Hall'],
            'address' => 'Bd. Unirii 1',
            'city' => 'București',
            'state' => 'București',
            'country' => 'RO',
            'capacity' => 800,
            'capacity_total' => 800,
        ]);
        $venue->saveQuietly();

        return $venue;
    }

    private function event(MarketplaceClient $client, MarketplaceOrganizer $organizer, Venue $venue, string $slug, array $title, array $dates): Event
    {
        $event = Event::where('marketplace_client_id', $client->id)->where('slug', $slug)->first() ?? new Event();

        $blank = [
            'event_date' => null, 'start_time' => null, 'door_time' => null, 'end_time' => null,
            'range_start_date' => null, 'range_end_date' => null, 'range_start_time' => null, 'range_end_time' => null,
        ];

        // is_published=false hides it from ambilet.ro, search and sitemap; the
        // organizer app still lists it and POS sales work.
        $event->forceFill(array_merge($blank, $dates, [
            'marketplace_client_id' => $client->id,
            'marketplace_organizer_id' => $organizer->id,
            'venue_id' => $venue->id,
            'slug' => $slug,
            'title' => $title,
            'short_description' => [
                'ro' => 'Eveniment demonstrativ pentru verificarea aplicației AmBilet.',
                'en' => 'Demo event used to review the AmBilet app.',
            ],
            'is_published' => false,
        ]));
        $event->saveQuietly();

        if (!$event->event_series) {
            $event->forceFill(['event_series' => 'AMB-' . $event->id])->saveQuietly();
        }

        return $event;
    }

    private function ticketType(Event $event, string $name, int $price, int $quota, int $sort): TicketType
    {
        $type = TicketType::where('event_id', $event->id)->where('name', $name)->first() ?? new TicketType();
        $type->forceFill([
            'event_id' => $event->id,
            'name' => $name,
            'price_cents' => $price * 100,
            'sale_price_cents' => null,
            'currency' => 'RON',
            'quota_total' => $quota,
            'quota_sold' => $type->quota_sold ?? 0,
            'status' => 'active',
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_entry_ticket' => true,
            'is_declarable' => false,
            'sort_order' => $sort,
            'meta' => array_merge((array) ($type->meta ?? []), ['is_test' => true, 'hide_from_public' => true, 'play_review_demo' => true]),
        ]);
        $type->save();

        return $type;
    }

    private function order(MarketplaceClient $client, Event $event, TicketType $type, int $qty, string $method, ?int $tenantId, int $orderNo, int $i, bool $isLive, int &$codeIndex): void
    {
        $orderNumber = sprintf('DEMO-GP-%04d', $orderNo);
        if (DB::table('orders')->where('order_number', $orderNumber)->exists()) {
            return;
        }

        $unit = $type->price_cents / 100;
        $soldAt = now()->subHours(($i + 1) * 3);
        $name = ['Andrei Pop', 'Maria Ionescu', 'Ioana Stan', 'Mihai Dobre', 'Elena Radu', 'Vlad Matei', 'Ana Costin', 'Radu Enache'][$i % 8];

        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $tenantId,
            'event_id' => $event->id,
            'customer_id' => null,
            'order_number' => $orderNumber,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'paid_at' => $soldAt,
            'subtotal' => $unit * $qty,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'total' => $unit * $qty,
            'currency' => 'RON',
            'locale' => 'ro',
            'source' => 'pos_test',
            'marketplace_client_id' => $client->id,
            'marketplace_organizer_id' => $event->marketplace_organizer_id,
            'customer_email' => 'participant' . $orderNo . '@example.com',
            'customer_name' => $name,
            'meta' => json_encode(['sold_by' => 'Demo Google Play', 'payment_method' => $method, 'play_review_demo' => true]),
            'created_at' => $soldAt,
            'updated_at' => $soldAt,
        ]);

        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'ticket_type_id' => $type->id,
            'name' => $type->name,
            'quantity' => $qty,
            'unit_price' => $unit,
            'total' => $unit * $qty,
            'created_at' => $soldAt,
            'updated_at' => $soldAt,
        ]);

        for ($n = 0; $n < $qty; $n++) {
            $fixed = $isLive && $codeIndex < count(self::MANUAL_CODES);
            $code = $fixed ? self::MANUAL_CODES[$codeIndex++] : strtoupper(Str::random(12));
            // Fixed-code tickets stay unscanned; about half of the others are in.
            $checkedIn = !$fixed && ($i + $n) % 2 === 0;

            DB::table('tickets')->insert([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'order_item_id' => $itemId,
                'event_id' => $event->id,
                'ticket_type_id' => $type->id,
                'code' => $code,
                'barcode' => (string) Str::uuid(),
                'status' => 'valid',
                'price' => $unit,
                'attendee_name' => $name,
                'checked_in_at' => $checkedIn ? $soldAt->copy()->addMinutes(20) : null,
                'checked_in_by' => $checkedIn ? 'Demo Google Play' : null,
                'checked_in_via' => $checkedIn ? 'pos_app' : null,
                'created_at' => $soldAt,
                'updated_at' => $soldAt,
            ]);
        }

        DB::table('ticket_types')->where('id', $type->id)->increment('quota_sold', $qty);
    }
}
