<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\ArtistGenre;
use App\Models\ArtistType;
use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ArtistDeepDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Tipuri & genuri minime
        $typeSolo = ArtistType::firstOrCreate(['slug' => 'solo-artist'], ['name' => 'Solo Artist']);
        $typeDj   = ArtistType::firstOrCreate(['slug' => 'dj'], ['name' => 'DJ']);
        $gHouse   = ArtistGenre::firstOrCreate(['slug' => 'house'], ['name' => 'House']);
        $gTechno  = ArtistGenre::firstOrCreate(['slug' => 'techno'], ['name' => 'Techno']);

        // Coloane existente în artists (pentru payload tolerant)
        $artistCols = Schema::getColumnListing('artists');

        $artistData = [
            'slug'    => 'aurora-nova',
            'name'    => 'Aurora Nova',
            // folosim bio_html dacă există, altfel bio
            (in_array('bio_html', $artistCols) ? 'bio_html' : (in_array('bio', $artistCols) ? 'bio' : 'bio_html'))
                => ['en' => '<p>Electronic music artist with a strong live presence.</p>'],

            'country' => 'Romania',
            'city'    => 'București',
            'email'   => 'booking@auroranova.example',
            'phone'   => '+40 712 345 678',
            'website' => 'https://auroranova.example',

            'facebook_url'  => 'https://www.facebook.com/auroranova',
            'instagram_url' => 'https://www.instagram.com/auroranova',
            'tiktok_url'    => 'https://www.tiktok.com/@auroranova',
            'youtube_url'   => 'https://www.youtube.com/@auroranova',
            'spotify_url'   => 'https://open.spotify.com/artist/0abc123xyz',

            'youtube_id' => 'UC-DEMO-123',
            'spotify_id' => '0abc123xyz',

            'followers_facebook'        => 12500,
            'followers_instagram'       => 39800,
            'followers_tiktok'          => 21000,
            'followers_youtube'         => 15300,
            'spotify_monthly_listeners' => 84210,

            'portrait_url'   => 'https://picsum.photos/seed/aurora/800/1000',
            'hero_image_url' => 'https://picsum.photos/seed/aurora2/1200/600',
            'logo_url'       => null,

            'youtube_videos' => [
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'https://www.youtube.com/embed/oHg5SJYRHA0',
            ],

            'is_active' => true,
        ];

        $artistFiltered = array_filter(
            $artistData,
            fn ($v, $k) => in_array($k, $artistCols, true),
            ARRAY_FILTER_USE_BOTH
        );

        $artist = Artist::updateOrCreate(
            ['slug' => 'aurora-nova'],
            $artistFiltered
        );

        // Tipuri & genuri
        $artist->artistTypes()->syncWithoutDetaching([$typeSolo->id, $typeDj->id]);
        $artist->artistGenres()->syncWithoutDetaching([$gHouse->id, $gTechno->id]);

        // Evenimente pe ultimele 12 luni, cu slug translatabil
        if (Schema::hasTable('events')) {
            $eventCols = Schema::getColumnListing('events');

            $start = Carbon::now()->startOfMonth()->subMonths(11);
            for ($i = 0; $i < 12; $i++) {
                $date  = (clone $start)->addMonths($i)->addDays(rand(0, 10));
                $slugEn = 'aurora-' . $date->format('Y-m');
                $titleEn = 'Aurora Nova Live ' . $date->format('M Y');

                // Caută după slug->en (JSON)
                $ev = Event::where('slug->en', $slugEn)->first();

                if (! $ev) {
                    // Construiește payload tolerant la coloane
                    $payload = [
                        'tenant_id'     => 1,
                        'title'         => ['en' => $titleEn],
                        'slug'          => ['en' => $slugEn],
                        'duration_mode' => 'single_day',
                        'event_date'    => $date->toDateString(),
                        'start_time'    => '20:00',
                        'end_time'      => '23:00',
                        'venue'         => 'Main Hall',
                        'city'          => 'București',
                        'country'       => 'Romania',
                        'is_active'     => true,
                    ];

                    $payload = array_filter(
                        $payload,
                        fn ($v, $k) => in_array($k, $eventCols, true),
                        ARRAY_FILTER_USE_BOTH
                    );

                    $ev = Event::create($payload);
                }

                // atașează artistul
                $artist->events()->syncWithoutDetaching([$ev->id]);
            }
        }
    }
}
