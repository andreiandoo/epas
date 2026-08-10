<?php

namespace Tests\Feature\Shorts;

use App\Models\Short;
use App\Models\ShortEvent;
use App\Services\Shorts\ShortFeedCursor;
use App\Services\Shorts\ShortFeedService;
use App\Services\Shorts\ShortTelemetryService;

class ShortsFoundationTest extends ShortsTestCase
{
    public function test_published_scope_hides_drafts_expired_and_unready_uploads(): void
    {
        $visible = $this->makeShort(['title' => 'visible']);

        $this->makeShort(['title' => 'draft', 'status' => Short::STATUS_DRAFT]);
        $this->makeShort(['title' => 'future', 'published_at' => now()->addDay()]);
        $this->makeShort(['title' => 'expired', 'expires_at' => now()->subMinute()]);
        $this->makeShort(['title' => 'still encoding', 'ready' => false]);

        $titles = Short::query()->published()->pluck('title')->all();

        $this->assertSame(['visible'], $titles);
        $this->assertNotNull($visible->id);
    }

    public function test_external_shorts_do_not_require_a_ready_asset(): void
    {
        $this->makeShort([
            'title' => 'youtube short',
            'source' => 'youtube',
            'source_url' => 'https://youtube.com/shorts/abc',
            'ready' => false,
        ]);

        $this->assertSame(1, Short::query()->published()->count());
    }

    public function test_feed_paginates_by_cursor_without_repeating_or_skipping(): void
    {
        foreach (range(1, 7) as $i) {
            $this->makeShort([
                'title' => "short {$i}",
                'published_at' => now()->subMinutes(60 - $i),
            ]);
        }

        $service = app(ShortFeedService::class);

        $first = $service->page(limit: 3);
        $this->assertCount(3, $first['items']);
        $this->assertNotNull($first['next_cursor']);

        $second = $service->page(cursor: $first['next_cursor'], limit: 3);
        $this->assertCount(3, $second['items']);

        $third = $service->page(cursor: $second['next_cursor'], limit: 3);
        $this->assertCount(1, $third['items']);
        $this->assertNull($third['next_cursor']);

        $seen = array_merge(
            array_column($first['items'], 'id'),
            array_column($second['items'], 'id'),
            array_column($third['items'], 'id'),
        );

        $this->assertCount(7, $seen);
        $this->assertSame($seen, array_unique($seen), 'the cursor handed back a duplicate short');
    }

    public function test_featured_shorts_lead_the_feed_and_the_cursor_never_walks_back_into_them(): void
    {
        $this->makeShort(['title' => 'normal a', 'published_at' => now()->subMinute()]);
        $this->makeShort(['title' => 'normal b', 'published_at' => now()->subMinutes(2)]);
        $featured = $this->makeShort([
            'title' => 'featured',
            'is_featured' => true,
            'published_at' => now()->subHours(5),
        ]);

        $service = app(ShortFeedService::class);

        $first = $service->page(limit: 1);
        $this->assertSame($featured->id, $first['items'][0]['id']);

        $rest = $service->page(cursor: $first['next_cursor'], limit: 5);
        $this->assertCount(2, $rest['items']);
        $this->assertNotContains($featured->id, array_column($rest['items'], 'id'));
    }

    public function test_cursor_round_trips(): void
    {
        $cursor = new ShortFeedCursor('2026-08-07 12:00:00', 42, true);
        $decoded = ShortFeedCursor::decode($cursor->encode());

        $this->assertSame('2026-08-07 12:00:00', $decoded->publishedAt);
        $this->assertSame(42, $decoded->id);
        $this->assertTrue($decoded->featured);
        $this->assertNull(ShortFeedCursor::decode('not-a-cursor'));
    }

    public function test_telemetry_rejects_unknown_shorts_and_uncredible_views(): void
    {
        $short = $this->makeShort();
        $telemetry = app(ShortTelemetryService::class);

        $result = $telemetry->record([
            ['short_id' => $short->id, 'type' => 'impression', 'feed' => 'for_you'],
            ['short_id' => $short->id, 'type' => 'view', 'watch_ms' => 5000, 'watch_ratio' => 0.8],
            ['short_id' => $short->id, 'type' => 'view', 'watch_ms' => 200],       // too short
            ['short_id' => $short->id, 'type' => 'not_a_type'],                    // unknown type
            ['short_id' => 999999, 'type' => 'view', 'watch_ms' => 9000],          // unknown short
        ], sessionId: 'sess-1');

        $this->assertSame(2, $result['accepted']);
        $this->assertSame(3, $result['rejected']);
        $this->assertSame(1, ShortEvent::query()->where('type', 'view')->count());
    }

    public function test_telemetry_clamps_the_watch_ratio_and_drops_unknown_feed_names(): void
    {
        $short = $this->makeShort();

        app(ShortTelemetryService::class)->record([
            ['short_id' => $short->id, 'type' => 'complete', 'watch_ratio' => 1.7, 'feed' => 'made_up'],
        ]);

        $event = ShortEvent::query()->firstOrFail();

        $this->assertSame('1.000', (string) $event->watch_ratio);
        $this->assertNull($event->feed);
    }

    public function test_playback_url_falls_back_to_self_hosted_storage(): void
    {
        $short = $this->makeShort([
            'video_provider' => null,
            'provider_asset_id' => null,
            'disk' => 'public',
            'path' => 'shorts/clip.mp4',
        ]);

        $this->assertStringContainsString('shorts/clip.mp4', (string) $short->playback_url);
    }

    /**
     * A published, ready, native short unless the test says otherwise.
     */
    protected function makeShort(array $attributes = []): Short
    {
        return Short::create(array_merge([
            'source' => Short::SOURCE_UPLOAD,
            'video_provider' => 'bunny',
            'provider_asset_id' => 'guid-'.uniqid(),
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ], $attributes));
    }
}
