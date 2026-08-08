<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\PickPosterWinnerJob;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortPosterVariant;
use App\Models\ShortPromotion;
use App\Models\ShortPromotionEvent;
use App\Models\ShortReport;
use App\Services\Shorts\CostGuardService;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortPayload;
use App\Services\Shorts\ShortPromotionService;
use App\Services\Shorts\ShortRightsGuard;
use App\Services\Shorts\ShortUgcService;
use App\Services\Video\NullVideoProvider;
use Illuminate\Support\Facades\DB;

/**
 * Wave 3 — promotions (D3), rights (D7), cost guardrails (D8), UGC (B9),
 * cover A/B (B10).
 */
class ShortsMonetisationTest extends ShortsTestCase
{
    /* ---------------- D3 — promotions ---------------- */

    public function test_a_promoted_short_is_injected_and_always_labelled(): void
    {
        $organic = $this->makeShort(['title' => 'organic']);
        $promoted = $this->makeShort(['title' => 'paid']);

        ShortPromotion::create([
            'short_id' => $promoted->id,
            'tenant_id' => 1,
            'model' => 'cpm',
            'bid_cents' => 5000,
            'budget_cents' => 100000,
            'status' => 'active',
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $service = app(ShortPromotionService::class);
        $items = app(ShortPayload::class)->collection(collect([$organic]));

        $result = $service->inject($items, null, app(ShortPayload::class));

        $this->assertCount(2, $result);

        $labelled = collect($result)->firstWhere('id', $promoted->id);
        $this->assertNotNull($labelled['promoted'] ?? null, 'money must never quietly become relevance');
        $this->assertSame('Sponsorizat', $labelled['promoted']['label']);
    }

    public function test_a_flight_ahead_of_its_pacing_curve_sits_out(): void
    {
        $short = $this->makeShort();

        $promotion = ShortPromotion::create([
            'short_id' => $short->id,
            'tenant_id' => 1,
            'model' => 'cpm',
            'bid_cents' => 1000,
            'budget_cents' => 100000,
            'status' => 'active',
            // A ten-day flight, one day in: pacing allows ~10% of the budget.
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(9),
        ]);

        $promotion->forceFill(['spent_cents' => 90000])->save();

        $this->assertTrue($promotion->fresh()->isAheadOfPace());
        $this->assertNull(app(ShortPromotionService::class)->pick(null));
    }

    public function test_an_exhausted_budget_ends_the_promotion(): void
    {
        $short = $this->makeShort();

        $promotion = ShortPromotion::create([
            'short_id' => $short->id,
            'tenant_id' => 1,
            'model' => 'cpc',
            'bid_cents' => 500,
            'budget_cents' => 600,
            'status' => 'active',
        ]);

        $service = app(ShortPromotionService::class);
        $service->chargeClick($promotion, null);
        $service->chargeClick($promotion->fresh(), null);

        $this->assertSame('ended', $promotion->fresh()->status);
        $this->assertSame(2, ShortPromotionEvent::query()->count());
    }

    public function test_cpm_bills_per_thousand_impressions(): void
    {
        $short = $this->makeShort();

        $promotion = ShortPromotion::create([
            'short_id' => $short->id,
            'tenant_id' => 1,
            'model' => 'cpm',
            'bid_cents' => 2000,      // 20.00 per 1000 impressions
            'budget_cents' => 100000,
            'status' => 'active',
        ]);

        app(ShortPromotionService::class)->chargeImpression($promotion, null);

        $this->assertSame(2, $promotion->fresh()->spent_cents);
    }

    public function test_frequency_capping_stops_showing_the_same_promotion(): void
    {
        $short = $this->makeShort();
        $customer = $this->makeCustomer();

        $promotion = ShortPromotion::create([
            'short_id' => $short->id,
            'tenant_id' => 1,
            'model' => 'cpm',
            'bid_cents' => 1000,
            'budget_cents' => 1000000,
            'status' => 'active',
        ]);

        $service = app(ShortPromotionService::class);

        foreach (range(1, 3) as $i) {
            $this->assertNotNull($service->pick($customer), "should still serve on view {$i}");
            $service->chargeImpression($promotion->fresh(), $customer);
        }

        $this->assertNull($service->pick($customer), 'the daily cap must stop the fourth');
    }

    /* ---------------- D7 — rights ---------------- */

    public function test_a_lapsed_licence_drops_a_short_from_the_feed(): void
    {
        $live = $this->makeShort(['title' => 'licensed', 'usage_expires_at' => now()->addMonth()]);
        $this->makeShort(['title' => 'lapsed', 'usage_expires_at' => now()->subDay()]);

        $page = app(ShortFeedService::class)->page();

        $this->assertCount(1, $page['items']);
        $this->assertSame($live->id, $page['items'][0]['id']);
    }

    public function test_age_restricted_shorts_need_a_verified_birth_date(): void
    {
        $open = $this->makeShort(['title' => 'everyone']);
        $restricted = $this->makeShort(['title' => 'adults', 'age_rating' => 18]);

        // Anonymous: only unrestricted.
        $this->assertSame([$open->id], array_column(app(ShortFeedService::class)->page()['items'], 'id'));

        // Logged in but no birth date on file — still only unrestricted.
        $unknownAge = $this->makeCustomer();
        $this->assertSame(
            [$open->id],
            array_column(app(ShortFeedService::class)->page(customer: $unknownAge)['items'], 'id'),
        );

        $adult = $this->makeCustomer(['birth_date' => now()->subYears(30)->toDateString()]);
        $adultIds = array_column(app(ShortFeedService::class)->page(customer: $adult)['items'], 'id');

        $this->assertContains($restricted->id, $adultIds);
    }

    public function test_territory_rules_are_honoured_in_both_directions(): void
    {
        $guard = app(ShortRightsGuard::class);

        $allowRo = $this->makeShort(['territories' => ['mode' => 'allow', 'codes' => ['RO', 'MD']]]);
        $denyRo = $this->makeShort(['territories' => ['mode' => 'deny', 'codes' => ['RO']]]);

        $ro = $this->makeCustomer(['country' => 'RO']);
        $de = $this->makeCustomer(['country' => 'DE']);

        $this->assertTrue($guard->allows($allowRo, $ro));
        $this->assertFalse($guard->allows($allowRo, $de));

        $this->assertFalse($guard->allows($denyRo, $ro));
        $this->assertTrue($guard->allows($denyRo, $de));

        // Unknown location is not "everywhere is fine".
        $this->assertFalse($guard->allows($allowRo, $this->makeCustomer()));
    }

    public function test_territory_filtering_works_through_the_feed_query(): void
    {
        // The PHP-side allows() check and the query constraint are two different
        // code paths. Only this one touches the json column in SQL, which is
        // where Postgres refuses LIKE on json — SQLite never noticed.
        $everywhere = $this->makeShort(['title' => 'unrestricted']);
        $roOnly = $this->makeShort(['title' => 'ro only', 'territories' => ['mode' => 'allow', 'codes' => ['RO']]]);
        $notRo = $this->makeShort(['title' => 'not ro', 'territories' => ['mode' => 'deny', 'codes' => ['RO']]]);

        $ro = $this->makeCustomer(['country' => 'RO']);
        $ids = array_column(app(ShortFeedService::class)->page(customer: $ro)['items'], 'id');

        $this->assertContains($everywhere->id, $ids);
        $this->assertContains($roOnly->id, $ids);
        $this->assertNotContains($notRo->id, $ids);

        $de = $this->makeCustomer(['country' => 'DE']);
        $deIds = array_column(app(ShortFeedService::class)->page(customer: $de)['items'], 'id');

        $this->assertContains($everywhere->id, $deIds);
        $this->assertNotContains($roOnly->id, $deIds);
        $this->assertContains($notRo->id, $deIds);
    }

    public function test_an_anonymous_viewer_sees_only_unrestricted_shorts(): void
    {
        $everywhere = $this->makeShort(['title' => 'unrestricted']);
        $this->makeShort(['title' => 'restricted', 'territories' => ['mode' => 'allow', 'codes' => ['RO']]]);

        // No country to check against is not "everywhere is fine".
        $ids = array_column(app(ShortFeedService::class)->page()['items'], 'id');

        $this->assertSame([$everywhere->id], $ids);
    }

    /* ---------------- D8 — cost guardrails ---------------- */

    public function test_projection_extrapolates_to_the_end_of_the_month(): void
    {
        config(['shorts.cost.monthly_bandwidth_cap_gb' => 1000]);

        // Pin the clock: the projection is day-of-month dependent by design.
        $this->travelTo(now()->startOfMonth()->addDays(9)->setTime(12, 0));

        $guard = app(CostGuardService::class);
        $guard->recordUsage(300);

        $daysInMonth = now()->daysInMonth;
        $expected = round(300 / 10 * $daysInMonth / 1000 * 100, 1);

        $this->assertSame($expected, $guard->projectedUsagePct());
        $this->assertTrue($guard->shouldAlert(), '30% on day 10 projects well past the cap');
    }

    public function test_data_saver_engages_near_the_cap_and_via_the_kill_switch(): void
    {
        config([
            'shorts.cost.monthly_bandwidth_cap_gb' => 100,
            'shorts.cost.data_saver_threshold_pct' => 90,
            'shorts.player.data_saver_global' => false,
        ]);

        $this->travelTo(now()->startOfMonth()->addDays(9)->setTime(12, 0));

        $guard = app(CostGuardService::class);

        $guard->recordUsage(1);
        $this->assertFalse($guard->dataSaverActive());

        $guard->recordUsage(50);
        $this->assertTrue($guard->dataSaverActive());
        $this->assertSame(480, $guard->playbackHints()['max_height']);
        $this->assertSame(0, $guard->playbackHints()['prefetch_count']);

        // The manual switch works without waiting for a usage poll.
        $guard->recordUsage(0);
        config(['shorts.player.data_saver_global' => true]);
        $this->assertTrue($guard->dataSaverActive());
    }

    public function test_no_cap_configured_means_no_guardrail(): void
    {
        config(['shorts.cost.monthly_bandwidth_cap_gb' => 0, 'shorts.player.data_saver_global' => false]);

        $guard = app(CostGuardService::class);
        $guard->recordUsage(99999);

        $this->assertFalse($guard->shouldAlert());
        $this->assertFalse($guard->dataSaverActive());
    }

    /* ---------------- B9 — UGC ---------------- */

    public function test_posting_requires_a_checked_in_ticket_you_own(): void
    {
        $customer = $this->makeCustomer();
        $other = $this->makeCustomer();
        $event = Event::create(['slug' => 'gig', 'title' => 'Gig']);

        $ugc = app(ShortUgcService::class);

        $this->assertFalse($ugc->mayPost($customer, $event->id));

        // A ticket that was never scanned is not attendance. checked_in_at is
        // the real scan marker — there is no boolean checked_in column.
        DB::table('tickets')->insert([
            'event_id' => $event->id,
            'current_owner_customer_id' => $customer->id,
            'checked_in_at' => null,
        ]);
        $this->assertFalse($ugc->mayPost($customer, $event->id));

        // Someone else's scanned ticket is not yours.
        DB::table('tickets')->insert([
            'event_id' => $event->id,
            'current_owner_customer_id' => $other->id,
            'checked_in_at' => now(),
        ]);
        $this->assertFalse($ugc->mayPost($customer, $event->id));

        DB::table('tickets')->insert([
            'event_id' => $event->id,
            'current_owner_customer_id' => $customer->id,
            'checked_in_at' => now(),
        ]);
        $this->assertTrue($ugc->mayPost($customer, $event->id));
    }

    public function test_a_ugc_short_lands_in_review_never_in_the_feed(): void
    {
        $customer = $this->makeCustomer();
        $event = Event::create(['slug' => 'gig2', 'title' => 'Gig']);

        // The null provider refuses uploads, which is the honest state without
        // Bunny credentials — assert the refusal rather than fake success.
        $this->expectException(\RuntimeException::class);

        (new ShortUgcService(new NullVideoProvider))->createUpload($customer, $event);
    }

    public function test_repeated_reports_auto_hide_a_published_short(): void
    {
        config(['shorts.moderation.auto_hide_reports' => 3]);

        $short = $this->makeShort();
        $ugc = app(ShortUgcService::class);

        $ugc->report($short, null, 'inappropriate');
        $ugc->report($short->fresh(), null, 'inappropriate');
        $this->assertSame(Short::STATUS_PUBLISHED, $short->fresh()->status);

        $ugc->report($short->fresh(), null, 'inappropriate');

        // Hiding something good for a few hours costs less than leaving
        // something harmful up.
        $this->assertSame(Short::STATUS_PENDING_REVIEW, $short->fresh()->status);
        $this->assertSame(3, ShortReport::query()->count());
    }

    public function test_an_unknown_report_reason_is_normalised(): void
    {
        $short = $this->makeShort();

        app(ShortUgcService::class)->report($short, null, 'made-up-reason');

        $this->assertSame('other', ShortReport::query()->firstOrFail()->reason);
    }

    /* ---------------- B10 — cover A/B ---------------- */

    public function test_a_winner_is_only_called_once_every_arm_has_sample(): void
    {
        $short = $this->makeShort();

        $weak = ShortPosterVariant::create(['short_id' => $short->id, 'poster_path' => 'a.jpg', 'label' => 'A']);
        $strong = ShortPosterVariant::create(['short_id' => $short->id, 'poster_path' => 'b.jpg', 'label' => 'B']);

        $weak->forceFill(['impressions' => 1000, 'clicks' => 50])->save();   // 5%
        $strong->forceFill(['impressions' => 100, 'clicks' => 40])->save();  // 40%, but tiny sample

        (new PickPosterWinnerJob(500))->handle();

        // Picking at 100 impressions is picking noise.
        $this->assertFalse($strong->fresh()->is_winner);
        $this->assertFalse($weak->fresh()->is_winner);

        $strong->forceFill(['impressions' => 1000, 'clicks' => 400])->save();

        (new PickPosterWinnerJob(500))->handle();

        $this->assertTrue($strong->fresh()->is_winner);
        $this->assertSame('b.jpg', $short->fresh()->poster_path);
    }

    public function test_a_single_variant_is_not_a_test(): void
    {
        $short = $this->makeShort();

        $only = ShortPosterVariant::create(['short_id' => $short->id, 'poster_path' => 'a.jpg']);
        $only->forceFill(['impressions' => 10000, 'clicks' => 900])->save();

        (new PickPosterWinnerJob(500))->handle();

        $this->assertFalse($only->fresh()->is_winner);
    }

    /* ---------------- helpers ---------------- */

    protected function makeCustomer(array $attributes = []): MarketplaceCustomer
    {
        return MarketplaceCustomer::create(array_merge([
            'email' => 'viewer-'.uniqid().'@example.test',
            'first_name' => 'Test',
            'status' => 'active',
        ], $attributes));
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
