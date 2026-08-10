<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Shorts\GenerateShortFromOwnerJob;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use App\Services\Shorts\ShortAutoGenerator;
use App\Services\Shorts\ShortFeedRanker;
use App\Services\Video\NullVideoRenderer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Automatic generation across the whole catalogue (B3).
 *
 * The generator itself has existed since phase 8, but nothing dispatched it —
 * no schedule entry, no observer, no command — so in practice not a single
 * short was ever generated, and only events were covered at all. These tests
 * pin down both halves: that all three owner kinds produce a short from the
 * pictures they already have, and that something actually triggers it.
 */
class ShortsAutoGenerationTest extends ShortsTestCase
{
    /* ---------------- what gets built ---------------- */

    public function test_an_event_without_video_gets_a_short_from_its_poster(): void
    {
        $event = $this->event(['title' => 'Untold 2026', 'poster_url' => 'https://cdn.test/untold.jpg']);

        $short = $this->generator()->generate($event);

        $this->assertNotNull($short);
        $this->assertTrue($short->is_generated);
        $this->assertTrue($short->ready, 'a still needs no transcode — it is playable immediately');
        $this->assertSame('https://cdn.test/untold.jpg', $short->poster_path);
        $this->assertSame('Untold 2026', $short->title);
        $this->assertSame($event->id, $short->event_id);
        $this->assertSame('buy_tickets', $short->cta_type);
    }

    public function test_the_poster_wins_but_a_hero_image_or_gallery_will_do(): void
    {
        $noPoster = $this->event(['hero_image_url' => 'https://cdn.test/hero.jpg']);
        $this->assertSame('https://cdn.test/hero.jpg', $this->generator()->generate($noPoster)?->poster_path);

        $galleryOnly = $this->event(['gallery' => ['https://cdn.test/g1.jpg', 'https://cdn.test/g2.jpg']]);
        $this->assertSame('https://cdn.test/g1.jpg', $this->generator()->generate($galleryOnly)?->poster_path);

        // Deployments differ on whether gallery holds bare URLs or objects.
        $objects = $this->event(['gallery' => [['url' => 'https://cdn.test/o1.jpg']]]);
        $this->assertSame('https://cdn.test/o1.jpg', $this->generator()->generate($objects)?->poster_path);
    }

    public function test_an_event_with_no_images_at_all_produces_nothing(): void
    {
        $bare = $this->event(['title' => 'No artwork']);

        $this->assertNull($this->generator()->generate($bare));
        $this->assertSame(0, Short::query()->count(), 'a short with no picture is a black rectangle');
    }

    public function test_an_artist_gets_a_short_pointing_at_their_next_gig(): void
    {
        $artist = Artist::create(['name' => 'Subcarpati', 'slug' => 'subcarpati']);
        $artist->forceFill(['portrait_url' => 'https://cdn.test/artist.jpg'])->save();

        $gig = $this->event(['title' => 'Subcarpati live', 'event_date' => now()->addMonth()->toDateString()]);
        DB::table('event_artist')->insert(['event_id' => $gig->id, 'artist_id' => $artist->id]);

        $short = $this->generator()->generate($artist);

        $this->assertNotNull($short);
        $this->assertSame(Artist::class, $short->owner_type);
        $this->assertSame('https://cdn.test/artist.jpg', $short->poster_path);
        $this->assertSame($gig->id, $short->event_id, 'the gig is the whole reason the short exists');
        $this->assertSame('buy_tickets', $short->cta_type);
    }

    public function test_an_artist_with_no_gig_gets_a_profile_cta_not_a_dead_buy_button(): void
    {
        $artist = Artist::create(['name' => 'Nobody Touring', 'slug' => 'nobody-touring']);
        $artist->forceFill(['main_image_url' => 'https://cdn.test/n.jpg'])->save();

        $short = $this->generator()->generate($artist);

        $this->assertNotNull($short);
        $this->assertNull($short->event_id);
        $this->assertSame('open_artist', $short->cta_type, 'a buy button with nothing to sell is a dead end');
    }

    public function test_a_venue_gets_a_short_from_its_image(): void
    {
        $venue = Venue::create(['name' => 'Arenele Romane', 'slug' => 'arenele-romane', 'city' => 'București']);
        $venue->forceFill(['image_url' => 'https://cdn.test/venue.jpg'])->save();

        $short = $this->generator()->generate($venue);

        $this->assertNotNull($short);
        $this->assertSame(Venue::class, $short->owner_type);
        $this->assertSame('https://cdn.test/venue.jpg', $short->poster_path);
        $this->assertSame('open_event', $short->cta_type);
    }

    /* ---------------- not stepping on real content ---------------- */

    public function test_an_owner_that_already_has_a_short_is_left_alone(): void
    {
        $event = $this->event(['poster_url' => 'https://cdn.test/p.jpg']);

        $this->assertNotNull($this->generator()->generate($event));
        $this->assertNull($this->generator()->generate($event), 'generation must be idempotent — the sweep re-runs nightly');
        $this->assertSame(1, Short::query()->where('event_id', $event->id)->count());
    }

