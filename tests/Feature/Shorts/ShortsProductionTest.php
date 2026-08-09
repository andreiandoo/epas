<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\AggregateShortAnalyticsJob;
use App\Jobs\Shorts\GenerateCaptionsJob;
use App\Jobs\Shorts\GenerateShortFromEventJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\Short;
use App\Models\ShortCaption;
use App\Models\ShortEvent;
use App\Services\Shorts\ShortPayload;
use App\Services\Video\NullVideoProvider;
use App\Services\Video\NullVideoRenderer;
use App\Services\Video\ShotstackRenderer;
use App\Services\Video\VideoRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * B3 auto-generation, B6 captions, B5 organiser analytics.
 */
class ShortsProductionTest extends ShortsTestCase
{
    /* ---------------- B3 — auto-generation ---------------- */

    public function test_without_a_renderer_generation_produces_a_playable_poster_short(): void
    {
        $event = Event::create(['slug' => 'fest', 'title' => 'Fest 2026']);
        $event->forceFill(['poster_url' => 'https://cdn.test/poster.jpg'])->save();

        (new GenerateShortFromEventJob($event->id))->handle(new NullVideoRenderer);

        $short = Short::query()->where('event_id', $event->id)->firstOrFail();

        $this->assertTrue($short->is_generated);
        // No video asset, so nothing has to transcode — the still is playable now.
        $this->assertTrue($short->ready);
        $this->assertSame('https://cdn.test/poster.jpg', $short->poster_path);
        $this->assertSame('buy_tickets', $short->cta_type);

        // Published, not draft. The picture is the organiser's own artwork,
        // already shown publicly on the event page — putting it in the feed adds
        // no moderation surface. Leaving thousands of these as drafts nobody
        // will ever publish by hand is the same as not shipping the feature.
        $this->assertSame(Short::STATUS_PUBLISHED, $short->status);
        $this->assertNotNull($short->published_at);
    }

    public function test_generation_can_be_routed_through_review_instead(): void
    {
        config()->set('shorts.autogen.publish', false);

        $event = Event::create(['slug' => 'fest-review', 'title' => 'Fest under review']);
        $event->forceFill(['poster_url' => 'https://cdn.test/poster.jpg'])->save();

        (new GenerateShortFromEventJob($event->id))->handle(new NullVideoRenderer);

        $short = Short::query()->where('event_id', $event->id)->firstOrFail();

        $this->assertSame(Short::STATUS_DRAFT, $short->status);
        $this->assertNull($short->published_at);
    }

    public function test_generation_records_the_render_job_when_a_renderer_exists(): void
    {
        Http::fake([
            'api.shotstack.io/*' => Http::response(['response' => ['id' => 'render-123']], 200),
        ]);

        $event = Event::create(['slug' => 'fest2', 'title' => 'Fest']);
        $event->forceFill(['poster_url' => 'https://cdn.test/a.jpg'])->save();

        $renderer = new ShotstackRenderer(apiKey: 'k', environment: 'stage', webhookSecret: 's');

        (new GenerateShortFromEventJob($event->id))->handle($renderer);

        $short = Short::query()->where('event_id', $event->id)->firstOrFail();

        $this->assertSame('render-123', $short->render_job_id);
        // Still not ready: the asset does not exist until the render finishes.
        $this->assertFalse($short->ready);
    }

    public function test_a_failing_renderer_falls_back_to_the_poster_short(): void
    {
        Http::fake(['api.shotstack.io/*' => Http::response([], 500)]);

        $event = Event::create(['slug' => 'fest3', 'title' => 'Fest']);
        $event->forceFill(['poster_url' => 'https://cdn.test/a.jpg'])->save();

        (new GenerateShortFromEventJob($event->id))
            ->handle(new ShotstackRenderer(apiKey: 'k', environment: 'stage'));

        $short = Short::query()->where('event_id', $event->id)->firstOrFail();

        // A render outage must not leave an unplayable short in the feed queue.
        $this->assertTrue($short->ready);
    }

