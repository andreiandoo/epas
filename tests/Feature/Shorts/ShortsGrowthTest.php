<?php

namespace Tests\Feature\Shorts;

use App\Http\Controllers\Api\MarketplaceClient\Customer\ShortInteractionsController;
use App\Jobs\Shorts\FireDropRemindersJob;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Models\ShortReminder;
use App\Models\ShortShare;
use App\Models\TicketType;
use App\Services\Push\PushSender;
use App\Services\Shorts\ShortGamificationService;
use App\Services\Shorts\ShortPayload;
use App\Services\Shorts\ShortReminderService;
use App\Services\Shorts\ShortShareService;
use Illuminate\Http\Request;

/**
 * Wave 1 — share + referral (D1), drop reminders (D2), gamification (D11).
 */
class ShortsGrowthTest extends ShortsTestCase
{
    /* ---------------- D1 — share ---------------- */

    public function test_sharing_mints_a_token_and_builds_a_landing_url(): void
    {
        $short = $this->makeShort();
        $customer = $this->makeCustomer();

        $result = app(ShortShareService::class)->share($short, $customer, 'whatsapp');

        $this->assertNotEmpty($result['token']);
        $this->assertStringContainsString("/s/{$short->id}", $result['url']);
        $this->assertStringContainsString("s={$result['token']}", $result['url']);
        $this->assertSame("tixello://shorts/{$short->id}", $result['deep_link']);

        $share = ShortShare::query()->firstOrFail();
        $this->assertSame('whatsapp', $share->channel);
        $this->assertSame($customer->id, $share->sharer_customer_id);
    }

    public function test_landing_click_is_attributed_to_the_share_that_produced_it(): void
    {
        $short = $this->makeShort();
        $service = app(ShortShareService::class);

        $result = $service->share($short, $this->makeCustomer(), 'copy');

        $service->recordClick($result['token']);
        $service->recordClick($result['token']);
        $service->recordClick('not-a-real-token');

        $this->assertSame(2, ShortShare::query()->firstOrFail()->fresh()->clicks);
    }

    public function test_share_endpoint_moves_the_counter_and_logs_telemetry(): void
    {
        $short = $this->makeShort();
        $customer = $this->makeCustomer();

        $response = $this->interactions()->share($this->authed($customer, ['channel' => 'instagram']), $short->id);

        $this->assertSame(200, $response->getStatusCode());

        $short->refresh();
        $this->assertSame(1, $short->shares);
        $this->assertSame(1, ShortEvent::query()->where('type', 'share')->count());
    }

    /* ---------------- D2 — remind / drop ---------------- */

    public function test_sale_window_reports_pending_when_tickets_are_not_out_yet(): void
    {
        $ticketType = TicketType::create([
            'event_id' => 1,
            'name' => 'Early bird',
            'sales_start_at' => now()->addDays(3),
        ]);

        $short = $this->makeShort(['event_id' => 1, 'cta_ticket_type_id' => $ticketType->id]);

        $window = app(ShortReminderService::class)->saleWindow($short);

        $this->assertTrue($window['pending']);
        $this->assertSame($ticketType->id, $window['ticket_type_id']);
    }

    public function test_reminder_is_idempotent_and_copies_the_sale_moment(): void
    {
        $onSale = now()->addDays(2)->startOfSecond();

        $ticketType = TicketType::create(['event_id' => 1, 'name' => 'GA', 'sales_start_at' => $onSale]);
        $short = $this->makeShort(['event_id' => 1, 'cta_ticket_type_id' => $ticketType->id]);
        $customer = $this->makeCustomer();

        $service = app(ShortReminderService::class);
        $service->remind($short, $customer);
        $service->remind($short, $customer);

        $this->assertSame(1, ShortReminder::query()->count());
        $this->assertSame(
            $onSale->toDateTimeString(),
            ShortReminder::query()->firstOrFail()->remind_at->toDateTimeString(),
        );
        $this->assertTrue($service->isReminded($short, $customer));

        $service->forget($short, $customer);
        $this->assertFalse($service->isReminded($short, $customer));
    }

    public function test_remind_endpoint_refuses_when_tickets_are_already_on_sale(): void
    {
        $ticketType = TicketType::create(['event_id' => 1, 'name' => 'GA', 'sales_start_at' => now()->subDay()]);
        $short = $this->makeShort(['event_id' => 1, 'cta_ticket_type_id' => $ticketType->id]);

        $response = $this->interactions()->remind($this->authed($this->makeCustomer()), $short->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, ShortReminder::query()->count());
    }

