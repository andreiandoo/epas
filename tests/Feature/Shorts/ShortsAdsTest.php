<?php

namespace Tests\Feature\Shorts;

use App\Filament\Resources\Shorts\ShortAdvertiserResource;
use App\Filament\Resources\Shorts\ShortPromotionResource as CoreShortPromotionResource;
use App\Filament\Tenant\Resources\ShortPromotionResource as TenantShortPromotionResource;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortAdvertiser;
use App\Models\ShortAdvertiserTransaction;
use App\Models\ShortPromotion;
use App\Models\ShortPromotionEvent;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortPayload;
use App\Services\Shorts\ShortPromotionService;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Ads in the feed (D3, extended): who pays, who is targeted, who is charged and
 * what the viewer is told.
 *
 * The promotion machinery existed before this suite but was never reachable —
 * inject() had no caller and chargeClick() was never invoked, so a campaign
 * could be created, approved and funded without a single impression ever being
 * served. These tests exist so that cannot silently happen again.
 */
class ShortsAdsTest extends ShortsTestCase
{
    /* ---------------- reaching the feed at all ---------------- */

    public function test_an_approved_campaign_actually_reaches_the_feed(): void
    {
        // The promoted short is old enough not to surface organically, so what
        // lands on the page can only have got there through the ad slot.
        $promoted = $this->makeShort(['title' => 'paid', 'published_at' => now()->subYear()]);
        $this->campaign($promoted);

        foreach (range(1, 12) as $i) {
            $this->makeShort(['title' => "organic {$i}"]);
        }

        $result = app(ShortFeedService::class)->page(feed: 'for_you', limit: 10);

        $ad = collect($result['items'])->first(fn (array $item) => isset($item['promoted']));

        $this->assertNotNull($ad, 'a funded, approved, in-flight campaign must be served');
        $this->assertSame($promoted->id, $ad['id']);
        $this->assertSame('Sponsorizat', $ad['promoted']['label']);
    }

    public function test_a_short_already_on_the_page_is_not_also_served_as_an_ad(): void
    {
        $promoted = $this->makeShort(['title' => 'paid']);
        $this->campaign($promoted);

        $result = app(ShortFeedService::class)->page(feed: 'for_you', limit: 10);

        $this->assertSame(
            1,
            collect($result['items'])->where('id', $promoted->id)->count(),
            'paying for a slot must not buy the same short twice on one screen',
        );
    }

    public function test_owner_pages_carry_no_ads(): void
    {
        $short = $this->makeShort();
        $this->campaign($this->makeShort(['title' => 'paid']));

        // "event" and "artist" are one organiser's own page; injecting a rival
        // there is a business decision, not a default.
        $result = app(ShortFeedService::class)->page(feed: 'event', limit: 10, filters: ['event_id' => $short->event_id]);

        foreach ($result['items'] as $item) {
            $this->assertArrayNotHasKey('promoted', $item);
        }
    }

    public function test_ads_can_be_switched_off_entirely(): void
    {
        $this->makeShort();
        $this->campaign($this->makeShort(['title' => 'paid']));

        config()->set('shorts.ads.enabled', false);

        $result = app(ShortFeedService::class)->page(feed: 'for_you', limit: 10);

        foreach ($result['items'] as $item) {
            $this->assertArrayNotHasKey('promoted', $item);
        }
    }

    /* ---------------- disclosure ---------------- */

    public function test_a_brand_ad_is_labelled_differently_from_a_boosted_event(): void
    {
        $short = $this->makeShort();

        $brand = $this->campaign($short, ['objective' => ShortPromotion::OBJECTIVE_BRAND]);
        $this->assertSame('Reclamă', $brand->disclosure());

        $house = $this->campaign($this->makeShort(), ['objective' => ShortPromotion::OBJECTIVE_HOUSE]);
        $this->assertSame('Recomandat de Tixello', $house->disclosure());

        $event = $this->campaign($this->makeShort(), ['objective' => ShortPromotion::OBJECTIVE_EVENT]);
        $this->assertSame('Sponsorizat', $event->disclosure());
    }

