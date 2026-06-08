<?php

namespace Database\Seeders;

use App\Models\AttractionType;
use App\Models\Interest;
use App\Models\TravelerType;
use Illuminate\Database\Seeder;

/**
 * Seeds a curated starter set of discovery taxonomies for bilete.online
 * (marketplace_client_id 3): Interests, Traveler types, Attraction types.
 *
 * Specific Attractions (POIs) are left for the admin — the AttractionResource
 * is ready. Idempotent (updateOrCreate keyed on marketplace_client_id + slug).
 *
 * Run: php artisan db:seed --class=DiscoveryTaxonomiesSeeder
 * Override target client with DISCOVERY_CLIENT_ID.
 */
class DiscoveryTaxonomiesSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = (int) env('DISCOVERY_CLIENT_ID', 3);

        // [ ro, en, slug, emoji, color ]
        $interests = [
            ['Mister & enigme',     'Mystery & puzzles',  'mister',           '🕵️', '#1B1714'],
            ['Aventură',            'Adventure',          'aventura',         '🧗', '#E84527'],
            ['Adrenalină',          'Adrenaline',         'adrenalina',       '⚡', '#C5371C'],
            ['Cultură & istorie',   'Culture & history',  'cultura-istorie',  '🏛️', '#2C5F8A'],
            ['Artă',                'Art',                'arta',             '🎨', '#DA9A33'],
            ['Gastronomie',         'Food & drink',       'gastronomie',      '🍽️', '#DA9A33'],
            ['Natură & outdoor',    'Nature & outdoor',   'natura-outdoor',   '🌲', '#1E4A3D'],
            ['Wellness & relaxare', 'Wellness & relax',   'wellness',         '🧖', '#1E4A3D'],
            ['Educațional',         'Educational',        'educational',      '📚', '#2C5F8A'],
            ['Romantic',            'Romantic',           'romantic',         '💞', '#E84527'],
            ['Fotografie',          'Photography',        'fotografie',       '📷', '#5A4F41'],
            ['Viață de noapte',     'Nightlife',          'viata-de-noapte',  '🌙', '#1B1714'],
        ];

        $travelerTypes = [
            ['Cupluri',         'Couples',        'cupluri',       '💛', '#E84527'],
            ['Familii',         'Families',       'familii',       '👨‍👩‍👧', '#1E4A3D'],
            ['Solo',            'Solo',           'solo',          '🚶', '#2C5F8A'],
            ['Grupuri',         'Groups',         'grupuri',       '👥', '#DA9A33'],
            ['Prieteni',        'Friends',        'prieteni',      '🤝', '#E84527'],
            ['Copii',           'Kids',           'copii',         '🧸', '#1E4A3D'],
            ['Adolescenți',     'Teens',          'adolescenti',   '🎮', '#2C5F8A'],
            ['Team building',   'Team building',  'team-building', '🏢', '#1B1714'],
            ['Turiști',         'Tourists',       'turisti',       '🎒', '#DA9A33'],
            ['Seniori',         'Seniors',        'seniori',       '🌳', '#1E4A3D'],
        ];

        $attractionTypes = [
            ['Monument',           'Monument',          'monument',          '🗿', '#5A4F41'],
            ['Muzeu',              'Museum',            'muzeu',             '🏛️', '#2C5F8A'],
            ['Castel & palat',     'Castle & palace',   'castel-palat',      '🏰', '#DA9A33'],
            ['Biserică & mănăstire', 'Church & monastery', 'biserica-manastire', '⛪', '#1B1714'],
            ['Parc & grădină',     'Park & garden',     'parc-gradina',      '🌳', '#1E4A3D'],
            ['Piață & centru vechi', 'Square & old town', 'piata-centru-vechi', '🏙️', '#E84527'],
            ['Clădire istorică',   'Historic building',  'cladire-istorica',  '🏚️', '#5A4F41'],
            ['Punct panoramic',    'Viewpoint',          'punct-panoramic',   '🌄', '#2C5F8A'],
            ['Lac & natură',       'Lake & nature',      'lac-natura',        '🏞️', '#1E4A3D'],
            ['Teatru & operă',     'Theatre & opera',    'teatru-opera',      '🎭', '#DA9A33'],
        ];

        $sort = 0;
        foreach ($interests as [$ro, $en, $slug, $icon, $color]) {
            Interest::updateOrCreate(
                ['marketplace_client_id' => $clientId, 'slug' => $slug],
                ['name' => ['ro' => $ro, 'en' => $en], 'icon_emoji' => $icon, 'color' => $color, 'sort_order' => $sort++, 'is_visible' => true]
            );
        }

        $sort = 0;
        foreach ($travelerTypes as [$ro, $en, $slug, $icon, $color]) {
            TravelerType::updateOrCreate(
                ['marketplace_client_id' => $clientId, 'slug' => $slug],
                ['name' => ['ro' => $ro, 'en' => $en], 'icon_emoji' => $icon, 'color' => $color, 'sort_order' => $sort++, 'is_visible' => true]
            );
        }

        $sort = 0;
        foreach ($attractionTypes as [$ro, $en, $slug, $icon, $color]) {
            AttractionType::updateOrCreate(
                ['marketplace_client_id' => $clientId, 'slug' => $slug],
                ['name' => ['ro' => $ro, 'en' => $en], 'icon_emoji' => $icon, 'color' => $color, 'sort_order' => $sort++, 'is_visible' => true]
            );
        }

        $this->command?->info('✓ Seeded ' . count($interests) . ' interests, ' . count($travelerTypes) . ' traveler types, ' . count($attractionTypes) . " attraction types for client #{$clientId}.");
    }
}
