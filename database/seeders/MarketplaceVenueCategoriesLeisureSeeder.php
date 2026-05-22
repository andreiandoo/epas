<?php

namespace Database\Seeders;

use App\Models\MarketplaceVenueCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds flat venue category list for bilete.online — broad location types
 * (indoor / outdoor / museum / park / cave / reservation / leisure center /
 * escape room operator / cultural space / educational space).
 *
 * No parent-child hierarchy on this set; the model supports parent_id but
 * we don't use it for the leisure case.
 *
 * Usage: MARKETPLACE_ID=3 php artisan db:seed --class=MarketplaceVenueCategoriesLeisureSeeder
 */
class MarketplaceVenueCategoriesLeisureSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaceClientId = (int) env('MARKETPLACE_ID', 1);
        $this->command->info("Seeding leisure venue categories for marketplace_client_id: {$marketplaceClientId}");

        $sortOrder = 0;
        foreach ($this->categories() as $cat) {
            $sortOrder++;
            $slug = $cat['slug'] ?? Str::slug($cat['name']['ro']);

            MarketplaceVenueCategory::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => $slug,
                ],
                [
                    'parent_id' => null,
                    'name' => $cat['name'],
                    'icon' => $cat['icon'] ?? null,
                    'color' => $cat['color'] ?? null,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'is_featured' => $cat['featured'] ?? false,
                ]
            );
            $this->command->info("  • {$cat['name']['ro']} ({$slug})");
        }

        $this->command->info("Done.");
    }

    protected function categories(): array
    {
        return [
            [
                'slug' => 'locatie-indoor',
                'name' => ['ro' => 'Locație indoor', 'en' => 'Indoor venue'],
                'icon' => 'heroicon-o-building-storefront',
                'color' => 'sky',
                'featured' => true,
            ],
            [
                'slug' => 'locatie-outdoor',
                'name' => ['ro' => 'Locație outdoor', 'en' => 'Outdoor venue'],
                'icon' => 'heroicon-o-sun',
                'color' => 'forest',
                'featured' => true,
            ],
            [
                'slug' => 'muzeu',
                'name' => ['ro' => 'Muzeu', 'en' => 'Museum'],
                'icon' => 'heroicon-o-building-library',
                'color' => 'ochre',
            ],
            [
                'slug' => 'parc',
                'name' => ['ro' => 'Parc', 'en' => 'Park'],
                'icon' => 'heroicon-o-globe-europe-africa',
                'color' => 'forest',
            ],
            [
                'slug' => 'pestera',
                'name' => ['ro' => 'Peșteră', 'en' => 'Cave'],
                'icon' => 'heroicon-o-cube-transparent',
                'color' => 'ochre',
            ],
            [
                'slug' => 'rezervatie',
                'name' => ['ro' => 'Rezervație', 'en' => 'Nature reserve'],
                'icon' => 'heroicon-o-globe-alt',
                'color' => 'forest',
            ],
            [
                'slug' => 'centru-de-agrement',
                'name' => ['ro' => 'Centru de agrement', 'en' => 'Leisure center'],
                'icon' => 'heroicon-o-sparkles',
                'color' => 'vermilion',
                'featured' => true,
            ],
            [
                'slug' => 'operator-escape-room',
                'name' => ['ro' => 'Operator escape room', 'en' => 'Escape room operator'],
                'icon' => 'heroicon-o-puzzle-piece',
                'color' => 'vermilion',
            ],
            [
                'slug' => 'spatiu-cultural',
                'name' => ['ro' => 'Spațiu cultural', 'en' => 'Cultural space'],
                'icon' => 'heroicon-o-musical-note',
                'color' => 'ochre',
            ],
            [
                'slug' => 'spatiu-educational',
                'name' => ['ro' => 'Spațiu educațional', 'en' => 'Educational space'],
                'icon' => 'heroicon-o-academic-cap',
                'color' => 'sky',
            ],
        ];
    }
}