    public function test_a_brand_ad_needs_no_tenant_at_all(): void
    {
        // The original schema made tenant_id NOT NULL, so the only thing that
        // could be advertised was an organiser's own event. This is the case
        // that could not exist before: a paying brand with no tenant account.
        $advertiser = ShortAdvertiser::create([
            'name' => 'Some Brand SRL',
            'type' => ShortAdvertiser::TYPE_EXTERNAL,
            'contact_email' => 'ads@brand.example',
        ]);
        $advertiser->topUp(50_000);

        $short = $this->makeShort(['title' => 'brand spot', 'published_at' => now()->subYear()]);

        $promotion = ShortPromotion::create([
            'short_id' => $short->id,
            'short_advertiser_id' => $advertiser->id,
            'tenant_id' => null,
            'objective' => ShortPromotion::OBJECTIVE_BRAND,
            'model' => ShortPromotion::MODEL_CPM,
            'bid_cents' => 8000,
            'budget_cents' => 50_000,
            'status' => ShortPromotion::STATUS_ACTIVE,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(30),
        ]);

        foreach (range(1, 12) as $i) {
            $this->makeShort(['title' => "organic {$i}"]);
        }

        $result = app(ShortFeedService::class)->page(feed: 'for_you', limit: 10);
        $ad = collect($result['items'])->first(fn (array $item) => isset($item['promoted']));

        $this->assertNotNull($ad);
        $this->assertSame($promotion->id, $ad['promoted']['id']);
        $this->assertSame('Reclamă', $ad['promoted']['label']);
        $this->assertSame('Some Brand SRL', $ad['promoted']['advertiser']);
        $this->assertSame(49_992, $advertiser->fresh()->credit_cents);
    }

    public function test_an_explicit_disclosure_label_wins(): void
    {
        $promotion = $this->campaign($this->makeShort(), [
            'objective' => ShortPromotion::OBJECTIVE_BRAND,
            'disclosure_label' => 'Publicitate plătită',
        ]);

        $this->assertSame('Publicitate plătită', $promotion->disclosure());
    }

    /* ---------------- who pays ---------------- */

    public function test_an_advertiser_out_of_credit_stops_being_served(): void
    {
        $advertiser = ShortAdvertiser::create([
            'name' => 'Broke Brand',
            'type' => ShortAdvertiser::TYPE_EXTERNAL,
            'status' => ShortAdvertiser::STATUS_ACTIVE,
        ]);

        $this->campaign($this->makeShort(), ['short_advertiser_id' => $advertiser->id]);

        $this->assertNull(
            app(ShortPromotionService::class)->pick(null),
            'serving an impression we cannot bill for is revenue that never existed',
        );

        $advertiser->topUp(10_000);

        $this->assertNotNull(app(ShortPromotionService::class)->pick(null));
    }

    public function test_a_house_advertiser_never_needs_credit(): void
    {
        $house = ShortAdvertiser::create([
            'name' => 'Tixello',
            'type' => ShortAdvertiser::TYPE_HOUSE,
            'status' => ShortAdvertiser::STATUS_ACTIVE,
        ]);

        $this->campaign($this->makeShort(), [
            'short_advertiser_id' => $house->id,
            'objective' => ShortPromotion::OBJECTIVE_HOUSE,
            'bid_cents' => 0,
        ]);

        $this->assertNotNull(app(ShortPromotionService::class)->pick(null));
        $this->assertSame(0, $house->fresh()->credit_cents);
    }

    public function test_a_blocked_advertiser_is_not_served(): void
    {
        $advertiser = ShortAdvertiser::create([
            'name' => 'Blocked',
            'type' => ShortAdvertiser::TYPE_EXTERNAL,
            'status' => ShortAdvertiser::STATUS_BLOCKED,
        ]);
        $advertiser->topUp(100_000);

        $this->campaign($this->makeShort(), ['short_advertiser_id' => $advertiser->id]);

        $this->assertNull(app(ShortPromotionService::class)->pick(null));
    }

    public function test_paid_ads_win_the_slot_and_house_ads_only_fill_what_is_left(): void
    {
        $house = ShortAdvertiser::create(['name' => 'House', 'type' => ShortAdvertiser::TYPE_HOUSE]);
        $paidAdvertiser = ShortAdvertiser::create(['name' => 'Paying', 'type' => ShortAdvertiser::TYPE_EXTERNAL]);
        $paidAdvertiser->topUp(100_000);

        $houseShort = $this->makeShort(['title' => 'house']);
        $paidShort = $this->makeShort(['title' => 'paid']);

        $this->campaign($houseShort, [
            'short_advertiser_id' => $house->id,
            'objective' => ShortPromotion::OBJECTIVE_HOUSE,
            'bid_cents' => 0,
        ]);
        $paid = $this->campaign($paidShort, ['short_advertiser_id' => $paidAdvertiser->id, 'bid_cents' => 4000]);

        $this->assertSame($paid->id, app(ShortPromotionService::class)->pick(null)?->id);

        // With the paid flight gone, the house ad takes the slot rather than
        // leaving it empty.
        $paid->update(['status' => ShortPromotion::STATUS_PAUSED]);

        $this->assertSame($houseShort->id, app(ShortPromotionService::class)->pick(null)?->short_id);
    }

