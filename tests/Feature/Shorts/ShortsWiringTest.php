<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\GenerateBlurhashJob;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortCollection;
use App\Models\ShortReminder;
use App\Models\ShortStreak;
use App\Services\Shorts\ShortCollectionService;
use App\Services\Shorts\ShortFeedService;
use Illuminate\Support\Facades\Queue;

/**
 * Everything that was BUILT but not CONNECTED.
 *
 * The audit that produced this file found the same defect in four separate
 * places: a service written, tested and correct, with nothing calling it. A
 * green unit test on the service proves nothing about that — it is exactly what
 * every one of those features already had.
 *
 * So these tests deliberately assert from the OUTSIDE: what the feed actually
 * returns, what the queue actually receives, what a status change actually
 * causes. Add one here for anything whose value depends on being invoked.
 */
class ShortsWiringTest extends ShortsTestCase
{
    /* ---------------- D8: the cost guard must actually guard ---------------- */

    public function test_the_feed_carries_the_cost_guards_playback_decision(): void
    {
        $this->makeShort();

        $page = app(ShortFeedService::class)->page(feed: 'for_you');

        $this->assertArrayHasKey('playback', $page, 'the guard reached no client without this');
        $this->assertArrayHasKey('data_saver', $page['playback']);
        $this->assertArrayHasKey('max_height', $page['playback']);
    }

    public function test_crossing_the_bandwidth_threshold_drops_quality_for_everyone(): void
    {
        $this->makeShort();

        config()->set('shorts.player.data_saver_global', true);

        $page = app(ShortFeedService::class)->page(feed: 'for_you');

        $this->assertTrue($page['playback']['data_saver']);
        $this->assertSame(480, $page['playback']['max_height']);
        $this->assertSame(0, $page['playback']['prefetch_count'], 'prefetch is exactly what you stop paying for');
    }

    public function test_owner_pages_carry_the_decision_too(): void
    {
        // An organiser's own page is served by a different method; a guardrail
        // that only covers the main feed still lets the bill run.
        $short = $this->makeShort();
        config()->set('shorts.player.data_saver_global', true);

        $page = app(ShortFeedService::class)->forOwner($short->event);

        $this->assertTrue($page['playback']['data_saver']);
    }

    /* ---------------- D11: approving UGC must pay the author ---------------- */

    public function test_publishing_a_ugc_short_pays_its_author(): void
    {
        $author = $this->customer();

        $short = $this->makeShort([
            'status' => Short::STATUS_DRAFT,
            'published_at' => null,
            'is_ugc' => true,
            'author_marketplace_customer_id' => $author->id,
        ]);

        $short->update(['status' => Short::STATUS_PUBLISHED, 'published_at' => now()]);

        $streak = ShortStreak::query()->where('marketplace_customer_id', $author->id)->first();

        $this->assertNotNull($streak, 'approval is the moment the reward was promised');
        $this->assertSame(
            (int) config('shorts.gamification.ugc_points', 50),
            $streak->total_points,
        );
    }

    public function test_republishing_cannot_farm_the_ugc_reward(): void
    {
        $author = $this->customer();

        $short = $this->makeShort([
            'status' => Short::STATUS_DRAFT,
            'published_at' => null,
            'is_ugc' => true,
            'author_marketplace_customer_id' => $author->id,
        ]);

        $short->update(['status' => Short::STATUS_PUBLISHED, 'published_at' => now()]);
        $short->update(['status' => Short::STATUS_ARCHIVED]);
        $short->update(['status' => Short::STATUS_PUBLISHED]);

        $this->assertSame(
            (int) config('shorts.gamification.ugc_points', 50),
            ShortStreak::query()->where('marketplace_customer_id', $author->id)->value('total_points'),
        );
    }

    public function test_a_non_ugc_short_pays_nobody(): void
    {
        $short = $this->makeShort(['status' => Short::STATUS_DRAFT, 'published_at' => null]);

        $short->update(['status' => Short::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->assertSame(0, ShortStreak::query()->count());
    }

    /* ---------------- D9: every poster gets an LQIP ---------------- */

    public function test_a_natively_uploaded_short_gets_a_blurhash_too(): void
    {
        Queue::fake();

        // Previously the blurhash job was dispatched only from the ingest path,
        // so the case the placeholder exists for — a native upload with no
        // platform thumbnail — was the one case that never got one.
        Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'status' => Short::STATUS_DRAFT,
            'poster_path' => 'shorts/posters/1.jpg',
        ]);

        Queue::assertPushed(GenerateBlurhashJob::class);
    }

    public function test_a_short_that_gains_a_poster_later_gets_one_as_well(): void
    {
        $short = Short::create(['source' => Short::SOURCE_UPLOAD, 'status' => Short::STATUS_DRAFT]);

        Queue::fake();
        $short->update(['poster_path' => 'shorts/posters/late.jpg']);

        Queue::assertPushed(GenerateBlurhashJob::class);
    }

    public function test_an_unrelated_update_does_not_queue_a_blurhash(): void
    {
        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'status' => Short::STATUS_DRAFT,
            'poster_path' => 'shorts/posters/1.jpg',
        ]);

        Queue::fake();
        $short->update(['title' => 'Renamed']);

        Queue::assertNotPushed(GenerateBlurhashJob::class);
    }

    /* ---------------- D2: the reminder must survive the session ---------------- */

    public function test_the_feed_tells_the_client_a_reminder_is_already_set(): void
    {
        $customer = $this->customer();
        $short = $this->makeShort();

        ShortReminder::create([
            'marketplace_customer_id' => $customer->id,
            'short_id' => $short->id,
            'event_id' => $short->event_id,
        ]);

        $page = app(ShortFeedService::class)->page(feed: 'for_you', customer: $customer);
        $item = collect($page['items'])->firstWhere('id', $short->id);

        $this->assertTrue(
            $item['viewer']['reminded'],
            'without this the button forgets every time the app is reopened',
        );
    }

    public function test_a_short_with_no_reminder_says_so(): void
    {
        $customer = $this->customer();
        $short = $this->makeShort();

        $page = app(ShortFeedService::class)->page(feed: 'for_you', customer: $customer);

        $this->assertFalse(collect($page['items'])->firstWhere('id', $short->id)['viewer']['reminded']);
    }

    /* ---------------- B7: a rail has to be shareable ---------------- */

    public function test_a_collection_carries_a_deep_link(): void
    {
        ShortCollection::create([
            'slug' => 'weekend',
            'title' => 'Weekend in Bucharest',
            'is_active' => true,
        ]);

        $rails = app(ShortCollectionService::class)->index();

        $this->assertNotEmpty($rails);
        $this->assertStringContainsString('shorts/collection/weekend', $rails[0]['deep_link']);
    }

    /* ---------------- helpers ---------------- */

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeShort(array $attributes = []): Short
    {
        if (! array_key_exists('event_id', $attributes)) {
            $attributes['event_id'] = Event::create([
                'slug' => 'wire-'.uniqid(),
                'title' => 'Wiring event',
            ])->id;
        }

        return Short::create(array_merge([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }

    protected function customer(): MarketplaceCustomer
    {
        return MarketplaceCustomer::create([
            'email' => 'wiring-'.uniqid().'@example.test',
            'first_name' => 'Wiring',
            'status' => 'active',
        ]);
    }
}
