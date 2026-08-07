<?php

namespace Tests\Feature\Shorts;

use App\Filament\Resources\Shorts\ShortCollectionResource;
use App\Jobs\Shorts\CheckShortHealthJob;
use App\Models\Artist;
use App\Models\Short;
use App\Models\ShortCollection;
use App\Services\Shorts\ShortCollectionService;
use App\Services\Shorts\ShortFeedService;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

/**
 * B7 collections, B8 stories, and the health sweep that expires them.
 */
class ShortsCollectionsTest extends ShortsTestCase
{
    /* ---------------- B7 — collections ---------------- */

    public function test_a_collection_derives_its_slug_and_keeps_curated_order(): void
    {
        $collection = ShortCollection::create(['title' => 'Weekend în București']);

        $this->assertStringStartsWith('weekend-in-bucuresti-', $collection->slug);

        $first = $this->makeShort(['title' => 'first']);
        $second = $this->makeShort(['title' => 'second']);

        // Attached in reverse, ordered by the pivot.
        $collection->shorts()->attach($second->id, ['sort' => 1]);
        $collection->shorts()->attach($first->id, ['sort' => 0]);

        $result = app(ShortCollectionService::class)->show($collection->slug);

        $this->assertSame([$first->id, $second->id], array_column($result['items'], 'id'));
    }

    public function test_a_collection_only_shows_published_shorts(): void
    {
        $collection = ShortCollection::create(['title' => 'Mixed']);

        $published = $this->makeShort(['title' => 'live']);
        $draft = $this->makeShort(['title' => 'draft', 'status' => Short::STATUS_DRAFT]);

        $collection->shorts()->attach([$published->id, $draft->id]);

        $result = app(ShortCollectionService::class)->show($collection->slug);

        $this->assertCount(1, $result['items']);
        $this->assertSame($published->id, $result['items'][0]['id']);
    }

    public function test_inactive_and_foreign_collections_are_not_served(): void
    {
        ShortCollection::create(['title' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        ShortCollection::create(['title' => 'Theirs', 'slug' => 'theirs', 'marketplace_client_id' => 99]);
        ShortCollection::create(['title' => 'Editorial', 'slug' => 'editorial']);
        ShortCollection::create(['title' => 'Ours', 'slug' => 'ours', 'marketplace_client_id' => 7]);

        $service = app(ShortCollectionService::class);

        $slugs = array_column($service->index(clientId: 7), 'slug');

        $this->assertContains('ours', $slugs);
        // Editorial collections are global — every marketplace gets them.
        $this->assertContains('editorial', $slugs);
        $this->assertNotContains('theirs', $slugs);
        $this->assertNotContains('hidden', $slugs);

        $this->assertNull($service->show('theirs', clientId: 7));
        $this->assertNull($service->show('hidden', clientId: 7));
    }

    /* ---------------- B8 — stories ---------------- */

    public function test_stories_are_grouped_by_owner_and_only_while_live(): void
    {
        $artist = Artist::create(['name' => 'Story teller', 'slug' => 'story-teller']);

        $live = $this->makeShort([
            'title' => 'live story',
            'is_story' => true,
            'expires_at' => now()->addHours(20),
            'owner_type' => Artist::class,
            'owner_id' => $artist->id,
        ]);

        $alsoLive = $this->makeShort([
            'title' => 'second story',
            'is_story' => true,
            'expires_at' => now()->addHours(22),
            'owner_type' => Artist::class,
            'owner_id' => $artist->id,
        ]);

        $this->makeShort([
            'title' => 'expired story',
            'is_story' => true,
            'expires_at' => now()->subHour(),
            'owner_type' => Artist::class,
            'owner_id' => $artist->id,
        ]);

        // A story without an expiry is not a story.
        $this->makeShort(['title' => 'no expiry', 'is_story' => true]);

        $tray = app(ShortCollectionService::class)->stories();

        $this->assertCount(1, $tray, 'one entry per owner');
        $this->assertSame(2, $tray[0]['count']);
        $this->assertSame('artist', $tray[0]['owner']['type']);
        $this->assertSame(
            [$live->id, $alsoLive->id],
            array_column($tray[0]['items'], 'id'),
        );
    }

    public function test_stories_stay_out_of_the_main_feed(): void
    {
        $regular = $this->makeShort(['title' => 'regular']);

        $this->makeShort([
            'title' => 'story',
            'is_story' => true,
            'expires_at' => now()->addHours(12),
        ]);

        $page = app(ShortFeedService::class)->page();

        $this->assertCount(1, $page['items']);
        $this->assertSame($regular->id, $page['items'][0]['id']);
    }

    /* ---------------- health sweep ---------------- */

    public function test_health_sweep_archives_anything_past_its_window(): void
    {
        $expired = $this->makeShort(['title' => 'gone', 'expires_at' => now()->subMinute()]);
        $live = $this->makeShort(['title' => 'still here', 'expires_at' => now()->addDay()]);
        $forever = $this->makeShort(['title' => 'no expiry']);

        (new CheckShortHealthJob)->handle();

        $this->assertSame(Short::STATUS_ARCHIVED, $expired->fresh()->status);
        $this->assertSame(Short::STATUS_PUBLISHED, $live->fresh()->status);
        $this->assertSame(Short::STATUS_PUBLISHED, $forever->fresh()->status);
    }

    public function test_health_sweep_archives_a_deleted_youtube_video(): void
    {
        Http::fake([
            'i.ytimg.com/vi/gone/*' => Http::response('', 404),
            'i.ytimg.com/vi/alive/*' => Http::response('', 200),
        ]);

        $dead = $this->makeShort(['source' => 'youtube', 'source_video_id' => 'gone', 'ready' => false]);
        $alive = $this->makeShort(['source' => 'youtube', 'source_video_id' => 'alive', 'ready' => false]);

        (new CheckShortHealthJob)->handle();

        $this->assertSame(Short::STATUS_ARCHIVED, $dead->fresh()->status);
        $this->assertSame(Short::STATUS_PUBLISHED, $alive->fresh()->status);
    }

    public function test_a_network_blip_does_not_archive_a_healthy_short(): void
    {
        Http::fake(fn () => throw new \RuntimeException('connection refused'));

        $short = $this->makeShort(['source' => 'youtube', 'source_video_id' => 'abc', 'ready' => false]);

        (new CheckShortHealthJob)->handle();

        $this->assertSame(Short::STATUS_PUBLISHED, $short->fresh()->status);
    }

    /* ---------------- admin ---------------- */

    public function test_the_collection_resource_form_compiles(): void
    {
        $this->assertNotEmpty(ShortCollectionResource::form(new Schema)->getComponents());
        $this->assertSame(ShortCollection::class, ShortCollectionResource::getModel());
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