    /* ---------------- targeting ---------------- */

    public function test_country_targeting_excludes_the_wrong_country_and_fails_closed_on_an_unknown_one(): void
    {
        $this->campaign($this->makeShort(), ['targeting' => ['geo' => ['RO']]]);

        $service = app(ShortPromotionService::class);

        $this->assertNotNull($service->pick(null, [], ['country' => 'ro']), 'case must not matter');
        $this->assertNull($service->pick(null, [], ['country' => 'DE']));
        $this->assertNull(
            $service->pick(null, [], []),
            'an unplaceable viewer must not be served a geo-targeted campaign',
        );
    }

    public function test_age_targeting_fails_closed_when_the_age_is_unknown(): void
    {
        $this->campaign($this->makeShort(), ['targeting' => ['age' => ['min' => 21]]]);

        $service = app(ShortPromotionService::class);

        $tooYoung = $this->customer(['birth_date' => now()->subYears(18)->toDateString()]);
        $oldEnough = $this->customer(['birth_date' => now()->subYears(30)->toDateString()]);
        $unknown = $this->customer(['birth_date' => null]);

        $this->assertNotNull($service->pick($oldEnough));
        $this->assertNull($service->pick($tooYoung));
        $this->assertNull($service->pick($unknown), 'age-gated advertising is regulated; "we did not know" is not a defence');
    }

    public function test_genre_targeting_fails_open_because_it_is_only_a_relevance_hint(): void
    {
        $short = $this->makeShort();

        DB::table('event_event_genre')->insert([
            'event_id' => $short->event_id,
            'event_genre_id' => 7,
        ]);

        $service = app(ShortPromotionService::class);

        $matching = $this->campaign($short, ['targeting' => ['genres' => [7]]]);
        $this->assertSame($matching->id, $service->pick(null)?->id);

        $matching->update(['targeting' => ['genres' => [99]]]);
        $this->assertNull($service->pick(null));

        // A brand ad has no event behind it, so it cannot be genre-matched at
        // all — failing that open keeps the gate a hint rather than a wall.
        $brandShort = $this->makeShort(['event_id' => null]);
        $this->campaign($brandShort, [
            'objective' => ShortPromotion::OBJECTIVE_BRAND,
            'targeting' => ['genres' => [99]],
        ]);

        $this->assertSame($brandShort->id, $service->pick(null)?->short_id);
    }

    /* ---------------- frequency capping ---------------- */

    public function test_an_anonymous_viewer_is_capped_by_session(): void
    {
        Cache::flush();

        $promotion = $this->campaign($this->makeShort());
        $service = app(ShortPromotionService::class);
        $context = ['session_id' => 'device-abc'];

        for ($i = 0; $i < (int) config('shorts.ads.frequency_cap', 3); $i++) {
            $this->assertNotNull($service->pick(null, [], $context));
            $service->chargeImpression($promotion, null, $context);
        }

        $this->assertNull(
            $service->pick(null, [], $context),
            'without a session cap one logged-out viewer would burn a whole budget',
        );

        // A different device is a different viewer.
        $this->assertNotNull($service->pick(null, [], ['session_id' => 'device-xyz']));
    }

    public function test_a_per_campaign_cap_overrides_the_platform_default(): void
    {
        Cache::flush();

        $promotion = $this->campaign($this->makeShort(), ['frequency_cap' => 1]);
        $service = app(ShortPromotionService::class);
        $context = ['session_id' => 'device-1'];

        $service->chargeImpression($promotion, null, $context);

        $this->assertNull($service->pick(null, [], $context));
    }

    /* ---------------- billing ---------------- */

    public function test_cpm_debits_the_advertiser_and_writes_a_ledger_row(): void
    {
        $advertiser = ShortAdvertiser::create(['name' => 'Brand', 'type' => ShortAdvertiser::TYPE_EXTERNAL]);
        $advertiser->topUp(10_000, 'invoice-1');

        // 5000 per mille => 5 per impression.
        $promotion = $this->campaign($this->makeShort(), [
            'short_advertiser_id' => $advertiser->id,
            'model' => ShortPromotion::MODEL_CPM,
            'bid_cents' => 5000,
        ]);

        app(ShortPromotionService::class)->chargeImpression($promotion, null);

        $this->assertSame(9_995, $advertiser->fresh()->credit_cents);
        $this->assertSame(5, (int) $promotion->fresh()->spent_cents);

        $charge = ShortAdvertiserTransaction::query()->where('type', 'charge')->first();
        $this->assertNotNull($charge);
        $this->assertSame(-5, $charge->amount_cents);
        $this->assertSame($promotion->id, $charge->short_promotion_id);
    }

