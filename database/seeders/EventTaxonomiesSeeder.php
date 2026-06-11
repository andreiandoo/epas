<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventTaxonomiesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ===== CATEGORIES (globale) =====
        $categories = [
            ['name' => 'Music',      'slug' => 'music'],
            ['name' => 'Theatre',    'slug' => 'theatre'],
            ['name' => 'Comedy',     'slug' => 'comedy'],
            ['name' => 'Conference', 'slug' => 'conference'],
            ['name' => 'Workshop',   'slug' => 'workshop'],
            ['name' => 'Exhibition', 'slug' => 'exhibition'],
            ['name' => 'Film',       'slug' => 'film'],
            ['name' => 'Family',     'slug' => 'family'],
            ['name' => 'Charity',    'slug' => 'charity'],
        ];

        DB::table('event_categories')->upsert(
            collect($categories)->map(fn ($c) => [
                'name'       => $c['name'],
                'slug'       => $c['slug'],
                'description'=> null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            uniqueBy: ['slug'],
            update: ['name','description','updated_at']
        );

        // ===== EVENT GENRES (globale) =====
        $eventGenres = [
            ['name' => 'Festival', 'slug' => 'festival'],
            ['name' => 'Concert',  'slug' => 'concert'],
            ['name' => 'Club',     'slug' => 'club'],
            ['name' => 'Opera',    'slug' => 'opera'],
            ['name' => 'Ballet',   'slug' => 'ballet'],
        ];

        DB::table('event_genres')->upsert(
            collect($eventGenres)->map(fn ($g) => [
                'name'       => $g['name'],
                'slug'       => $g['slug'],
                'description'=> null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            uniqueBy: ['slug'],
            update: ['name','description','updated_at']
        );

        // ===== MUSIC GENRES (globale) =====
        $musicGenres = [
            ['name' => 'Rock',      'slug' => 'rock'],
            ['name' => 'Pop',       'slug' => 'pop'],
            ['name' => 'Electronic','slug' => 'electronic'],
            ['name' => 'Hip-Hop',   'slug' => 'hip-hop'],
            ['name' => 'Jazz',      'slug' => 'jazz'],
            ['name' => 'Classical', 'slug' => 'classical'],
        ];

        DB::table('music_genres')->upsert(
            collect($musicGenres)->map(fn ($g) => [
                'name'       => $g['name'],
                'slug'       => $g['slug'],
                'description'=> null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            uniqueBy: ['slug'],
            update: ['name','description','updated_at']
        );

        // ===== EVENT TAGS (pe TENANT) =====
        // IMPORTANT: tags sunt pe client → trebuie tenant_id.
        $tags = [
            ['name' => 'early-bird',     'slug' => 'early-bird'],
            ['name' => 'vip',            'slug' => 'vip'],
            ['name' => 'all-ages',       'slug' => 'all-ages'],
            ['name' => '18+',            'slug' => '18'],
            ['name' => 'seated',         'slug' => 'seated'],
            ['name' => 'standing',       'slug' => 'standing'],
            ['name' => 'meet-and-greet', 'slug' => 'meet-and-greet'],
            ['name' => 'limited',        'slug' => 'limited'],
            ['name' => 'outdoor',        'slug' => 'outdoor'],
            ['name' => 'indoor',         'slug' => 'indoor'],
            ['name' => 'charity',        'slug' => 'charity'],
            ['name' => 'sold-out',       'slug' => 'sold-out'],
            ['name' => 'rescheduled',    'slug' => 'rescheduled'],
        ];

        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $payload = collect($tags)->map(function ($t) use ($tenantId, $now) {
                return [
                    'tenant_id'  => $tenantId,
                    'name'       => $t['name'],
                    'slug'       => Str::slug($t['slug']),
                    'description'=> null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            // upsert pe (tenant_id, slug)
            DB::table('event_tags')->upsert(
                $payload,
                uniqueBy: ['tenant_id','slug'],
                update: ['name','description','updated_at']
            );
        }
    }
}