    public function test_a_real_uploaded_video_stops_generation_for_that_event(): void
    {
        $event = $this->event(['poster_url' => 'https://cdn.test/p.jpg']);

        // Somebody uploaded a proper vertical clip. A generated poster short
        // would compete with it for the same event.
        Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'event_id' => $event->id,
            'status' => Short::STATUS_PUBLISHED,
            'ready' => true,
            'hls_url' => 'https://cdn.test/real.m3u8',
        ]);

        $this->assertNull($this->generator()->generate($event));
    }

    /* ---------------- the ranker ---------------- */

    public function test_a_real_video_outranks_a_generated_poster_all_else_equal(): void
    {
        $poster = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'ready' => true,
            'is_generated' => true,
            'poster_path' => 'https://cdn.test/p.jpg',
        ]);

        $video = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'ready' => true,
            'hls_url' => 'https://cdn.test/real.m3u8',
        ]);

        $ranked = app(ShortFeedRanker::class)->rank(Short::query()->get(), null);

        $this->assertSame($video->id, $ranked->first()->id);
        $this->assertLessThan(
            0,
            $ranked->firstWhere('id', $poster->id)->getAttribute('feed_score_parts')['generated'],
        );
    }

    public function test_a_generated_short_stops_being_penalised_once_it_has_real_video(): void
    {
        $rendered = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'status' => Short::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'ready' => true,
            'is_generated' => true,
            'hls_url' => 'https://cdn.test/rendered.m3u8',
        ]);

        $ranked = app(ShortFeedRanker::class)->rank(Short::query()->get(), null);

        $this->assertSame(
            0.0,
            (float) $ranked->firstWhere('id', $rendered->id)->getAttribute('feed_score_parts')['generated'],
        );
    }

    /* ---------------- something actually triggers it ---------------- */

    public function test_the_sweep_queues_a_job_for_every_uncovered_owner(): void
    {
        Queue::fake();

        $this->event(['poster_url' => 'https://cdn.test/a.jpg', 'event_date' => now()->addWeek()->toDateString()]);

        $artist = Artist::create(['name' => 'Touring', 'slug' => 'touring']);
        $artist->forceFill(['main_image_url' => 'https://cdn.test/b.jpg'])->save();
        $gig = $this->event(['event_date' => now()->addWeek()->toDateString(), 'poster_url' => 'https://cdn.test/c.jpg']);
        DB::table('event_artist')->insert(['event_id' => $gig->id, 'artist_id' => $artist->id]);

        $venue = Venue::create(['name' => 'Hall', 'slug' => 'hall']);
        $venue->forceFill(['image_url' => 'https://cdn.test/d.jpg'])->save();

        Artisan::call('shorts:generate');

        Queue::assertPushed(GenerateShortFromOwnerJob::class, 4); // 2 events + 1 artist + 1 venue
    }

    public function test_the_sweep_skips_events_outside_the_horizon(): void
    {
        Queue::fake();

        // Last year's poster in an infinite feed makes the whole surface look
        // abandoned, and none of those tickets are for sale.
        $this->event(['poster_url' => 'https://cdn.test/old.jpg', 'event_date' => now()->subYear()->toDateString()]);
        $this->event(['poster_url' => 'https://cdn.test/far.jpg', 'event_date' => now()->addYears(2)->toDateString()]);

        Artisan::call('shorts:generate', ['--type' => ['event']]);

        Queue::assertNothingPushed();
    }

    public function test_the_sweep_skips_owners_with_no_pictures(): void
    {
        Queue::fake();

        $this->event(['title' => 'No art', 'event_date' => now()->addWeek()->toDateString()]);
        Venue::create(['name' => 'Pictureless', 'slug' => 'pictureless']);

        Artisan::call('shorts:generate');

        Queue::assertNothingPushed();
    }

    public function test_the_batch_limit_caps_a_first_run_over_a_large_catalogue(): void
    {
        Queue::fake();

        foreach (range(1, 6) as $i) {
            $this->event([
                'poster_url' => "https://cdn.test/{$i}.jpg",
                'event_date' => now()->addDays($i)->toDateString(),
            ]);
        }

        Artisan::call('shorts:generate', ['--type' => ['event'], '--limit' => 2]);

        Queue::assertPushed(GenerateShortFromOwnerJob::class, 2);
    }

    public function test_a_dry_run_queues_nothing(): void
    {
        Queue::fake();

        $this->event(['poster_url' => 'https://cdn.test/a.jpg', 'event_date' => now()->addWeek()->toDateString()]);

        Artisan::call('shorts:generate', ['--dry-run' => true]);

        Queue::assertNothingPushed();
    }

    public function test_generation_can_be_switched_off_entirely(): void
    {
        Queue::fake();
        config()->set('shorts.autogen.enabled', false);

        $this->event(['poster_url' => 'https://cdn.test/a.jpg', 'event_date' => now()->addWeek()->toDateString()]);

        Artisan::call('shorts:generate');

        Queue::assertNothingPushed();
    }

    public function test_the_job_resolves_any_owner_type(): void
    {
        $venue = Venue::create(['name' => 'Club', 'slug' => 'club']);
        $venue->forceFill(['image_url' => 'https://cdn.test/club.jpg'])->save();

        (new GenerateShortFromOwnerJob(Venue::class, $venue->id))->handle($this->generator());

        $this->assertSame(1, Short::query()->where('owner_type', Venue::class)->count());
    }

    public function test_an_unknown_owner_type_is_ignored_rather_than_fatal(): void
    {
        (new GenerateShortFromOwnerJob('App\\Models\\NotAThing', 1))->handle($this->generator());

        $this->assertSame(0, Short::query()->count());
    }

    /* ---------------- helpers ---------------- */

    protected function generator(): ShortAutoGenerator
    {
        return new ShortAutoGenerator(new NullVideoRenderer);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function event(array $attributes = []): Event
    {
        $event = Event::create([
            'slug' => 'ev-'.uniqid(),
            'title' => $attributes['title'] ?? 'Event',
        ]);

        $event->forceFill($attributes + ['event_date' => now()->addWeek()->toDateString()])->save();

        return $event->refresh();
    }
}