    public function test_cpm_costs_nothing_on_a_click_and_cpc_nothing_on_an_impression(): void
    {
        $cpm = $this->campaign($this->makeShort(), ['model' => ShortPromotion::MODEL_CPM, 'bid_cents' => 5000]);
        $cpc = $this->campaign($this->makeShort(), ['model' => ShortPromotion::MODEL_CPC, 'bid_cents' => 300]);

        $this->assertSame(5, $cpm->impressionCost());
        $this->assertSame(0, $cpm->clickCost());
        $this->assertSame(0, $cpc->impressionCost());
        $this->assertSame(300, $cpc->clickCost());
    }

    public function test_a_click_is_only_billed_against_the_promotion_that_served_it(): void
    {
        $advertiser = ShortAdvertiser::create(['name' => 'Brand', 'type' => ShortAdvertiser::TYPE_EXTERNAL]);
        $advertiser->topUp(10_000);

        $short = $this->makeShort();
        $other = $this->makeShort();

        $promotion = $this->campaign($short, [
            'short_advertiser_id' => $advertiser->id,
            'model' => ShortPromotion::MODEL_CPC,
            'bid_cents' => 300,
        ]);

        $service = app(ShortPromotionService::class);

        // No promotion id: the viewer got here organically. Nobody pays.
        $this->assertNull($service->chargeClickFor($short, null, null));
        $this->assertSame(10_000, $advertiser->fresh()->credit_cents);

        // An id that belongs to a different short must not charge either —
        // otherwise a crafted request bills a competitor's budget.
        $this->assertNull($service->chargeClickFor($other, $promotion->id, null));
        $this->assertSame(10_000, $advertiser->fresh()->credit_cents);

        $this->assertNotNull($service->chargeClickFor($short, $promotion->id, null));
        $this->assertSame(9_700, $advertiser->fresh()->credit_cents);
    }

    public function test_an_impression_that_cannot_be_billed_is_logged_at_zero_rather_than_as_revenue(): void
    {
        $advertiser = ShortAdvertiser::create(['name' => 'Brand', 'type' => ShortAdvertiser::TYPE_EXTERNAL]);
        $advertiser->topUp(2);

        $promotion = $this->campaign($this->makeShort(), [
            'short_advertiser_id' => $advertiser->id,
            'bid_cents' => 5000, // 5 per impression, more than the balance
        ]);

        app(ShortPromotionService::class)->chargeImpression($promotion, null);

        $event = ShortPromotionEvent::query()->where('short_promotion_id', $promotion->id)->first();

        $this->assertNotNull($event, 'the impression happened, so it is recorded');
        $this->assertSame(0, (int) $event->charged_cents, 'but it is not booked as revenue we never collected');
        $this->assertSame(2, $advertiser->fresh()->credit_cents, 'and the balance is untouched');
    }

    public function test_the_budget_ends_the_flight_the_moment_it_is_exhausted(): void
    {
        $promotion = $this->campaign($this->makeShort(), [
            'model' => ShortPromotion::MODEL_CPC,
            'bid_cents' => 500,
            'budget_cents' => 500,
        ]);

        app(ShortPromotionService::class)->chargeClick($promotion, null);

        $this->assertSame(ShortPromotion::STATUS_ENDED, $promotion->fresh()->status);
    }

    public function test_the_ledger_sums_to_the_balance(): void
    {
        $advertiser = ShortAdvertiser::create(['name' => 'Brand', 'type' => ShortAdvertiser::TYPE_EXTERNAL]);
        $advertiser->topUp(1_000);

        $promotion = $this->campaign($this->makeShort(), [
            'short_advertiser_id' => $advertiser->id,
            'bid_cents' => 10_000, // 10 per impression
        ]);

        $service = app(ShortPromotionService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->chargeImpression($promotion, null, ['session_id' => "device-{$i}"]);
        }

        $ledger = (int) ShortAdvertiserTransaction::query()
            ->where('short_advertiser_id', $advertiser->id)
            ->sum('amount_cents');

        $this->assertSame($advertiser->fresh()->credit_cents, $ledger);
        $this->assertSame(950, $ledger);
    }

    /* ---------------- page layout ---------------- */

