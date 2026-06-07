<?php

namespace Database\Seeders;

use App\Models\Blog\BlogCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds a curated set of blog/guide categories for bilete.online (activities
 * marketplace). Idempotent — updateOrCreate keyed on (marketplace_client_id,
 * slug), so re-running refreshes names/icons without duplicating.
 *
 * Target marketplace defaults to client 3 (bilete.online); override with
 * BLOG_CATEGORIES_CLIENT_ID. Trim/extend the $categories array as you like
 * before running.
 *
 * Run:  php artisan db:seed --class=BlogGuideCategoriesSeeder
 */
class BlogGuideCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $clientId = (int) env('BLOG_CATEGORIES_CLIENT_ID', 3);

        // [ ro, en, slug, icon(emoji), color(hex) ]
        $categories = [
            ['Ghiduri de oraș',        'City guides',            'ghiduri-de-oras',   '🏙️', '#2C5F8A'],
            ['Idei de weekend',        'Weekend ideas',          'idei-de-weekend',   '🗓️', '#E84527'],
            ['Cupluri & date night',   'Couples & date night',   'cupluri',           '💞', '#DA9A33'],
            ['Familie & copii',        'Family & kids',          'familie-copii',     '🧸', '#1E4A3D'],
            ['Aventură & adrenalină',  'Adventure & adrenaline', 'aventura',          '🧗', '#E84527'],
            ['Cultură & istorie',      'Culture & history',      'cultura-istorie',   '🏛️', '#2C5F8A'],
            ['Gastronomie & băuturi',  'Food & drink',           'gastronomie',       '🍽️', '#DA9A33'],
            ['Natură & outdoor',       'Nature & outdoor',       'natura-outdoor',    '🌲', '#1E4A3D'],
            ['Team building & corporate', 'Team building',       'team-building',     '🤝', '#2C5F8A'],
            ['Escape rooms & jocuri',  'Escape rooms & games',   'escape-jocuri',     '🗝️', '#1B1714'],
            ['Sezonier & sărbători',   'Seasonal & holidays',    'sezonier',          '🎄', '#E84527'],
            ['Wellness & relaxare',    'Wellness & relax',       'wellness',          '🧖', '#1E4A3D'],
            ['Tururi ghidate',         'Guided tours',           'tururi-ghidate',    '🚶', '#2C5F8A'],
            ['Viață de noapte',        'Nightlife',              'viata-de-noapte',   '🌙', '#1B1714'],
            ['Buget & oferte',         'Budget & deals',         'buget-oferte',      '💸', '#DA9A33'],
            ['Zile ploioase / indoor', 'Rainy day / indoor',     'indoor',            '☔', '#2C5F8A'],
        ];

        $sort = 0;
        foreach ($categories as [$ro, $en, $slug, $icon, $color]) {
            BlogCategory::updateOrCreate(
                ['marketplace_client_id' => $clientId, 'slug' => $slug],
                [
                    'tenant_id'   => null,
                    'name'        => ['ro' => $ro, 'en' => $en],
                    'icon'        => $icon,
                    'color'       => $color,
                    'sort_order'  => $sort++,
                    'is_visible'  => true,
                ]
            );
        }

        $this->command?->info('Seeded ' . count($categories) . " guide categories for marketplace client #{$clientId}.");
    }
}
