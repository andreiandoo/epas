<?php

namespace App\Console\Commands\Activities;

use App\Models\Activity;
use App\Models\ActivityAddon;
use App\Models\ActivityBooking;
use App\Models\ActivityCashSession;
use App\Models\ActivityLocation;
use App\Models\ActivityPackageItem;
use App\Models\ActivitySchedule;
use App\Models\ActivityVariant;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\Activities\ActivityCommission;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test data for one bilete.online operator: a filled-in location, its products (access tickets, experiences, a
 * package), customers, online and desk orders with their bookings and tickets, and two closed cash sessions — enough
 * to see every operator screen (Locațiile mele, Produse, Rezervări, Raport, Casă & POS) with real numbers.
 *
 *   php artisan bilete:demo-seed --organizer=622 --location=1
 *   php artisan bilete:demo-seed --remove
 *
 * Everything it writes is listed in the local disk's bilete-demo-seed.json (storage/app/private/) and also carries a
 * `demo_seed` marker, so
 * --remove takes it all back out (and puts the location's own fields back as they were). It refuses to run unless the
 * operator's marketplace has the activities module active, so Ambilet can never be touched.
 */
class DemoOperatorSeedCommand extends Command
{
    protected $signature = 'bilete:demo-seed
        {--organizer=622 : the operator (marketplace organizer id)}
        {--location=1 : the operator\'s location (activity location id)}
        {--orders=40 : how many paid orders to write}
        {--days=45 : how far back the sales go}
        {--remove : delete everything this command created and restore the location}
        {--force : seed again even if a previous run is still in place (it is removed first)}';

    protected $description = 'Seed (or remove) demo data for one bilete.online operator: location, products, orders, tickets';

    private const MANIFEST = 'bilete-demo-seed.json';
    private const MARKER = 'demo_seed';

    public function handle(): int
    {
        return $this->option('remove') ? $this->remove() : $this->seed();
    }

    // =====================================================================
    // Seeding
    // =====================================================================

    private function seed(): int
    {
        if (Storage::disk('local')->exists(self::MANIFEST)) {
            if (!$this->option('force')) {
                $this->error('A previous demo seed is still in place. Run --remove first, or pass --force.');

                return self::FAILURE;
            }
            $this->remove();
        }

        $organizer = MarketplaceOrganizer::with('marketplaceClient')->find((int) $this->option('organizer'));
        if (!$organizer) {
            $this->error('Operator not found.');

            return self::FAILURE;
        }
        $client = $organizer->marketplaceClient;
        if (!$client || !$client->hasMicroservice('activities-module')) {
            $this->error('The operator\'s marketplace does not have the activities module: nothing was written.');

            return self::FAILURE;
        }
        $location = ActivityLocation::find((int) $this->option('location'));
        if (!$location || (int) $location->marketplace_organizer_id !== $organizer->id) {
            $this->error('The location does not exist or belongs to another operator.');

            return self::FAILURE;
        }

        mt_srand(2609);
        $man = [
            'created_at' => now()->toIso8601String(),
            'client_id' => $client->id,
            'organizer_id' => $organizer->id,
            'location_id' => $location->id,
            'location_before' => $location->only($this->locationFields()),
            'activity_ids' => [], 'variant_ids' => [], 'addon_ids' => [], 'schedule_ids' => [], 'package_item_ids' => [],
            'customer_ids' => [], 'order_ids' => [], 'booking_ids' => [], 'ticket_ids' => [], 'session_ids' => [],
        ];

        // Model events stay off while writing: demo orders must not fire Purchase events to Meta / GA4 / TikTok, hand
        // out loyalty points or ring the operator's bell forty times. The bookings and tickets are written paid here.
        Order::withoutEvents(function () use (&$man, $client, $organizer, $location) {
            DB::transaction(function () use (&$man, $client, $organizer, $location) {
                $this->fillLocation($location, $this->category($client->id, $location));
                $products = $this->products($client->id, $organizer->id, $location, $man);
                $customers = $this->customers($client->id, $man);
                $this->orders($client, $organizer, $location, $products, $customers, $man);
            });
        });

        Storage::disk('local')->put(self::MANIFEST, json_encode($man, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $paid = Order::whereIn('id', $man['order_ids'])->sum('total');
        $this->info(sprintf(
            'Seeded: %d products, %d variants, %d customers, %d orders (%s lei), %d bookings, %d tickets, %d cash sessions.',
            count($man['activity_ids']), count($man['variant_ids']), count($man['customer_ids']), count($man['order_ids']),
            number_format((float) $paid, 2, ',', '.'), count($man['booking_ids']), count($man['ticket_ids']), count($man['session_ids'])
        ));
        $this->line('Remove it all with: php artisan bilete:demo-seed --remove');

        return self::SUCCESS;
    }

    /** The location's own fields the seed overwrites (and puts back on --remove). */
    private function locationFields(): array
    {
        return [
            'subtitle', 'short_description', 'description', 'address', 'latitude', 'longitude', 'google_maps_url',
            'marketplace_category_id',
            'phone', 'email', 'website_url', 'facilities', 'rules', 'seasons', 'closed_dates', 'max_advance_days',
            'display_categories', 'lodging', 'faqs', 'seo', 'review_status', 'is_published',
        ];
    }

    /**
     * A category for the location and its products: the one the location already has, else the marketplace's own
     * category that fits a leisure venue, else the first one. Null when the marketplace has no categories.
     */
    private function category(int $clientId, ActivityLocation $location): ?int
    {
        if ($location->marketplace_category_id) {
            return (int) $location->marketplace_category_id;
        }
        $categories = rescue(fn () => MarketplaceCategory::where('marketplace_client_id', $clientId)->whereNull('parent_id')
            ->orderBy('sort_order')->get(['id', 'slug', 'name']), collect(), false);
        foreach (['atractii', 'agrement', 'natura', 'experiente', 'activitati', 'outdoor'] as $wanted) {
            foreach ($categories as $category) {
                $name = strtolower($this->ro($category->name) . ' ' . (string) $category->slug);
                if (str_contains($name, $wanted)) {
                    return (int) $category->id;
                }
            }
        }

        return $categories->first()?->id ? (int) $categories->first()->id : null;
    }

    private function fillLocation(ActivityLocation $location, ?int $categoryId): void
    {
        $hours = array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['open' => '09:00', 'close' => '19:00']);
        $winter = array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], ['open' => '10:00', 'close' => '17:00']);

        $location->forceFill([
            'subtitle' => ['ro' => 'Lac, pădure și plimbări cu barca, la 10 minute de centrul stațiunii'],
            'short_description' => ['ro' => 'Rezervație naturală cu acces pe bază de bilet, plimbări cu barca, tur ghidat prin tinov și locuri de picnic.'],
            'description' => ['ro' => '<p>Rezervația se întinde pe malul lacului și e deschisă tot anul. Vara poți închiria bărci cu vâsle, iarna traseul spre punctul de belvedere rămâne deschis.</p>'
                . '<p>Accesul se face pe bază de bilet, verificat la intrare de pe telefon. Copiii sub 5 ani intră gratuit, însoțiți de un adult.</p>'],
            'address' => 'DN12, Lacul Sfânta Ana, jud. Harghita',
            'phone' => '0266 000 000',
            'email' => 'rezervari@example.com',
            'website_url' => 'https://example.com',
            'facilities' => ['parking', 'wifi', 'restrooms', 'restaurant', 'playground'],
            'rules' => ['ro' => '<p>Accesul cu animale de companie este permis doar în lesă. Focul deschis este interzis în afara zonelor amenajate.</p>'],
            'seasons' => [
                ['name' => 'Sezon estival', 'start' => '04-01', 'end' => '10-31', 'last_entry' => '18:30', 'schedule' => $hours],
                ['name' => 'Sezon rece', 'start' => '11-01', 'end' => '03-31', 'last_entry' => '16:30', 'schedule' => $winter],
            ],
            'closed_dates' => [CarbonImmutable::now()->addDays(17)->toDateString()],
            'max_advance_days' => 90,
            'display_categories' => [
                ['id' => 'acces', 'name' => 'Bilete de acces'],
                ['id' => 'pe-apa', 'name' => 'Pe apă'],
                ['id' => 'ghidate', 'name' => 'Tururi ghidate'],
                ['id' => 'pachete', 'name' => 'Pachete'],
            ],
            'lodging' => [
                'type' => 'camping',
                'description' => ['ro' => '<p>În apropiere funcționează un camping cu 20 de locuri de cort și 6 căsuțe. Rezervarea se face direct la unitate.</p>'],
                'links' => [
                    ['label' => 'Camping — rezervări', 'url' => 'https://example.com/camping'],
                ],
            ],
            'faqs' => [
                ['q' => 'Biletul e valabil toată ziua?', 'a' => 'Da, biletul de acces e valabil în ziua aleasă, în intervalul de program al rezervației.'],
                ['q' => 'Pot veni cu mașina până la lac?', 'a' => 'Accesul auto e permis doar până în parcarea de la intrare; de acolo se merge pe jos 10 minute.'],
                ['q' => 'Ce fac dacă plouă?', 'a' => 'Biletele rămân valabile; plimbările cu barca se suspendă doar pe vreme severă, iar atunci le reprogramăm.'],
            ],
            'seo' => ['demo_seed' => true],
            'marketplace_category_id' => $categoryId ?: $location->marketplace_category_id,
            'review_status' => 'approved',
            'is_published' => true,
        ])->save();
    }

    /**
     * The catalogue: two access tickets, two experiences and a package that puts them together.
     *
     * @return array<string, array{activity: Activity, variants: array<string, ActivityVariant>}>
     */
    private function products(int $clientId, int $organizerId, ActivityLocation $location, array &$man): array
    {
        $base = [
            'marketplace_client_id' => $clientId,
            'marketplace_organizer_id' => $organizerId,
            'location_id' => $location->id,
            'marketplace_city_id' => $location->marketplace_city_id,
            'marketplace_category_id' => $location->marketplace_category_id,
            'is_published' => true,
            'review_status' => 'approved',
            'reviewed_at' => now(),
            'booking_lead_time_hours' => 0,
            'booking_max_advance_days' => 90,
            'seo' => ['demo_seed' => true],
        ];
        $out = [];

        $keep = function (string $key, Activity $activity, array $variants) use (&$out, &$man) {
            $man['activity_ids'][] = $activity->id;
            foreach ($variants as $v) {
                $man['variant_ids'][] = $v->id;
            }
            $out[$key] = ['activity' => $activity, 'variants' => $variants];
        };

        // ---- access ------------------------------------------------------
        $access = Activity::forceCreate($base + [
            'title' => ['ro' => 'Acces rezervație'],
            'slug' => $this->slug('acces-rezervatie'),
            'subtitle' => ['ro' => 'Biletul de intrare, valabil toată ziua'],
            'short_description' => ['ro' => 'Intrarea în rezervație pentru o zi, cu acces la traseele marcate și la zona de picnic.'],
            'product_type' => 'access', 'access_kind' => 'person', 'booking_mode' => 'day',
            'daily_capacity' => 400, 'use_location_schedule' => true, 'display_category' => 'acces',
            'usage_terms' => ['ro' => 'Biletul se arată la intrare, de pe telefon.'],
        ]);
        $accessV = [
            'adult' => ActivityVariant::forceCreate(['activity_id' => $access->id, 'name' => ['ro' => 'Adult'], 'price_cents' => 4100, 'pos_price_cents' => 3900, 'min_per_order' => 0, 'max_per_order' => 20, 'sort_order' => 1]),
            'pupil' => ActivityVariant::forceCreate(['activity_id' => $access->id, 'name' => ['ro' => 'Elev / student'], 'price_cents' => 3100, 'pos_price_cents' => 2900, 'min_per_order' => 0, 'max_per_order' => 20, 'sort_order' => 2]),
            'child' => ActivityVariant::forceCreate(['activity_id' => $access->id, 'name' => ['ro' => 'Copil 5–14 ani'], 'price_cents' => 2900, 'pos_price_cents' => 2700, 'is_child' => true, 'min_per_order' => 0, 'max_per_order' => 20, 'sort_order' => 3]),
            'group' => ActivityVariant::forceCreate(['activity_id' => $access->id, 'name' => ['ro' => 'Grup (min. 8)'], 'price_cents' => 3700, 'min_per_order' => 8, 'max_per_order' => 40, 'companion_label' => 'Ghid însoțitor', 'sort_order' => 4]),
        ];
        $keep('access', $access, $accessV);

        $parking = Activity::forceCreate($base + [
            'title' => ['ro' => 'Parcare'],
            'slug' => $this->slug('parcare'),
            'short_description' => ['ro' => 'Loc de parcare în zona de la intrare, pentru o zi.'],
            'product_type' => 'access', 'access_kind' => 'vehicle', 'booking_mode' => 'day',
            'daily_capacity' => 60, 'use_location_schedule' => true, 'requires_vehicle_info' => true, 'display_category' => 'acces',
        ]);
        $parkingV = [
            'car' => ActivityVariant::forceCreate(['activity_id' => $parking->id, 'name' => ['ro' => 'Autoturism'], 'price_cents' => 2500, 'price_type' => 'per_unit', 'min_per_order' => 0, 'max_per_order' => 2]),
        ];
        $keep('parking', $parking, $parkingV);

        // ---- experiences --------------------------------------------------
        $boat = Activity::forceCreate($base + [
            'title' => ['ro' => 'Închiriere barcă cu vâsle'],
            'slug' => $this->slug('inchiriere-barca'),
            'short_description' => ['ro' => 'O barcă pentru maximum 4 persoane, pe intervale de 30 sau 60 de minute.'],
            'product_type' => 'experience', 'service_type' => 'rental', 'booking_mode' => 'slot',
            'capacity_mode' => 'concurrent', 'capacity_per_slot' => 8, 'slot_interval_minutes' => 30,
            'duration_minutes' => 30, 'access_requirement' => 'adult', 'display_category' => 'pe-apa',
        ]);
        $boatV = [
            'm30' => ActivityVariant::forceCreate(['activity_id' => $boat->id, 'name' => ['ro' => '30 de minute'], 'price_cents' => 4000, 'price_type' => 'per_unit', 'persons_max' => 4, 'duration_minutes' => 30, 'min_per_order' => 0, 'sort_order' => 1]),
            'm60' => ActivityVariant::forceCreate(['activity_id' => $boat->id, 'name' => ['ro' => 'O oră'], 'price_cents' => 5000, 'price_type' => 'per_unit', 'persons_max' => 4, 'duration_minutes' => 60, 'min_per_order' => 0, 'sort_order' => 2]),
        ];
        $man['addon_ids'][] = ActivityAddon::forceCreate(['activity_id' => $boat->id, 'name' => ['ro' => 'Vestă de salvare'], 'price_cents' => 0, 'included_qty' => 4, 'max_per_unit' => 4])->id;
        $man['addon_ids'][] = ActivityAddon::forceCreate(['activity_id' => $boat->id, 'name' => ['ro' => 'Pachet foto'], 'price_cents' => 1500, 'included_qty' => 0, 'max_per_unit' => 2])->id;
        foreach (range(1, 7) as $dow) {
            $man['schedule_ids'][] = ActivitySchedule::forceCreate(['activity_id' => $boat->id, 'day_of_week' => $dow, 'open_time' => '09:30', 'close_time' => '18:30', 'season_start' => '04-01', 'season_end' => '10-31'])->id;
        }
        $keep('boat', $boat, $boatV);

        $tour = Activity::forceCreate($base + [
            'title' => ['ro' => 'Tur ghidat prin tinov'],
            'slug' => $this->slug('tur-ghidat-tinov'),
            'short_description' => ['ro' => 'Două ore prin tinovul din apropiere, cu un ghid al rezervației.'],
            'product_type' => 'experience', 'service_type' => 'guided', 'booking_mode' => 'slot',
            'capacity_mode' => 'per_slot', 'capacity_per_slot' => 15, 'slot_interval_minutes' => 120,
            'duration_minutes' => 120, 'age_min' => 7, 'display_category' => 'ghidate',
        ]);
        $tourV = [
            'person' => ActivityVariant::forceCreate(['activity_id' => $tour->id, 'name' => ['ro' => 'O persoană'], 'price_cents' => 3500, 'min_per_order' => 0, 'max_per_order' => 15]),
        ];
        foreach (range(1, 7) as $dow) {
            $man['schedule_ids'][] = ActivitySchedule::forceCreate(['activity_id' => $tour->id, 'day_of_week' => $dow, 'open_time' => '10:00', 'close_time' => '16:00'])->id;
        }
        $keep('tour', $tour, $tourV);

        // ---- package -------------------------------------------------------
        $package = Activity::forceCreate($base + [
            'title' => ['ro' => 'Pachet familie: 2 adulți + 2 copii + barcă'],
            'slug' => $this->slug('pachet-familie'),
            'short_description' => ['ro' => 'Intrare pentru doi adulți și doi copii, plus 30 de minute cu barca.'],
            'product_type' => 'package', 'booking_mode' => 'day', 'display_category' => 'pachete',
        ]);
        $packageV = [
            'family' => ActivityVariant::forceCreate(['activity_id' => $package->id, 'name' => ['ro' => 'Pachet'], 'price_cents' => 16500, 'min_per_order' => 0, 'max_per_order' => 5]),
        ];
        foreach ([
            ['activity' => $access->id, 'variant' => $accessV['adult']->id, 'qty' => 2],
            ['activity' => $access->id, 'variant' => $accessV['child']->id, 'qty' => 2],
            ['activity' => $boat->id, 'variant' => $boatV['m30']->id, 'qty' => 1],
        ] as $item) {
            $man['package_item_ids'][] = ActivityPackageItem::forceCreate([
                'package_activity_id' => $package->id,
                'component_activity_id' => $item['activity'],
                'component_variant_id' => $item['variant'],
                'quantity' => $item['qty'],
            ])->id;
        }
        $keep('package', $package, $packageV);

        return $out;
    }

    /** A slug nothing else uses yet. */
    private function slug(string $base): string
    {
        $slug = $base;
        $n = 2;
        while (Activity::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /** @return array<int, MarketplaceCustomer> */
    private function customers(int $clientId, array &$man): array
    {
        $people = [
            ['Ana', 'Popescu'], ['Mihai', 'Ionescu'], ['Elena', 'Radu'], ['Andrei', 'Marin'],
            ['Ioana', 'Stan'], ['Vlad', 'Dumitru'], ['Raluca', 'Toma'], ['George', 'Barbu'],
        ];
        $out = [];
        foreach ($people as $i => [$first, $last]) {
            $email = sprintf('demo.%s.%d@example.com', Str::slug($first . '-' . $last), $i + 1);
            $existing = MarketplaceCustomer::where('marketplace_client_id', $clientId)->where('email', $email)->first();
            if (!$existing) {
                $existing = MarketplaceCustomer::create([
                    'marketplace_client_id' => $clientId,
                    'email' => $email,
                    'first_name' => $first,
                    'last_name' => $last,
                    'phone' => '07' . str_pad((string) (40000000 + $i * 111111), 8, '0', STR_PAD_LEFT),
                    'status' => 'active',
                ]);
                $man['customer_ids'][] = $existing->id;
            }
            $out[] = $existing;
        }

        return $out;
    }

    // =====================================================================
    // Orders
    // =====================================================================

    private function orders($client, MarketplaceOrganizer $organizer, ActivityLocation $location, array $products, array $customers, array &$man): void
    {
        $count = max(1, (int) $this->option('orders'));
        $days = max(1, (int) $this->option('days'));
        $floor = ActivityCommission::floor($organizer);
        $rate = (float) $organizer->getEffectiveCommissionRate();
        $mode = $organizer->getEffectiveCommissionMode();

        // two closed desk days, so the cash register has history too
        $sessions = [];
        foreach ([3, 1] as $ago) {
            $opened = CarbonImmutable::now()->subDays($ago)->setTime(9, 30);
            $session = ActivityCashSession::create([
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $organizer->id,
                'location_id' => $location->id,
                'opened_by' => $organizer->name,
                'opened_at' => $opened,
                'opening_cash_cents' => 20000,
                'closed_by' => $organizer->name,
                'closed_at' => $opened->setTime(19, 0),
                'counted_cash_cents' => 20000,
                'notes' => self::MARKER,
            ]);
            $man['session_ids'][] = $session->id;
            $sessions[$opened->toDateString()] = $session;
        }

        for ($i = 0; $i < $count; $i++) {
            $boughtAt = CarbonImmutable::now()->subDays(mt_rand(0, $days))->setTime(mt_rand(8, 20), mt_rand(0, 59));
            $desk = $i % 4 === 0;
            $session = $desk ? ($sessions[$boughtAt->toDateString()] ?? null) : null;
            // at the desk people buy for the same day; online they book ahead
            $visit = $desk ? $boughtAt->toDateString() : $boughtAt->addDays(mt_rand(0, 20))->toDateString();
            $customer = $customers[array_rand($customers)];
            $lines = $this->lines($products, $visit, $desk, $rate, $floor);

            $subtotal = round(array_sum(array_column($lines, 'total')), 2);
            $commission = round(array_sum(array_column($lines, 'commission')), 2);
            $onTop = $mode === 'added_on_top' ? $commission : 0.0;
            $total = round($subtotal + $onTop, 2);

            $order = Order::create([
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $organizer->id,
                'marketplace_customer_id' => $customer->id,
                'tenant_id' => null,
                'event_id' => null,
                'marketplace_event_id' => null,
                'order_number' => ($desk ? 'POS-' : 'ACT-') . strtoupper(Str::random(8)),
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_processor' => $desk ? 'pos' : 'stripe',
                'payment_reference' => $desk ? 'pos-cash' : 'demo-' . Str::random(10),
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'commission_rate' => 0,
                'commission_amount' => $commission,
                'total' => $total,
                'currency' => $client->currency ?? 'RON',
                'source' => $desk ? 'pos' : 'web',
                'customer_email' => $customer->email,
                'customer_name' => trim($customer->first_name . ' ' . $customer->last_name),
                'customer_phone' => $customer->phone,
                'paid_at' => $boughtAt,
                'meta' => array_filter([
                    'order_type' => 'activity',
                    self::MARKER => true,
                    'pos' => $desk ?: null,
                    'payment_method' => $desk ? 'cash' : 'card',
                    'cash_session_id' => $session?->id,
                    'location_id' => $location->id,
                    'commission_added_on_top' => $onTop,
                ], fn ($v) => $v !== null),
            ]);
            $order->forceFill(['created_at' => $boughtAt, 'updated_at' => $boughtAt])->save();
            $man['order_ids'][] = $order->id;

            foreach ($lines as $line) {
                $this->writeLine($order, $client->id, $organizer->id, $location->id, $customer, $line, $boughtAt, $visit, $mode, $rate, $man);
            }
        }
    }

    /** One to three lines for an order, priced as the desk or as the site. */
    private function lines(array $products, string $visit, bool $desk, float $rate, float $floor): array
    {
        $pick = [];
        $adults = mt_rand(1, 4);
        $pick[] = ['product' => $products['access'], 'variant' => 'adult', 'qty' => $adults];
        if (mt_rand(0, 1)) {
            $pick[] = ['product' => $products['access'], 'variant' => mt_rand(0, 1) ? 'child' : 'pupil', 'qty' => mt_rand(1, 3)];
        }
        if (mt_rand(0, 3) === 0) {
            $pick[] = ['product' => $products['parking'], 'variant' => 'car', 'qty' => 1];
        }
        if (mt_rand(0, 2) === 0) {
            $pick[] = ['product' => $products['boat'], 'variant' => mt_rand(0, 1) ? 'm30' : 'm60', 'qty' => 1];
        }
        if (mt_rand(0, 4) === 0) {
            $pick[] = ['product' => $products['tour'], 'variant' => 'person', 'qty' => mt_rand(2, 4)];
        }
        if (mt_rand(0, 6) === 0) {
            $pick = [['product' => $products['package'], 'variant' => 'family', 'qty' => 1]];
        }

        $lines = [];
        foreach ($pick as $p) {
            $activity = $p['product']['activity'];
            $variant = $p['product']['variants'][$p['variant']];
            $unitCents = $desk && $variant->pos_price_cents ? (int) $variant->pos_price_cents : (int) $variant->price_cents;
            $qty = (int) $p['qty'];
            $total = round($unitCents * $qty / 100, 2);
            $slot = $activity->booking_mode === 'slot' ? sprintf('%02d:%02d:00', mt_rand(10, 16), mt_rand(0, 1) ? 0 : 30) : null;
            $lines[] = [
                'activity' => $activity,
                'variant' => $variant,
                'quantity' => $qty,
                'unit_cents' => $unitCents,
                'total' => $total,
                'slot' => $slot,
                'end' => $slot ? CarbonImmutable::parse($visit . ' ' . $slot)->addMinutes((int) ($variant->duration_minutes ?: $activity->duration_minutes ?: 60))->format('H:i:s') : null,
                'commission' => ActivityCommission::forLine($total, $rate, $floor, $qty),
            ];
        }

        return $lines;
    }

    /** The order item, the booking and its tickets — the same shape the checkout writes. */
    private function writeLine(Order $order, int $clientId, int $organizerId, int $locationId, MarketplaceCustomer $customer, array $line, CarbonImmutable $boughtAt, string $visit, string $mode, float $rate, array &$man): void
    {
        $activity = $line['activity'];
        $variant = $line['variant'];
        $title = $this->ro($activity->title, 'Produs');
        $variantName = $this->ro($variant->name, 'Bilet');

        $item = $order->items()->create([
            'ticket_type_id' => null,
            'performance_id' => null,
            'name' => $title . ' — ' . $variantName,
            'quantity' => $line['quantity'],
            'unit_price' => round($line['unit_cents'] / 100, 2),
            'total' => $line['total'],
            'meta' => [
                'order_type' => 'activity',
                self::MARKER => true,
                'product_type' => $activity->product_type,
                'activity_id' => $activity->id,
                'variant_id' => $variant->id,
                'location_id' => $locationId,
                'organizer_id' => $organizerId,
                'booking_date' => $visit,
                'slot_start_time' => $line['slot'],
                'slot_end_time' => $line['end'],
                'participants_count' => $line['quantity'],
            ],
        ]);

        $booking = ActivityBooking::create([
            'marketplace_client_id' => $clientId,
            'activity_id' => $activity->id,
            'variant_id' => $variant->id,
            'location_id' => $locationId,
            'marketplace_organizer_id' => $organizerId,
            'marketplace_customer_id' => $customer->id,
            'order_id' => $order->id,
            'booking_date' => $visit,
            'end_date' => $visit,
            'slot_start_time' => $line['slot'],
            'slot_end_time' => $line['end'],
            'quantity' => $line['quantity'],
            'participants_count' => $activity->product_type === 'package' ? 0 : $line['quantity'] * max(1, (int) ($variant->capacity_share ?: 1)),
            'unit_price_cents' => $line['unit_cents'],
            'total_cents' => (int) round($line['total'] * 100),
            'commission_cents' => (int) round($line['commission'] * 100),
            'currency' => $order->currency,
            // model events are off while seeding, so the code the booking's own hook would make is set here
            'confirmation_code' => $this->confirmationCode($clientId),
            'status' => ActivityBooking::STATUS_PAID,
            'held_until' => null,
            'meta' => [self::MARKER => true, 'commission_mode' => $mode, 'commission_rate' => $rate],
        ]);
        $booking->forceFill(['created_at' => $boughtAt, 'updated_at' => $boughtAt])->save();
        $man['booking_ids'][] = $booking->id;

        $past = $visit < CarbonImmutable::now()->toDateString();
        for ($n = 0; $n < $line['quantity']; $n++) {
            $ticket = Ticket::create([
                'marketplace_client_id' => $clientId,
                'tenant_id' => null,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'event_id' => null,
                'marketplace_event_id' => null,
                'ticket_type_id' => null,
                'marketplace_ticket_type_id' => null,
                'performance_id' => null,
                'marketplace_customer_id' => $customer->id,
                'activity_booking_id' => $booking->id,
                'code' => strtoupper(Str::random(8)),
                'barcode' => Str::uuid()->toString(),
                'locale' => 'ro',
                'status' => $past ? 'used' : 'valid',
                'price' => round($line['unit_cents'] / 100, 2),
                'attendee_name' => trim($customer->first_name . ' ' . $customer->last_name),
                'attendee_email' => $customer->email,
                'checked_in_at' => $past ? CarbonImmutable::parse($visit)->setTime(11, mt_rand(0, 59)) : null,
                'meta' => [
                    self::MARKER => true,
                    'activity_id' => $activity->id,
                    'variant_id' => $variant->id,
                    'product_type' => $activity->product_type,
                    'location_id' => $locationId,
                    'booking_date' => $visit,
                    'slot_start_time' => $line['slot'],
                    'slot_end_time' => $line['end'],
                ],
            ]);
            $man['ticket_ids'][] = $ticket->id;
        }
    }

    // =====================================================================
    // Removal
    // =====================================================================

    private function remove(): int
    {
        if (!Storage::disk('local')->exists(self::MANIFEST)) {
            $this->warn('No manifest found (storage/app/' . self::MANIFEST . '): nothing to remove.');

            return self::SUCCESS;
        }
        $man = json_decode((string) Storage::disk('local')->get(self::MANIFEST), true) ?: [];

        Order::withoutEvents(fn () => DB::transaction(function () use ($man) {
            Ticket::whereIn('id', $man['ticket_ids'] ?? [])->delete();
            ActivityBooking::withTrashed()->whereIn('id', $man['booking_ids'] ?? [])->forceDelete();
            // bookings of a package's components carry the parent's order too
            ActivityBooking::withTrashed()->whereIn('order_id', $man['order_ids'] ?? [])->forceDelete();
            Ticket::whereIn('order_id', $man['order_ids'] ?? [])->delete();
            DB::table('order_items')->whereIn('order_id', $man['order_ids'] ?? [])->delete();
            Order::whereIn('id', $man['order_ids'] ?? [])->delete();
            ActivityCashSession::whereIn('id', $man['session_ids'] ?? [])->delete();

            ActivityPackageItem::whereIn('id', $man['package_item_ids'] ?? [])->delete();
            ActivityAddon::whereIn('id', $man['addon_ids'] ?? [])->delete();
            ActivitySchedule::whereIn('id', $man['schedule_ids'] ?? [])->delete();
            ActivityVariant::withTrashed()->whereIn('id', $man['variant_ids'] ?? [])->forceDelete();
            ActivityPackageItem::whereIn('package_activity_id', $man['activity_ids'] ?? [])
                ->orWhereIn('component_activity_id', $man['activity_ids'] ?? [])->delete();
            Activity::withTrashed()->whereIn('id', $man['activity_ids'] ?? [])->forceDelete();
            MarketplaceCustomer::withTrashed()->whereIn('id', $man['customer_ids'] ?? [])->forceDelete();

            if (!empty($man['location_id']) && isset($man['location_before'])) {
                $location = ActivityLocation::find($man['location_id']);
                if ($location) {
                    $location->forceFill($man['location_before'])->save();
                }
            }
        }));

        Storage::disk('local')->delete(self::MANIFEST);
        $this->info(sprintf(
            'Removed: %d orders, %d bookings, %d tickets, %d products, %d customers, %d cash sessions. The location is back as it was.',
            count($man['order_ids'] ?? []), count($man['booking_ids'] ?? []), count($man['ticket_ids'] ?? []),
            count($man['activity_ids'] ?? []), count($man['customer_ids'] ?? []), count($man['session_ids'] ?? [])
        ));

        return self::SUCCESS;
    }

    /** Same shape as the booking's own hook: ten characters, free within the marketplace. */
    private function confirmationCode(int $clientId): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (ActivityBooking::withTrashed()->where('marketplace_client_id', $clientId)->where('confirmation_code', $code)->exists());

        return $code;
    }

    private function ro($value, string $fallback = ''): string
    {
        if (is_array($value)) {
            $value = $value['ro'] ?? $value['en'] ?? (array_values(array_filter($value))[0] ?? null);
        }
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }
}
