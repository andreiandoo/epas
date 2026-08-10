<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\AggregateShortRetentionJob;
use App\Jobs\Shorts\ComputeTrendingJob;
use App\Jobs\Shorts\EvaluateBehaviouralTriggersJob;
use App\Jobs\Shorts\PruneShortEventsJob;
use App\Jobs\Shorts\SyncShortImpressionsJob;
use App\Models\MarketplaceCustomer;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Services\Push\PushSender;
use App\Services\Shorts\ShortFeedService;
use Illuminate\Support\Facades\DB;

/**
 * Wave 2 — retention and trending (D4), telemetry scale (D6), ranker evolution
 * (D5), behavioural notifications (D12).
 */
class ShortsMeasurementTest extends ShortsTestCase
{
    /* ---------------- D4 — trending ---------------- */

    public function test_trending_rewards_velocity_not_lifetime_totals(): void
    {
        $rising = $this->makeShort(['title' => 'rising']);
        $steady = $this->makeShort(['title' => 'steady']);

        // The rising short's engagement is all in the last few hours.
        $this->events($rising, 20, now()->subHour());

        // The steady one has far more events, spread across the baseline window.
        $this->events($steady, 10, now()->subHour());
        $this->events($steady, 90, now()->subHours(30));

        (new ComputeTrendingJob)->handle();

        $this->assertGreaterThan(
            $steady->fresh()->trending_score,
            $rising->fresh()->trending_score,
            'a short picking up now must outrank a bigger steady performer',
        );
    }

    public function test_trending_decays_when_engagement_stops(): void
    {
        $short = $this->makeShort();
        $short->forceFill(['trending_score' => 42])->save();

        // Nothing inside the window at all.
        $this->events($short, 5, now()->subDays(10));

        (new ComputeTrendingJob)->handle();

        $this->assertSame(0.0, (float) $short->fresh()->trending_score);
    }

    /* ---------------- D4 — retention ---------------- */

    public function test_retention_buckets_watch_ratios_into_deciles(): void
    {
        $short = $this->makeShort();
        $date = now()->subDay();

        foreach ([0.05, 0.05, 0.35, 0.95, 1.0] as $ratio) {
            ShortEvent::create([
                'short_id' => $short->id,
                'type' => ShortEvent::TYPE_VIEW,
                'watch_ratio' => $ratio,
                'created_at' => $date,
            ]);
        }

        (new AggregateShortRetentionJob($date->toDateString()))->handle();

        $rows = DB::table('short_retention')->where('short_id', $short->id)->pluck('count', 'bucket');

        $this->assertSame(2, (int) $rows[0]);
        $this->assertSame(1, (int) $rows[3]);
        // 0.95 and 1.0 both belong in the last bucket, not an eleventh one.
        $this->assertSame(2, (int) $rows[9]);
    }

    public function test_retention_rebuild_replaces_rather_than_doubles(): void
    {
        $short = $this->makeShort();
        $date = now()->subDay();

        ShortEvent::create([
            'short_id' => $short->id,
            'type' => ShortEvent::TYPE_VIEW,
            'watch_ratio' => 0.5,
            'created_at' => $date,
        ]);

        $job = new AggregateShortRetentionJob($date->toDateString());
        $job->handle();
        $job->handle();

        $this->assertSame(1, (int) DB::table('short_retention')->where('short_id', $short->id)->sum('count'));
    }

    /* ---------------- D6 — telemetry scale ---------------- */

    public function test_prune_deletes_only_events_past_the_retention_window(): void
    {
        config(['shorts.telemetry.retention_days' => 30]);

        $short = $this->makeShort();

        $this->events($short, 3, now()->subDays(60));
        $this->events($short, 2, now()->subDays(5));

        (new PruneShortEventsJob)->handle();

        $this->assertSame(2, ShortEvent::query()->count());
    }

    /* ---------------- D5 — ranker evolution ---------------- */

    public function test_seen_store_is_populated_from_telemetry(): void
    {
        $short = $this->makeShort();
        $customer = $this->makeCustomer();

        ShortEvent::create([
            'short_id' => $short->id,
            'marketplace_customer_id' => $customer->id,
            'type' => ShortEvent::TYPE_VIEW,
            'created_at' => now(),
        ]);

        (new SyncShortImpressionsJob)->handle();

        $this->assertSame(1, DB::table('short_impressions')
            ->where('marketplace_customer_id', $customer->id)
            ->where('short_id', $short->id)
            ->count());
    }

    public function test_the_seen_penalty_survives_the_telemetry_prune(): void
    {
        $customer = $this->makeCustomer();

        $seen = $this->makeShort(['title' => 'seen', 'published_at' => now()->subMinute()]);
        $unseen = $this->makeShort(['title' => 'unseen', 'published_at' => now()->subHours(6)]);

        // Only the distilled store knows — the raw event is gone.
        DB::table('short_impressions')->insert([
            'marketplace_customer_id' => $customer->id,
            'short_id' => $seen->id,
            'last_seen' => now()->subDays(120),
        ]);

        $page = app(ShortFeedService::class)->page(feed: 'for_you', customer: $customer);

        $this->assertSame($unseen->id, $page['items'][0]['id']);
    }