    public function test_a_page_never_carries_more_ads_than_configured(): void
    {
        Cache::flush();
        config()->set('shorts.ads.max_per_page', 2);

        foreach (range(1, 5) as $i) {
            $this->campaign(
                $this->makeShort(['title' => "paid {$i}", 'published_at' => now()->subYear()]),
                ['bid_cents' => 1000 * $i],
            );
        }

        foreach (range(1, 12) as $i) {
            $this->makeShort(['title' => "organic {$i}"]);
        }

        $result = app(ShortFeedService::class)->page(feed: 'for_you', limit: 10);

        $ads = collect($result['items'])->filter(fn (array $item) => isset($item['promoted']));

        $this->assertCount(2, $ads);
        $this->assertSame(
            $ads->pluck('id')->unique()->count(),
            $ads->count(),
            'the same short must not fill two slots on one page',
        );
    }

    public function test_a_short_page_still_gets_one_ad(): void
    {
        // A page shorter than the slot interval is common — the tail of a feed,
        // a narrow filter — and dropping the ad there zeroes out fill on exactly
        // those requests.
        $this->makeShort();
        $this->campaign($this->makeShort(['title' => 'paid']));

        $items = app(ShortPromotionService::class)->inject(
            app(ShortPayload::class)->collection(collect([Short::query()->first()])),
            null,
            app(ShortPayload::class),
        );

        $this->assertCount(2, $items);
    }

    /* ---------------- panels ---------------- */

    public function test_the_filament_resources_compile(): void
    {
        $this->assertNotEmpty(CoreShortPromotionResource::form(new Schema)->getComponents());
        $this->assertNotEmpty(TenantShortPromotionResource::form(new Schema)->getComponents());
        $this->assertNotEmpty(ShortAdvertiserResource::form(new Schema)->getComponents());

        $this->assertNotEmpty(CoreShortPromotionResource::table(Table::make($this->tableHost()))->getColumns());
        $this->assertNotEmpty(TenantShortPromotionResource::table(Table::make($this->tableHost()))->getColumns());
        $this->assertNotEmpty(ShortAdvertiserResource::table(Table::make($this->tableHost()))->getColumns());
    }

    public function test_a_tenant_campaign_is_stamped_pending_and_cannot_self_approve(): void
    {
        $data = TenantShortPromotionResource::stampOwnership([
            'short_id' => $this->makeShort()->id,
            'status' => ShortPromotion::STATUS_ACTIVE, // what a tampered payload would send
        ]);

        $this->assertSame(ShortPromotion::STATUS_PENDING, $data['status']);
        $this->assertArrayHasKey('short_advertiser_id', $data);
    }

    public function test_a_tenants_advertiser_row_is_created_once_and_reused(): void
    {
        $first = ShortAdvertiser::forTenant(42, 'Acme Events');
        $second = ShortAdvertiser::forTenant(42);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(ShortAdvertiser::TYPE_TENANT, $first->type);
    }

    /* ---------------- helpers ---------------- */

    /**
     * A funded, approved, in-flight campaign — the baseline every test varies
     * one thing away from.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function campaign(Short $short, array $overrides = []): ShortPromotion
    {
        $advertiser = null;

        if (! array_key_exists('short_advertiser_id', $overrides)) {
            $advertiser = ShortAdvertiser::create([
                'name' => 'Advertiser '.$short->id,
                'type' => ShortAdvertiser::TYPE_EXTERNAL,
                'status' => ShortAdvertiser::STATUS_ACTIVE,
            ]);
            $advertiser->topUp(1_000_000);
        }

        return ShortPromotion::create(array_merge([
            'short_id' => $short->id,
            'short_advertiser_id' => $advertiser?->id,
            'tenant_id' => 1,
            'model' => ShortPromotion::MODEL_CPM,
            'objective' => ShortPromotion::OBJECTIVE_EVENT,
            'bid_cents' => 5000,
            'budget_cents' => 1_000_000,
            'status' => ShortPromotion::STATUS_ACTIVE,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(30),
        ], $overrides));
    }

    /**
     * A published short with a real event behind it — genre targeting reads the
     * event's pivot, so a short with a null event_id would silently pass every
     * genre test for the wrong reason.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeShort(array $attributes = []): Short
    {
        if (! array_key_exists('event_id', $attributes)) {
            $attributes['event_id'] = DB::table('events')->insertGetId([
                'title' => 'Ads event',
                'slug' => 'ads-event-'.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return Short::create(array_merge([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function customer(array $attributes = []): MarketplaceCustomer
    {
        return MarketplaceCustomer::create(array_merge([
            'marketplace_client_id' => 1,
            'email' => 'ads-'.uniqid().'@example.test',
            'name' => 'Ads viewer',
        ], $attributes));
    }

    protected function tableHost(): Component&HasTable
    {
        return new class extends Component implements HasTable
        {
            use InteractsWithTable;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function render(): string
            {
                return '';
            }
        };
    }
}
