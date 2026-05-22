<?php

namespace Database\Seeders;

use App\Models\MarketplaceEventCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds leisure-focused event categories for bilete.online (or any leisure
 * marketplace). 12 parent categories with hierarchical children.
 *
 * Usage: MARKETPLACE_ID=3 php artisan db:seed --class=MarketplaceEventCategoriesLeisureSeeder
 *
 * Idempotent — uses updateOrCreate keyed on (marketplace_client_id, slug).
 * Re-running refreshes labels but preserves IDs and FK relationships.
 */
class MarketplaceEventCategoriesLeisureSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaceClientId = (int) env('MARKETPLACE_ID', 1);
        $this->command->info("Seeding leisure event categories for marketplace_client_id: {$marketplaceClientId}");

        $parentSort = 0;
        foreach ($this->categories() as $parentData) {
            $parentSort++;

            // Build slug from RO name explicitly (model boot uses marketplace's language;
            // we set marketplace_client_id at create time so it resolves to 'ro').
            $parentSlug = $parentData['slug'] ?? Str::slug($parentData['name']['ro']);

            $parent = MarketplaceEventCategory::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => $parentSlug,
                ],
                [
                    'parent_id' => null,
                    'name' => $parentData['name'],
                    'icon_emoji' => $parentData['emoji'] ?? null,
                    'color' => $parentData['color'] ?? null,
                    'sort_order' => $parentSort,
                    'is_visible' => true,
                    'is_featured' => $parentData['featured'] ?? false,
                ]
            );

            $this->command->info("  ● {$parentData['name']['ro']} ({$parentSlug})");

            $childSort = 0;
            foreach ($parentData['children'] ?? [] as $childRoName) {
                $childSort++;
                // Suffix slug with parent slug to avoid collisions across hierarchies —
                // e.g. "Activități pentru copii" appears under Family AND Education.
                $baseSlug = Str::slug($childRoName);
                $childSlug = $baseSlug . '-' . $parentSlug;

                MarketplaceEventCategory::updateOrCreate(
                    [
                        'marketplace_client_id' => $marketplaceClientId,
                        'slug' => $childSlug,
                    ],
                    [
                        'parent_id' => $parent->id,
                        'name' => ['ro' => $childRoName],
                        'color' => $parentData['color'] ?? null, // inherit visual identity
                        'sort_order' => $childSort,
                        'is_visible' => true,
                        'is_featured' => false,
                    ]
                );
                $this->command->line("      └ {$childRoName}");
            }
        }

        $this->command->info("Done.");
    }

    /**
     * Each parent has: slug (URL-friendly RO name), emoji icon, accent color
     * from the bilete.online palette (vermilion/forest/ochre/sky), translated
     * name, and an array of child category names (Romanian).
     */
    protected function categories(): array
    {
        return [
            [
                'slug' => 'escape-rooms',
                'emoji' => '🔐',
                'color' => '#E84527',
                'featured' => true,
                'name' => ['ro' => 'Escape rooms', 'en' => 'Escape Rooms'],
                'children' => [
                    'Escape rooms clasice',
                    'Escape rooms horror',
                    'Escape rooms mystery / detective',
                    'Escape rooms adventure',
                    'Escape rooms fantasy / sci-fi',
                    'Escape rooms pentru copii',
                    'Escape rooms pentru adolescenți',
                    'Escape rooms pentru grupuri',
                    'Escape rooms corporate / team building',
                ],
            ],
            [
                'slug' => 'muzee-expozitii',
                'emoji' => '🏛️',
                'color' => '#1E4A3D',
                'featured' => true,
                'name' => ['ro' => 'Muzee & expoziții', 'en' => 'Museums & Exhibitions'],
                'children' => [
                    'Muzee de artă',
                    'Muzee de istorie',
                    'Muzee de știință',
                    'Muzee interactive',
                    'Muzee pentru copii',
                    'Expoziții temporare',
                    'Galerii de artă',
                    'Planetarii',
                    'Centre de știință',
                    'Experiențe imersive / interactive',
                ],
            ],
            [
                'slug' => 'parcuri-de-distractii',
                'emoji' => '🎡',
                'color' => '#2C5F8A',
                'featured' => true,
                'name' => ['ro' => 'Parcuri de distracții', 'en' => 'Amusement Parks'],
                'children' => [
                    'Parcuri de distracții clasice',
                    'Parcuri tematice',
                    'Zone de joacă',
                    'Parcuri pentru copii',
                    'Parcuri indoor',
                    'Parcuri sezoniere',
                    'Carusele / atracții mecanice',
                    'Experiențe family entertainment center',
                ],
            ],
            [
                'slug' => 'parcuri-de-aventura',
                'emoji' => '🧗',
                'color' => '#DA9A33',
                'featured' => true,
                'name' => ['ro' => 'Parcuri de aventură', 'en' => 'Adventure Parks'],
                'children' => [
                    'Trasee în copaci',
                    'Tiroliene',
                    'Panouri de escaladă',
                    'Aventură pentru copii',
                    'Aventură pentru adolescenți',
                    'Aventură pentru adulți',
                    'Pachete de grup',
                    'Team building outdoor',
                ],
            ],
            [
                'slug' => 'natura-outdoor',
                'emoji' => '🌲',
                'color' => '#1E4A3D',
                'name' => ['ro' => 'Natură & outdoor', 'en' => 'Nature & Outdoor'],
                'children' => [
                    'Rezervații naturale',
                    'Tururi ghidate în natură',
                    'Peșteri',
                    'Chei / canioane / trasee spectaculoase',
                    'Observatoare animale',
                    'Grădini botanice',
                    'Parcuri naturale',
                    'Activități montane ușoare',
                    'Experiențe eco / educație de mediu',
                ],
            ],
            [
                'slug' => 'acvarii-zoo-animale',
                'emoji' => '🐠',
                'color' => '#2C5F8A',
                'name' => ['ro' => 'Acvarii, zoo & animale', 'en' => 'Aquariums, Zoos & Animals'],
                'children' => [
                    'Acvarii',
                    'Grădini zoologice',
                    'Ferme educative',
                    'Sanctuare / rezervații de animale',
                    'Observatoare faună',
                    'Experiențe cu animale pentru copii',
                ],
            ],
            [
                'slug' => 'ateliere-experiente-creative',
                'emoji' => '🎨',
                'color' => '#E84527',
                'name' => ['ro' => 'Ateliere & experiențe creative', 'en' => 'Workshops & Creative Experiences'],
                'children' => [
                    'Ateliere pentru copii',
                    'Ateliere pentru adulți',
                    'Ateliere de pictură',
                    'Ateliere de ceramică',
                    'Ateliere DIY / craft',
                    'Ateliere educative',
                    'Ateliere de știință',
                    'Ateliere tematice sezoniere',
                    'Experiențe creative pentru grupuri',
                ],
            ],
            [
                'slug' => 'tururi-experiente-turistice',
                'emoji' => '🗺️',
                'color' => '#DA9A33',
                'name' => ['ro' => 'Tururi & experiențe turistice', 'en' => 'Tours & Tourist Experiences'],
                'children' => [
                    'Tururi ghidate urbane',
                    'Tururi istorice',
                    'Tururi culturale',
                    'Tururi în natură',
                    'Tururi gastronomice',
                    'Tururi pentru turiști străini',
                    'City walks',
                    'Tururi pentru școli / grupuri',
                ],
            ],
            [
                'slug' => 'educatie-invatare-experientiala',
                'emoji' => '🔬',
                'color' => '#1E4A3D',
                'name' => ['ro' => 'Educație & învățare experiențială', 'en' => 'Education & Experiential Learning'],
                'children' => [
                    'Activități educative',
                    'Activități STEM',
                    'Activități pentru școli',
                    'Activități pentru grădinițe',
                    'Excursii educative',
                    'Lecții interactive',
                    'Vizite ghidate educative',
                    'Ateliere de literație / știință / artă',
                    'Experiențe pentru clase',
                ],
            ],
            [
                'slug' => 'familie-copii',
                'emoji' => '👨‍👩‍👧',
                'color' => '#DA9A33',
                'name' => ['ro' => 'Familie & copii', 'en' => 'Family & Kids'],
                'children' => [
                    'Activități pentru copii',
                    'Activități pentru familie',
                    'Activități pentru grădinițe',
                    'Activități pentru școli',
                    'Activități pentru adolescenți',
                    'Activități pentru zile de naștere',
                    'Locuri de joacă',
                    'Ateliere copii',
                    'Muzee interactive copii',
                    'Parcuri pentru copii',
                ],
            ],
            [
                'slug' => 'corporate-grupuri',
                'emoji' => '💼',
                'color' => '#2C5F8A',
                'name' => ['ro' => 'Corporate & grupuri', 'en' => 'Corporate & Groups'],
                'children' => [
                    'Team building',
                    'Activități corporate',
                    'Activități pentru grupuri',
                    'Private events',
                    'Group bookings',
                    'Pachete corporate',
                    'Pachete aniversare',
                    'Pachete școli / clase',
                    'Activități pentru comunități',
                ],
            ],
            [
                'slug' => 'cultura-arta',
                'emoji' => '🎭',
                'color' => '#E84527',
                'name' => ['ro' => 'Cultură & artă', 'en' => 'Culture & Arts'],
                'children' => [
                    'Muzee',
                    'Galerii',
                    'Expoziții',
                    'Tururi culturale',
                    'Ateliere artistice',
                    'Experiențe imersive',
                    'Evenimente culturale cu bilete',
                    'Activități de patrimoniu',
                ],
            ],
        ];
    }
}