    public function test_generation_skips_an_event_that_already_has_a_short(): void
    {
        $event = Event::create(['slug' => 'fest4', 'title' => 'Fest']);
        $event->forceFill(['poster_url' => 'https://cdn.test/a.jpg'])->save();

        Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'event_id' => $event->id,
            'status' => Short::STATUS_PUBLISHED,
        ]);

        (new GenerateShortFromEventJob($event->id))->handle(new NullVideoRenderer);

        $this->assertSame(1, Short::query()->where('event_id', $event->id)->count());
    }

    public function test_generation_does_nothing_without_images(): void
    {
        $event = Event::create(['slug' => 'fest5', 'title' => 'Fest']);

        (new GenerateShortFromEventJob($event->id))->handle(new NullVideoRenderer);

        $this->assertSame(0, Short::query()->where('event_id', $event->id)->count());
    }

    public function test_the_container_falls_back_to_the_null_renderer(): void
    {
        config(['services.render.driver' => 'shotstack', 'services.render.api_key' => '']);

        app()->forgetInstance(VideoRenderer::class);

        $this->assertInstanceOf(NullVideoRenderer::class, app(VideoRenderer::class));
    }

    /* ---------------- B6 — captions ---------------- */

    public function test_captions_are_skipped_for_a_short_whose_asset_is_not_ready(): void
    {
        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'provider_asset_id' => 'guid-1',
            'ready' => false,
            'status' => Short::STATUS_DRAFT,
        ]);

        (new GenerateCaptionsJob($short->id))->handle(new NullVideoProvider);

        $this->assertSame(0, ShortCaption::query()->count());
    }

    public function test_captions_are_never_generated_twice_for_a_language(): void
    {
        Storage::fake('public');

        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'provider_asset_id' => 'guid-2',
            'ready' => true,
            'language' => 'ro',
            'status' => Short::STATUS_PUBLISHED,
        ]);

        ShortCaption::create(['short_id' => $short->id, 'language' => 'ro', 'vtt_path' => 'existing.vtt']);

        (new GenerateCaptionsJob($short->id))->handle(new NullVideoProvider);

        $this->assertSame(1, ShortCaption::query()->count());
        $this->assertSame('existing.vtt', ShortCaption::query()->firstOrFail()->vtt_path);
    }

    public function test_caption_tracks_reach_the_feed_payload(): void
    {
        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        ShortCaption::create(['short_id' => $short->id, 'language' => 'ro', 'vtt_path' => 'shorts/captions/1-ro.vtt']);

        $item = app(ShortPayload::class)->one($short->load('captions'));

        $this->assertCount(1, $item['captions']);
        $this->assertSame('ro', $item['captions'][0]['language']);
        $this->assertStringContainsString('1-ro.vtt', $item['captions'][0]['url']);
    }

    public function test_the_payload_omits_captions_that_were_not_eager_loaded(): void
    {
        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
        ]);

        ShortCaption::create(['short_id' => $short->id, 'language' => 'ro', 'vtt_path' => 'x.vtt']);

        // Not loaded → empty, rather than an N+1 hiding inside the serialiser.
        $this->assertSame([], app(ShortPayload::class)->one($short)['captions']);
    }

    /* ---------------- B5 — organiser analytics ---------------- */

    public function test_daily_analytics_rolls_telemetry_and_conversions_together(): void
    {
        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'ready' => true,
            'status' => Short::STATUS_PUBLISHED,
        ]);

        $date = now()->subDay();

        foreach (['impression', 'impression', 'view', 'complete', 'cta_click'] as $type) {
            ShortEvent::create([
                'short_id' => $short->id,
                'marketplace_customer_id' => 1,
                'type' => $type,
                'watch_ratio' => $type === 'view' ? 0.8 : null,
                'created_at' => $date,
            ]);
        }

        $order = Order::create([
            'source_short_id' => $short->id,
            'status' => 'paid',
            'total' => 120,
            'currency' => 'RON',
        ]);

        // The observer stamps attribution at payment time; backdate it so the
        // order belongs to the day being rolled up.
        $order->forceFill(['short_attributed_at' => $date])->saveQuietly();

        (new AggregateShortAnalyticsJob($date->toDateString()))->handle();

        $row = DB::table('short_analytics_daily')
            ->where('short_id', $short->id)
            ->firstOrFail();

        $this->assertSame(2, (int) $row->impressions);
        $this->assertSame(1, (int) $row->views);
        $this->assertSame(1, (int) $row->completions);
        $this->assertSame(1, (int) $row->cta_clicks);
        $this->assertSame(1, (int) $row->unique_viewers);
        $this->assertSame(1, (int) $row->conversions);
        $this->assertSame(12000, (int) $row->revenue_cents);
    }

    public function test_rebuilding_a_day_replaces_rather_than_duplicates(): void
    {
        $short = Short::create(['source' => Short::SOURCE_UPLOAD, 'status' => Short::STATUS_PUBLISHED]);
        $date = now()->subDay();

        ShortEvent::create(['short_id' => $short->id, 'type' => 'view', 'created_at' => $date]);

        $job = new AggregateShortAnalyticsJob($date->toDateString());
        $job->handle();
        $job->handle();

        $this->assertSame(1, DB::table('short_analytics_daily')
            ->where('short_id', $short->id)
            ->count());
    }

    public function test_shotstack_webhook_states_map_correctly(): void
    {
        $renderer = new ShotstackRenderer(apiKey: 'k', environment: 'stage', webhookSecret: 'hook');

        $this->assertTrue($renderer->verifyWebhook(Request::create('/w?secret=hook', 'POST')));
        $this->assertFalse($renderer->verifyWebhook(Request::create('/w?secret=nope', 'POST')));

        $done = $renderer->parseWebhook(Request::create('/w', 'POST', [
            'id' => 'r1', 'status' => 'done', 'url' => 'https://cdn/out.mp4',
        ]));

        $this->assertSame('ready', $done['state']);
        $this->assertSame('https://cdn/out.mp4', $done['url']);
        $this->assertSame('failed', $renderer->parseWebhook(
            Request::create('/w', 'POST', ['id' => 'r1', 'status' => 'failed'])
        )['state']);
    }
}
