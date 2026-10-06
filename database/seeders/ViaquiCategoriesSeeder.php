<?php

namespace Database\Seeders;

use App\Models\MarketplaceEventCategory;
use Illuminate\Database\Seeder;

/**
 * The twelve main categories of Viaqui (marketplace client 4) with their subcategories, in English.
 *
 * Slugs match the starter catalogue of the site (resources/marketplaces/viaqui/includes/v2/seed.php), so the
 * menu, the footer and the homepage keep the same addresses and pictures once they read from the API.
 * Idempotent: rows are matched on (marketplace_client_id, slug); running it again only updates them.
 *
 *   php artisan db:seed --class="Database\Seeders\ViaquiCategoriesSeeder"
 */
class ViaquiCategoriesSeeder extends Seeder
{
    private const CLIENT_ID = 4;

    // slug => [name, emoji, one line, [subcategory slug => name]]
    private const CATEGORIES = [
        'museums-exhibitions' => ['Museums & Exhibitions', '🏛️', 'Collections worth an unhurried afternoon.', [
            'history-museums' => 'History museums', 'art-galleries' => 'Art galleries', 'science-centres' => 'Science centres',
            'open-air-museums' => 'Open-air museums', 'temporary-exhibitions' => 'Temporary exhibitions',
        ]],
        'culture-art' => ['Culture & Art', '🏰', 'Palaces, concert halls and living traditions.', [
            'castles-palaces' => 'Castles & palaces', 'citadels' => 'Citadels', 'monasteries' => 'Monasteries',
            'concert-halls' => 'Concert halls', 'old-towns' => 'Old towns',
        ]],
        'tours-sightseeing' => ['Tours & Sightseeing', '🧭', 'Walks, rides and stories with a local guide.', [
            'walking-tours' => 'Walking tours', 'day-trips' => 'Day trips', 'boat-trips' => 'Boat trips',
            'food-tours' => 'Food tours', 'bike-tours' => 'Bike tours',
        ]],
        'nature-outdoors' => ['Nature & Outdoors', '🌲', 'Gorges, caves, lakes and trails with a ticket gate.', [
            'caves' => 'Caves', 'salt-mines' => 'Salt mines', 'national-parks' => 'National parks',
            'gardens' => 'Gardens', 'viewpoints' => 'Viewpoints',
        ]],
        'adventure-parks' => ['Adventure Parks', '🧗', 'Zip lines, rope courses and a healthy dose of nerve.', [
            'rope-courses' => 'Rope courses', 'zip-lines' => 'Zip lines', 'via-ferrata' => 'Via ferrata',
            'climbing' => 'Climbing', 'rafting' => 'Rafting',
        ]],
        'theme-parks' => ['Theme Parks', '🎡', 'Rides, slides and a full day of noise.', [
            'amusement-parks' => 'Amusement parks', 'water-parks' => 'Water parks', 'dino-parks' => 'Dino parks',
            'trampoline-parks' => 'Trampoline parks',
        ]],
        'zoos-aquariums' => ['Zoos & Aquariums', '🐾', 'Animals up close, from sanctuaries to sea life.', [
            'zoos' => 'Zoos', 'aquariums' => 'Aquariums', 'sanctuaries' => 'Sanctuaries', 'farms' => 'Farms',
            'butterfly-houses' => 'Butterfly houses',
        ]],
        'family-kids' => ['Family & Kids', '🪁', 'Days out that children ask to repeat.', [
            'play-centres' => 'Play centres', 'kids-museums' => 'Kids museums', 'puppet-theatres' => 'Puppet theatres',
            'family-shows' => 'Family shows',
        ]],
        'escape-rooms' => ['Escape Rooms', '🗝️', 'Sixty minutes, one locked door, your best friends.', [
            'mystery-rooms' => 'Mystery rooms', 'horror-rooms' => 'Horror rooms', 'outdoor-quests' => 'Outdoor quests',
            'vr-rooms' => 'VR rooms',
        ]],
        'workshops-creative' => ['Workshops & Creative', '🎨', 'Make something with your hands and take it home.', [
            'pottery' => 'Pottery', 'cooking-classes' => 'Cooking classes', 'painting' => 'Painting', 'crafts' => 'Crafts',
            'tastings' => 'Tastings',
        ]],
        'learning-experiences' => ['Learning Experiences', '🔭', 'Planetariums, labs and lessons you can touch.', [
            'planetariums' => 'Planetariums', 'science-shows' => 'Science shows', 'farm-schools' => 'Farm schools',
            'history-re-enactments' => 'History re-enactments',
        ]],
        'groups-corporate' => ['Groups & Corporate', '👥', 'One booking for the whole team, class or party.', [
            'team-building' => 'Team building', 'school-trips' => 'School trips', 'private-tours' => 'Private tours',
            'celebrations' => 'Celebrations',
        ]],
    ];

    public function run(): void
    {
        $order = 0;
        $parents = 0;
        $children = 0;

        foreach (self::CATEGORIES as $slug => [$name, $emoji, $description, $subs]) {
            $parent = MarketplaceEventCategory::updateOrCreate(
                ['marketplace_client_id' => self::CLIENT_ID, 'slug' => $slug],
                [
                    'parent_id' => null,
                    'name' => ['en' => $name],
                    'description' => ['en' => $description],
                    'icon_emoji' => $emoji,
                    'sort_order' => ++$order * 10,
                    'is_visible' => true,
                    'is_featured' => true,
                ]
            );
            $parents++;

            $subOrder = 0;
            foreach ($subs as $subSlug => $subName) {
                MarketplaceEventCategory::updateOrCreate(
                    ['marketplace_client_id' => self::CLIENT_ID, 'slug' => $subSlug],
                    [
                        'parent_id' => $parent->id,
                        'name' => ['en' => $subName],
                        'sort_order' => $parent->sort_order + ++$subOrder,
                        'is_visible' => true,
                        'is_featured' => false,
                    ]
                );
                $children++;
            }
        }

        $this->command?->info("Viaqui categories: {$parents} main, {$children} subcategories (client " . self::CLIENT_ID . ').');
    }
}