    public function test_drop_job_fires_due_reminders_once_and_only_once(): void
    {
        $short = $this->makeShort(['event_id' => 1]);
        $customer = $this->makeCustomer();

        ShortReminder::create([
            'marketplace_customer_id' => $customer->id,
            'short_id' => $short->id,
            'event_id' => 1,
            'remind_at' => now()->subMinute(),
        ]);

        // Not due yet — must be left alone.
        ShortReminder::create([
            'marketplace_customer_id' => $this->makeCustomer()->id,
            'short_id' => $short->id,
            'event_id' => 1,
            'remind_at' => now()->addDay(),
        ]);

        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) implements PushSender
        {
            public function __construct(public array &$sent) {}

            public function send(MarketplaceCustomer $customer, string $title, string $body, array $data = []): bool
            {
                $this->sent[] = $data;

                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });

        $job = new FireDropRemindersJob;
        $job->handle($this->app->make(PushSender::class));

        $this->assertSame(1, ShortReminder::query()->whereNotNull('notified_at')->count());

        // Second pass must be a no-op: a drop notice re-sent is worse than none.
        $job->handle($this->app->make(PushSender::class));
        $this->assertSame(1, ShortReminder::query()->whereNotNull('notified_at')->count());
    }

    /* ---------------- D11 — gamification ---------------- */

    public function test_daily_watch_counts_once_per_day_and_extends_the_streak(): void
    {
        $customer = $this->makeCustomer();
        $service = app(ShortGamificationService::class);

        $first = $service->recordDailyWatch($customer);
        $this->assertTrue($first['awarded']);
        $this->assertSame(1, $first['streak']);

        $again = $service->recordDailyWatch($customer);
        $this->assertFalse($again['awarded']);
        $this->assertSame(0, $again['points']);
        $this->assertSame(1, $again['streak']);

        // Yesterday's watch continues the streak; a gap resets it.
        $streak = $service->streakFor($customer);
        $streak->forceFill(['last_watch_date' => now()->subDay()->toDateString()])->save();

        $this->assertSame(2, $service->recordDailyWatch($customer)['streak']);

        $streak->refresh()->forceFill(['last_watch_date' => now()->subDays(4)->toDateString()])->save();
        $this->assertSame(1, $service->recordDailyWatch($customer)['streak']);
    }

    public function test_daily_points_cap_stops_farming(): void
    {
        config(['shorts.gamification.daily_cap' => 12, 'shorts.gamification.share_points' => 10]);

        $customer = $this->makeCustomer();
        $service = app(ShortGamificationService::class);

        $this->assertSame(10, $service->recordShare($customer)['points']);
        // Only 2 left under the cap, so the second share is partially paid...
        $this->assertSame(2, $service->recordShare($customer)['points']);
        // ...and the third gets nothing.
        $this->assertSame(0, $service->recordShare($customer)['points']);

        $this->assertSame(12, $service->streakFor($customer)->fresh()->total_points);
    }

    public function test_streak_endpoint_reports_the_current_state(): void
    {
        $customer = $this->makeCustomer();
        app(ShortGamificationService::class)->recordDailyWatch($customer);

        $data = $this->interactions()->streak($this->authed($customer))->getData(true)['data'];

        $this->assertSame(1, $data['current_streak']);
        $this->assertGreaterThan(0, $data['total_points']);
    }

    /* ---------------- D9 — payload for the player ---------------- */

    public function test_feed_payload_carries_the_blurhash_and_content_flags(): void
    {
        $short = $this->makeShort([
            'blurhash' => 'g2x3:112233445566778899aabbccddeeff',
            'content_flags' => ['flashing'],
        ]);

        $item = app(ShortPayload::class)->one($short);

        $this->assertSame('g2x3:112233445566778899aabbccddeeff', $item['playback']['blurhash']);
        $this->assertSame(['flashing'], $item['content_flags']);
    }

    public function test_feed_payload_marks_a_pending_drop_on_the_cta(): void
    {
        $ticketType = TicketType::create(['event_id' => 1, 'name' => 'GA', 'sales_start_at' => now()->addWeek()]);

        $short = $this->makeShort([
            'event_id' => 1,
            'cta_type' => 'buy_tickets',
            'cta_ticket_type_id' => $ticketType->id,
        ]);

        $item = app(ShortPayload::class)->one($short);

        $this->assertTrue($item['cta']['pending']);
        $this->assertNotNull($item['cta']['on_sale_at']);
    }

    /* ---------------- helpers ---------------- */

    protected function interactions(): ShortInteractionsController
    {
        return new ShortInteractionsController(
            app(ShortShareService::class),
            app(ShortReminderService::class),
            app(ShortGamificationService::class),
        );
    }

    protected function authed(MarketplaceCustomer $customer, array $body = []): Request
    {
        $request = Request::create('/', 'POST', $body);
        $request->setUserResolver(fn () => $customer);

        return $request;
    }

    protected function makeCustomer(): MarketplaceCustomer
    {
        return MarketplaceCustomer::create([
            'email' => 'viewer-'.uniqid().'@example.test',
            'first_name' => 'Test',
            'status' => 'active',
        ]);
    }

    protected function makeShort(array $attributes = []): Short
    {
        return Short::create(array_merge([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }
}
