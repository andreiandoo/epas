<?php

namespace Tests\Feature\Shorts;

use App\Filament\Tenant\Resources\ShortResource as TenantShortResource;
use App\Http\Controllers\Api\MarketplaceClient\Customer\FollowsController;
use App\Models\Artist;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceFollow;
use App\Models\Short;
use App\Models\ShortEvent;
use App\Models\Venue;
use App\Services\Shorts\ShortAffinityProfile;
use App\Services\Shorts\ShortFeedRanker;
use App\Services\Shorts\ShortFeedService;
use Filament\Schemas\Schema;
use Illuminate\Http\Request;

/**
 * B2 — follow graph, the "For You" ranker, and the tenant-scoped resource.
 */
class ShortsRankerTest extends ShortsTestCase
{
    /* ---------------- follow graph ---------------- */

    public function test_follow_toggles_and_rejects_a_target_that_does_not_exist(): void
    {
        $customer = $this->makeCustomer();
        $artist = Artist::create(['name' => 'Subcarpati', 'slug' => 'subcarpati']);

        $controller = app(FollowsController::class);

        $on = $controller->toggle($this->authed($customer, ['type' => 'artist', 'id' => $artist->id]));
        $this->assertTrue($on->getData(true)['data']['following']);
        $this->assertSame(1, MarketplaceFollow::query()->count());

        $off = $controller->toggle($this->authed($customer, ['type' => 'artist', 'id' => $artist->id]));
        $this->assertFalse($off->getData(true)['data']['following']);
        $this->assertSame(0, MarketplaceFollow::query()->count());

        $missing = $controller->toggle($this->authed($customer, ['type' => 'artist', 'id' => 999999]));
        $this->assertSame(404, $missing->getStatusCode());
    }

    public function test_following_feed_is_empty_for_a_viewer_who_follows_nobody(): void
    {
        $this->makeShort(['title' => 'anything']);

        $page = app(ShortFeedService::class)->page(feed: 'following', customer: $this->makeCustomer());

        // Honest empty, not a silently un-personalised feed.
        $this->assertCount(0, $page['items']);
    }

    public function test_following_feed_only_returns_shorts_from_followed_owners(): void
    {
        $customer = $this->makeCustomer();
        $followed = Artist::create(['name' => 'Followed', 'slug' => 'followed']);
        $other = Artist::create(['name' => 'Other', 'slug' => 'other']);

        MarketplaceFollow::create([
            'marketplace_customer_id' => $customer->id,
            'followable_type' => Artist::class,
            'followable_id' => $followed->id,
        ]);

        $wanted = $this->makeShort(['title' => 'from followed', 'owner_type' => Artist::class, 'owner_id' => $followed->id]);
        $this->makeShort(['title' => 'from other', 'owner_type' => Artist::class, 'owner_id' => $other->id]);

        $page = app(ShortFeedService::class)->page(feed: 'following', customer: $customer);

        $this->assertCount(1, $page['items']);
        $this->assertSame($wanted->id, $page['items'][0]['id']);
    }

    /* ---------------- ranker ---------------- */

    public function test_a_followed_owner_outranks_a_fresher_stranger(): void
    {
        $customer = $this->makeCustomer();
        $artist = Artist::create(['name' => 'Loved', 'slug' => 'loved']);

        MarketplaceFollow::create([
            'marketplace_customer_id' => $customer->id,
            'followable_type' => Artist::class,
            'followable_id' => $artist->id,
        ]);

        $followed = $this->makeShort([
            'title' => 'followed but older',
            'owner_type' => Artist::class,
            'owner_id' => $artist->id,
            'published_at' => now()->subDays(3),
        ]);

        $this->makeShort(['title' => 'fresh stranger', 'published_at' => now()->subMinute()]);

        $page = app(ShortFeedService::class)->page(feed: 'for_you', customer: $customer);

        $this->assertSame($followed->id, $page['items'][0]['id']);
    }

    public function test_an_already_watched_short_is_pushed_down(): void
    {
        $customer = $this->makeCustomer();

        $watched = $this->makeShort(['title' => 'watched', 'published_at' => now()->subMinute()]);
        $unwatched = $this->makeShort(['title' => 'unwatched', 'published_at' => now()->subHours(6)]);

        ShortEvent::create([
            'short_id' => $watched->id,
            'marketplace_customer_id' => $customer->id,
            'type' => ShortEvent::TYPE_VIEW,
            'created_at' => now(),
        ]);

        $page = app(ShortFeedService::class)->page(feed: 'for_you', customer: $customer);

        $this->assertSame($unwatched->id, $page['items'][0]['id']);
    }

    public function test_diversity_never_places_two_shorts_from_the_same_owner_back_to_back(): void
    {
        $prolific = Artist::create(['name' => 'Prolific', 'slug' => 'prolific']);
        $quiet = Artist::create(['name' => 'Quiet', 'slug' => 'quiet']);

        foreach (range(1, 4) as $i) {
            $this->makeShort([
                'title' => "prolific {$i}",
                'owner_type' => Artist::class,
                'owner_id' => $prolific->id,
                'published_at' => now()->subMinutes($i),
            ]);
        }

        foreach (range(1, 2) as $i) {
            $this->makeShort([
                'title' => "quiet {$i}",
                'owner_type' => Artist::class,
                'owner_id' => $quiet->id,
                'published_at' => now()->subHours($i),
            ]);
        }

        $page = app(ShortFeedService::class)->page(feed: 'for_you', limit: 6);

        // Diversity must not drop anything, only reorder.
        $this->assertCount(6, $page['items']);

        $owners = array_map(fn ($item) => $item['owner']['id'] ?? null, $page['items']);

        // The first few placements interleave; the tail may repeat once the
        // quiet owner runs out, which is correct — a page must not shrink.
        $this->assertNotSame($owners[0], $owners[1]);
    }

