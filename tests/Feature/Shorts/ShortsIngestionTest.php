<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\IngestShortJob;
use App\Models\Short;
use App\Services\Shorts\ShortIngestService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * External ingestion (docs/plans/social-video-ingestion.md) + YouTube seed (B4).
 */
class ShortsIngestionTest extends ShortsTestCase
{
    public function test_platform_detection_covers_every_supported_link_shape(): void
    {
        $service = app(ShortIngestService::class);

        $this->assertSame('youtube', $service->detectPlatform('https://www.youtube.com/shorts/abc123'));
        $this->assertSame('youtube', $service->detectPlatform('https://youtu.be/abc123'));
        $this->assertSame('youtube', $service->detectPlatform('https://www.youtube.com/watch?v=abc123'));
        $this->assertSame('tiktok', $service->detectPlatform('https://www.tiktok.com/@user/video/7123456789'));
        $this->assertSame('instagram', $service->detectPlatform('https://www.instagram.com/reel/Cx123/'));
        $this->assertSame('facebook', $service->detectPlatform('https://fb.watch/abc/'));
        $this->assertSame('unknown', $service->detectPlatform('https://vimeo.com/12345'));
    }

    public function test_youtube_ingest_returns_a_nocookie_embed_and_a_thumbnail(): void
    {
        config(['services.youtube.api_key' => 'test-key']);

        Http::fake([
            'youtube.googleapis.com/*' => Http::response([
                'items' => [[
                    'id' => 'abc123',
                    'snippet' => [
                        'title' => 'Live at UNTOLD',
                        'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vi/abc123/hq.jpg']],
                    ],
                    'contentDetails' => ['duration' => 'PT45S'],
                    'statistics' => [],
                ]],
            ], 200),
            'www.googleapis.com/*' => Http::response([
                'items' => [[
                    'id' => 'abc123',
                    'snippet' => [
                        'title' => 'Live at UNTOLD',
                        'thumbnails' => ['high' => ['url' => 'https://i.ytimg.com/vi/abc123/hq.jpg']],
                    ],
                    'contentDetails' => ['duration' => 'PT45S'],
                    'statistics' => [],
                ]],
            ], 200),
        ]);

        $payload = app(ShortIngestService::class)->ingest('https://www.youtube.com/shorts/abc123');

        $this->assertSame('youtube', $payload['source']);
        $this->assertSame('abc123', $payload['source_video_id']);
        $this->assertStringContainsString('youtube-nocookie.com/embed/abc123', $payload['embed_html']);
        $this->assertSame(45, $payload['duration']);
        $this->assertNotNull($payload['thumbnail_remote']);
    }

    public function test_tiktok_ingest_uses_the_public_oembed(): void
    {
        Http::fake([
            'www.tiktok.com/oembed*' => Http::response([
                'title' => 'A clip',
                'author_name' => 'someone',
                'thumbnail_url' => 'https://p16.tiktok.com/thumb.jpg',
                'html' => '<blockquote class="tiktok-embed"></blockquote>',
            ], 200),
        ]);

        $payload = app(ShortIngestService::class)->ingest('https://www.tiktok.com/@user/video/7123456789');

        $this->assertSame('tiktok', $payload['source']);
        $this->assertSame('7123456789', $payload['source_video_id']);
        $this->assertStringContainsString('tiktok-embed', $payload['embed_html']);
        $this->assertSame('someone', $payload['author']);
    }

    public function test_meta_platforms_report_unsupported_until_the_token_exists(): void
    {
        config(['services.meta.oembed_token' => null]);

        $service = app(ShortIngestService::class);

        // Better an honest null than a half-ingested short.
        $this->assertNull($service->ingest('https://www.instagram.com/reel/Cx123/'));
        $this->assertNull($service->ingest('https://www.facebook.com/watch/?v=123'));
    }

    public function test_unknown_platforms_are_refused(): void
    {
        $this->assertNull(app(ShortIngestService::class)->ingest('https://vimeo.com/12345'));
    }

    public function test_iso8601_durations_parse(): void
    {
        $service = app(ShortIngestService::class);

        $this->assertSame(45, $service->parseIso8601Duration('PT45S'));
        $this->assertSame(73, $service->parseIso8601Duration('PT1M13S'));
        $this->assertSame(3661, $service->parseIso8601Duration('PT1H1M1S'));
        $this->assertNull($service->parseIso8601Duration(null));
        $this->assertNull($service->parseIso8601Duration('nonsense'));
    }

    public function test_ingest_job_fills_the_short_and_caches_the_thumbnail_locally(): void
    {
        Storage::fake('public');

        Http::fake([
            'www.tiktok.com/oembed*' => Http::response([
                'title' => 'Fetched title',
                'thumbnail_url' => 'https://p16.tiktok.com/thumb.jpg',
                'html' => '<blockquote class="tiktok-embed"></blockquote>',
            ], 200),
            'p16.tiktok.com/*' => Http::response('binary-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $short = Short::create([
            'source' => 'tiktok',
            'source_url' => 'https://www.tiktok.com/@user/video/7123456789',
            'status' => Short::STATUS_DRAFT,
        ]);

        (new IngestShortJob($short->id))->handle(app(ShortIngestService::class));

        $short->refresh();

        $this->assertSame('Fetched title', $short->title);
        $this->assertSame('7123456789', $short->source_video_id);
        $this->assertStringContainsString('tiktok-embed', $short->embed_html);
        $this->assertNotNull($short->poster_path);

        // Cached locally, not hotlinked: platform CDN URLs expire.
        Storage::disk('public')->assertExists($short->poster_path);

        // Ingestion is not curation.
        $this->assertSame(Short::STATUS_DRAFT, $short->status);
    }

    public function test_ingest_job_never_overwrites_a_hand_written_title(): void
    {
        Storage::fake('public');

        Http::fake([
            'www.tiktok.com/oembed*' => Http::response([
                'title' => 'Platform title',
                'html' => '<blockquote class="tiktok-embed"></blockquote>',
            ], 200),
        ]);

        $short = Short::create([
            'source' => 'tiktok',
            'source_url' => 'https://www.tiktok.com/@user/video/7123456789',
            'title' => 'Curated title',
            'status' => Short::STATUS_DRAFT,
        ]);

        (new IngestShortJob($short->id))->handle(app(ShortIngestService::class));

        $this->assertSame('Curated title', $short->fresh()->title);
    }

    public function test_ingest_job_leaves_the_short_alone_when_the_link_yields_nothing(): void
    {
        $short = Short::create([
            'source' => 'youtube',
            'source_url' => 'https://vimeo.com/12345',
            'status' => Short::STATUS_DRAFT,
        ]);

        (new IngestShortJob($short->id))->handle(app(ShortIngestService::class));

        $this->assertNull($short->fresh()->embed_html);
    }
}
