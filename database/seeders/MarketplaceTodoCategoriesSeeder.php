<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceTodoCategory;
use Illuminate\Database\Seeder;

class MarketplaceTodoCategoriesSeeder extends Seeder
{
    /**
     * Default TODO categories seeded for every marketplace client.
     * Existing categories with the same slug are preserved (no overwrite),
     * so the seeder can be re-run safely.
     */
    public function run(): void
    {
        $defaults = [
            ['name' => 'Bug / Defect',         'slug' => 'bug',          'color' => 'danger',  'icon' => 'heroicon-o-bug-ant',                  'sort_order' => 10],
            ['name' => 'Cerere îmbunătățire', 'slug' => 'improvement',  'color' => 'info',    'icon' => 'heroicon-o-light-bulb',               'sort_order' => 20],
            ['name' => 'Suport organizator',  'slug' => 'support',      'color' => 'warning', 'icon' => 'heroicon-o-lifebuoy',                 'sort_order' => 30],
            ['name' => 'Financiar / Decont',  'slug' => 'finance',      'color' => 'success', 'icon' => 'heroicon-o-banknotes',                'sort_order' => 40],
            ['name' => 'Marketing / Newsletter','slug' => 'marketing',  'color' => 'primary', 'icon' => 'heroicon-o-megaphone',                'sort_order' => 50],
            ['name' => 'Operațional',         'slug' => 'operational',  'color' => 'gray',    'icon' => 'heroicon-o-cog-6-tooth',              'sort_order' => 60],
            ['name' => 'Altele',              'slug' => 'other',        'color' => 'gray',    'icon' => 'heroicon-o-ellipsis-horizontal-circle','sort_order' => 99],
        ];

        $clients = MarketplaceClient::query()->get();
        foreach ($clients as $client) {
            foreach ($defaults as $row) {
                MarketplaceTodoCategory::query()
                    ->withoutGlobalScopes()
                    ->updateOrCreate(
                        [
                            'marketplace_client_id' => $client->id,
                            'slug' => $row['slug'],
                        ],
                        [
                            'name' => $row['name'],
                            'color' => $row['color'],
                            'icon' => $row['icon'],
                            'sort_order' => $row['sort_order'],
                            'is_active' => true,
                        ],
                    );
            }
        }
    }
}