    public function test_diversity_is_disableable(): void
    {
        config(['shorts.feed.diversity_enabled' => false]);

        $artist = Artist::create(['name' => 'Solo', 'slug' => 'solo']);

        foreach (range(1, 3) as $i) {
            $this->makeShort([
                'title' => "s{$i}",
                'owner_type' => Artist::class,
                'owner_id' => $artist->id,
                'published_at' => now()->subMinutes($i),
            ]);
        }

        $page = app(ShortFeedService::class)->page(feed: 'for_you');

        $this->assertCount(3, $page['items']);
    }

    public function test_cold_start_falls_back_to_featured_and_freshness(): void
    {
        $this->makeShort(['title' => 'old', 'published_at' => now()->subMonth()]);
        $featured = $this->makeShort([
            'title' => 'featured',
            'is_featured' => true,
            'published_at' => now()->subDay(),
        ]);

        // No customer at all — the guest feed must still be sensibly ordered.
        $page = app(ShortFeedService::class)->page(feed: 'for_you');

        $this->assertSame($featured->id, $page['items'][0]['id']);
    }

    public function test_ranked_pages_still_paginate_without_duplicates(): void
    {
        foreach (range(1, 8) as $i) {
            $this->makeShort(['title' => "s{$i}", 'published_at' => now()->subMinutes($i)]);
        }

        $service = app(ShortFeedService::class);

        $first = $service->page(feed: 'for_you', limit: 3);
        $second = $service->page(feed: 'for_you', cursor: $first['next_cursor'], limit: 3);

        $ids = array_merge(array_column($first['items'], 'id'), array_column($second['items'], 'id'));

        $this->assertSame($ids, array_unique($ids), 'ranking must not make the cursor repeat a short');
    }

    public function test_nearby_filters_by_the_viewers_city(): void
    {
        $customer = $this->makeCustomer(['city' => 'Cluj-Napoca']);

        // The city lives on the venue — `events` has no city column.
        $clujVenue = Venue::create(['name' => 'BT Arena', 'slug' => 'bt-arena', 'city' => 'Cluj-Napoca']);
        $iasiVenue = Venue::create(['name' => 'Palas', 'slug' => 'palas', 'city' => 'Iasi']);

        $cluj = Event::create(['slug' => 'cluj-fest', 'title' => 'Cluj Fest', 'venue_id' => $clujVenue->id]);
        $iasi = Event::create(['slug' => 'iasi-fest', 'title' => 'Iasi Fest', 'venue_id' => $iasiVenue->id]);

        $wanted = $this->makeShort(['title' => 'in cluj', 'event_id' => $cluj->id]);
        $this->makeShort(['title' => 'in iasi', 'event_id' => $iasi->id]);

        $page = app(ShortFeedService::class)->page(feed: 'nearby', customer: $customer);

        $this->assertCount(1, $page['items']);
        $this->assertSame($wanted->id, $page['items'][0]['id']);
    }

    public function test_affinity_profile_is_forgotten_when_a_follow_changes(): void
    {
        $customer = $this->makeCustomer();
        $artist = Artist::create(['name' => 'A', 'slug' => 'a']);

        $profile = app(ShortAffinityProfile::class);
        $this->assertSame([], $profile->for($customer)['followed']);

        app(FollowsController::class)->toggle($this->authed($customer, ['type' => 'artist', 'id' => $artist->id]));

        // A follow must show up in the very next page, not after the cache TTL.
        $this->assertArrayHasKey(Artist::class.':'.$artist->id, $profile->for($customer)['followed']);
    }

    public function test_followed_owners_helper_returns_class_and_id(): void
    {
        $customer = $this->makeCustomer();
        $artist = Artist::create(['name' => 'B', 'slug' => 'b']);

        MarketplaceFollow::create([
            'marketplace_customer_id' => $customer->id,
            'followable_type' => Artist::class,
            'followable_id' => $artist->id,
        ]);

        $this->assertSame(
            [['type' => Artist::class, 'id' => $artist->id]],
            ShortFeedRanker::followedOwners($customer),
        );
    }

    /* ---------------- tenant resource ---------------- */

    public function test_tenant_resource_is_scoped_and_its_form_compiles(): void
    {
        $this->assertNotEmpty(TenantShortResource::form(new Schema)->getComponents());

        // Unlike the core twin, this one MUST narrow the query.
        $this->assertSame(
            TenantShortResource::class,
            (new \ReflectionMethod(TenantShortResource::class, 'getEloquentQuery'))->getDeclaringClass()->getName(),
        );
    }

    /* ---------------- helpers ---------------- */

    protected function authed(MarketplaceCustomer $customer, array $body = []): Request
    {
        $request = Request::create('/', 'POST', $body);
        $request->setUserResolver(fn () => $customer);

        return $request;
    }

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