    public function test_exploration_reserves_slots_for_shorts_with_no_signal(): void
    {
        config([
            'shorts.ranker.exploration_rate' => 0.5,
            'shorts.ranker.exploration_impression_threshold' => 10,
            'shorts.feed.diversity_enabled' => false,
        ]);

        // Four established shorts that would otherwise fill the page.
        foreach (range(1, 4) as $i) {
            $short = $this->makeShort(['title' => "established {$i}", 'published_at' => now()->subMinutes($i)]);
            $short->forceFill(['impressions' => 5000, 'views' => 4000, 'likes' => 500])->save();
        }

        // A brand new one with no signal at all, published earlier so it loses on score.
        $fresh = $this->makeShort(['title' => 'brand new', 'published_at' => now()->subDays(2)]);

        $page = app(ShortFeedService::class)->page(feed: 'for_you', limit: 4);

        $this->assertContains(
            $fresh->id,
            array_column($page['items'], 'id'),
            'without exploration a short with no impressions can never earn any',
        );
    }

    /* ---------------- D12 — behavioural notifications ---------------- */

    /**
     * The job refuses to send during quiet hours, so these tests pin the clock
     * to the middle of the afternoon. Without this they pass or fail depending
     * on what time CI happens to run.
     */
    protected function atSendableHour(): void
    {
        $this->travelTo(now()->setTime(15, 0));
    }

    public function test_a_nudge_fires_only_past_the_intent_threshold(): void
    {
        $this->atSendableHour();

        $customer = $this->makeCustomer();
        $this->allowNudges($customer);

        // Two shorts watched for the same event — below the threshold.
        foreach (range(1, 2) as $i) {
            $short = $this->makeShort(['event_id' => 77]);
            ShortEvent::create([
                'short_id' => $short->id,
                'marketplace_customer_id' => $customer->id,
                'type' => ShortEvent::TYPE_VIEW,
                'created_at' => now(),
            ]);
        }

        $push = $this->recordingPush();
        (new EvaluateBehaviouralTriggersJob)->handle($push);
        $this->assertCount(0, $push->sent);

        // A third pushes it over.
        $third = $this->makeShort(['event_id' => 77]);
        ShortEvent::create([
            'short_id' => $third->id,
            'marketplace_customer_id' => $customer->id,
            'type' => ShortEvent::TYPE_VIEW,
            'created_at' => now(),
        ]);

        (new EvaluateBehaviouralTriggersJob)->handle($push);
        $this->assertCount(1, $push->sent);
    }

    public function test_nobody_is_nudged_to_buy_what_they_already_bought(): void
    {
        $this->atSendableHour();

        $customer = $this->makeCustomer();
        $this->allowNudges($customer);
        $this->buildIntent($customer, 77);

        Order::create([
            'marketplace_customer_id' => $customer->id,
            'event_id' => 77,
            'status' => 'paid',
            'total' => 100,
        ]);

        $push = $this->recordingPush();
        (new EvaluateBehaviouralTriggersJob)->handle($push);

        $this->assertCount(0, $push->sent);
    }

    public function test_nudges_are_opt_in(): void
    {
        $this->atSendableHour();

        $customer = $this->makeCustomer();
        $this->buildIntent($customer, 77);

        // No preference row — the default for this type is off, because a
        // "you looked but didn't buy" nudge is the one most likely to feel
        // like surveillance.
        $push = $this->recordingPush();
        (new EvaluateBehaviouralTriggersJob)->handle($push);

        $this->assertCount(0, $push->sent);
    }

    public function test_a_nudge_is_not_repeated_inside_the_cooldown(): void
    {
        $this->atSendableHour();

        $customer = $this->makeCustomer();
        $this->allowNudges($customer);
        $this->buildIntent($customer, 77);

        $push = $this->recordingPush();
        $job = new EvaluateBehaviouralTriggersJob;

        $job->handle($push);
        $job->handle($push);

        $this->assertCount(1, $push->sent);
    }

    public function test_notification_preferences_fall_back_to_type_defaults(): void
    {
        $customer = $this->makeCustomer();

        // No row at all → the type default applies.
        $this->assertTrue(NotificationPreference::allows($customer->id, NotificationPreference::TYPE_SHORTS_DROPPED));
        $this->assertFalse(NotificationPreference::allows($customer->id, NotificationPreference::TYPE_SHORTS_ABANDONED));

        NotificationPreference::create([
            'marketplace_customer_id' => $customer->id,
            'type' => NotificationPreference::TYPE_SHORTS_DROPPED,
            'push' => false,
        ]);

        $this->assertFalse(NotificationPreference::allows($customer->id, NotificationPreference::TYPE_SHORTS_DROPPED));
    }

    /* ---------------- helpers ---------------- */

    protected function recordingPush(): PushSender
    {
        return new class implements PushSender
        {
            /** @var array<int, array<string, mixed>> */
            public array $sent = [];

            public function send(MarketplaceCustomer $customer, string $title, string $body, array $data = []): bool
            {
                $this->sent[] = $data;

                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };
    }

    protected function allowNudges(MarketplaceCustomer $customer): void
    {
        NotificationPreference::create([
            'marketplace_customer_id' => $customer->id,
            'type' => NotificationPreference::TYPE_SHORTS_ABANDONED,
            'push' => true,
        ]);
    }

    protected function buildIntent(MarketplaceCustomer $customer, int $eventId): void
    {
        foreach (range(1, 3) as $i) {
            $short = $this->makeShort(['event_id' => $eventId]);
            ShortEvent::create([
                'short_id' => $short->id,
                'marketplace_customer_id' => $customer->id,
                'type' => ShortEvent::TYPE_VIEW,
                'created_at' => now(),
            ]);
        }
    }

    protected function events(Short $short, int $count, \DateTimeInterface $at): void
    {
        foreach (range(1, $count) as $i) {
            ShortEvent::create([
                'short_id' => $short->id,
                'type' => ShortEvent::TYPE_VIEW,
                'created_at' => $at,
            ]);
        }
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
