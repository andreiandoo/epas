<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceVenueCategory;
use Illuminate\Database\Seeder;

class TicsVenueCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $marketplace = MarketplaceClient::find(2);

        if (! $marketplace) {
            $this->command->error('Marketplace client cu id=2 nu a fost găsit. Aborting.');
            return;
        }

        $mcId = 2;

        $this->command->info("Seeding venue categories for marketplace: {$marketplace->name} (id={$mcId})");

        // Delete existing categories for this marketplace to avoid duplicates
        MarketplaceVenueCategory::where('marketplace_client_id', $mcId)->forceDelete();

        $categories = [
            [
                'icon'  => '🎵',
                'color' => '#7C3AED',
                'sort'  => 1,
                'name'  => ['ro' => 'Muzică', 'en' => 'Music'],
                'desc'  => ['ro' => 'Concerte, festivaluri muzicale și orice eveniment cu muzică live', 'en' => 'Concerts, music festivals and live music events of all kinds'],
                'children' => [
                    ['icon' => '🎤', 'color' => '#8B5CF6', 'sort' => 1, 'name' => ['ro' => 'Concerte', 'en' => 'Concerts'], 'desc' => ['ro' => 'Concerte solo sau în trupă, de orice gen muzical', 'en' => 'Solo or band concerts across all music genres']],
                    ['icon' => '🎪', 'color' => '#A78BFA', 'sort' => 2, 'name' => ['ro' => 'Festivaluri muzicale', 'en' => 'Music Festivals'], 'desc' => ['ro' => 'Festivaluri cu mai mulți artiști și zile multiple', 'en' => 'Multi-artist and multi-day music festivals']],
                    ['icon' => '🎧', 'color' => '#6D28D9', 'sort' => 3, 'name' => ['ro' => 'Club & DJ', 'en' => 'Club & DJ'], 'desc' => ['ro' => 'Seri de club, petreceri cu DJ și muzică electronică', 'en' => 'Club nights, DJ sets and electronic music events']],
                    ['icon' => '🎻', 'color' => '#5B21B6', 'sort' => 4, 'name' => ['ro' => 'Muzică clasică & Operă', 'en' => 'Classical Music & Opera'], 'desc' => ['ro' => 'Concerte simfonice, recitaluri și spectacole de operă', 'en' => 'Symphonic concerts, recitals and opera performances']],
                    ['icon' => '🎙️', 'color' => '#9333EA', 'sort' => 5, 'name' => ['ro' => 'Karaoke', 'en' => 'Karaoke'], 'desc' => ['ro' => 'Seri de karaoke în cluburi și baruri', 'en' => 'Karaoke nights at clubs and bars']],
                ],
            ],
            [
                'icon'  => '🎭',
                'color' => '#EC4899',
                'sort'  => 2,
                'name'  => ['ro' => 'Teatru & Artă', 'en' => 'Theatre & Arts'],
                'desc'  => ['ro' => 'Spectacole live, arte vizuale și performanțe culturale', 'en' => 'Live performances, visual arts and cultural events'],
                'children' => [
                    ['icon' => '🎭', 'color' => '#F472B6', 'sort' => 1, 'name' => ['ro' => 'Teatru', 'en' => 'Theatre'], 'desc' => ['ro' => 'Piese de teatru clasice și contemporane', 'en' => 'Classic and contemporary theatre plays']],
                    ['icon' => '😂', 'color' => '#DB2777', 'sort' => 2, 'name' => ['ro' => 'Stand-up Comedy', 'en' => 'Stand-up Comedy'], 'desc' => ['ro' => 'Seri de stand-up comedy cu comici consacrați sau în devenire', 'en' => 'Stand-up comedy nights with established or emerging comedians']],
                    ['icon' => '🎶', 'color' => '#BE185D', 'sort' => 3, 'name' => ['ro' => 'Muzicale & Reviste', 'en' => 'Musicals & Revues'], 'desc' => ['ro' => 'Spectacole de musical, reviste și varieteu', 'en' => 'Musical shows, revues and variety performances']],
                    ['icon' => '🩰', 'color' => '#F9A8D4', 'sort' => 4, 'name' => ['ro' => 'Balet & Dans', 'en' => 'Ballet & Dance'], 'desc' => ['ro' => 'Spectacole de balet, dans contemporan și folcloric', 'en' => 'Ballet, contemporary and folk dance performances']],
                    ['icon' => '🖼️', 'color' => '#EC4899', 'sort' => 5, 'name' => ['ro' => 'Expoziții & Vernisaje', 'en' => 'Exhibitions & Openings'], 'desc' => ['ro' => 'Expoziții de artă, vernisaje și instalații', 'en' => 'Art exhibitions, gallery openings and installations']],
                    ['icon' => '🎩', 'color' => '#9D174D', 'sort' => 6, 'name' => ['ro' => 'Circ & Magie', 'en' => 'Circus & Magic'], 'desc' => ['ro' => 'Spectacole de circ, iluzionism și magie', 'en' => 'Circus, illusionist and magic shows']],
                ],
            ],
            [
                'icon'  => '⚽',
                'color' => '#10B981',
                'sort'  => 3,
                'name'  => ['ro' => 'Sport', 'en' => 'Sports'],
                'desc'  => ['ro' => 'Competiții sportive și evenimente fizice de toate tipurile', 'en' => 'Sports competitions and physical events of all kinds'],
                'children' => [
                    ['icon' => '⚽', 'color' => '#059669', 'sort' => 1, 'name' => ['ro' => 'Fotbal', 'en' => 'Football'], 'desc' => ['ro' => 'Meciuri și turnee de fotbal', 'en' => 'Football matches and tournaments']],
                    ['icon' => '🎾', 'color' => '#34D399', 'sort' => 2, 'name' => ['ro' => 'Tenis', 'en' => 'Tennis'], 'desc' => ['ro' => 'Meciuri și turnee de tenis', 'en' => 'Tennis matches and tournaments']],
                    ['icon' => '🏀', 'color' => '#047857', 'sort' => 3, 'name' => ['ro' => 'Baschet', 'en' => 'Basketball'], 'desc' => ['ro' => 'Meciuri și competiții de baschet', 'en' => 'Basketball matches and competitions']],
                    ['icon' => '🥊', 'color' => '#065F46', 'sort' => 4, 'name' => ['ro' => 'Box & Arte Marțiale', 'en' => 'Boxing & Martial Arts'], 'desc' => ['ro' => 'Gale de box, MMA, kickboxing și arte marțiale', 'en' => 'Boxing, MMA, kickboxing and martial arts events']],
                    ['icon' => '⛷️', 'color' => '#6EE7B7', 'sort' => 5, 'name' => ['ro' => 'Sport de iarnă', 'en' => 'Winter Sports'], 'desc' => ['ro' => 'Competiții de schi, snowboard și patinaj', 'en' => 'Ski, snowboard and ice skating competitions']],
                    ['icon' => '🏋️', 'color' => '#10B981', 'sort' => 6, 'name' => ['ro' => 'Fitness & Wellness', 'en' => 'Fitness & Wellness'], 'desc' => ['ro' => 'Competiții de fitness, yoga și evenimente wellness', 'en' => 'Fitness competitions, yoga and wellness events']],
                ],
            ],
            [
                'icon'  => '🎬',
                'color' => '#F59E0B',
                'sort'  => 4,
                'name'  => ['ro' => 'Film & Cinema', 'en' => 'Film & Cinema'],
                'desc'  => ['ro' => 'Proiecții, premiere și evenimente cinematografice', 'en' => 'Screenings, premieres and film events'],
                'children' => [
                    ['icon' => '🎬', 'color' => '#D97706', 'sort' => 1, 'name' => ['ro' => 'Cinema', 'en' => 'Cinema'], 'desc' => ['ro' => 'Proiecții de film în cinematografe și în aer liber', 'en' => 'Film screenings in cinemas and outdoors']],
                    ['icon' => '🌟', 'color' => '#F59E0B', 'sort' => 2, 'name' => ['ro' => 'Premiere de film', 'en' => 'Film Premieres'], 'desc' => ['ro' => 'Premiere naționale și internaționale de film', 'en' => 'National and international film premieres']],
                    ['icon' => '🚗', 'color' => '#B45309', 'sort' => 3, 'name' => ['ro' => 'Drive-in', 'en' => 'Drive-in'], 'desc' => ['ro' => 'Proiecții de film drive-in și în aer liber', 'en' => 'Drive-in and open-air film screenings']],
                ],
            ],
            [
                'icon'  => '🎓',
                'color' => '#3B82F6',
                'sort'  => 5,
                'name'  => ['ro' => 'Educație & Business', 'en' => 'Education & Business'],
                'desc'  => ['ro' => 'Conferințe, cursuri și evenimente profesionale', 'en' => 'Conferences, courses and professional events'],
                'children' => [
                    ['icon' => '🎤', 'color' => '#2563EB', 'sort' => 1, 'name' => ['ro' => 'Conferințe & Summit-uri', 'en' => 'Conferences & Summits'], 'desc' => ['ro' => 'Conferințe de business, summit-uri și panel-uri', 'en' => 'Business conferences, summits and panels']],
                    ['icon' => '📚', 'color' => '#1D4ED8', 'sort' => 2, 'name' => ['ro' => 'Workshop-uri & Training', 'en' => 'Workshops & Training'], 'desc' => ['ro' => 'Workshop-uri practice și sesiuni de training profesional', 'en' => 'Practical workshops and professional training sessions']],
                    ['icon' => '📖', 'color' => '#3B82F6', 'sort' => 3, 'name' => ['ro' => 'Lansări de carte & Târguri', 'en' => 'Book Launches & Fairs'], 'desc' => ['ro' => 'Lansări de carte, târguri de carte și sesiuni de autografe', 'en' => 'Book launches, book fairs and signing sessions']],
                    ['icon' => '💻', 'color' => '#1E40AF', 'sort' => 4, 'name' => ['ro' => 'Hackathoane & Tech', 'en' => 'Hackathons & Tech'], 'desc' => ['ro' => 'Competiții de programare, hackathoane și conferințe tech', 'en' => 'Coding competitions, hackathons and tech conferences']],
                ],
            ],
            [
                'icon'  => '👨‍👩‍👧',
                'color' => '#F97316',
                'sort'  => 6,
                'name'  => ['ro' => 'Familie & Copii', 'en' => 'Family & Kids'],
                'desc'  => ['ro' => 'Activități și spectacole pentru toate vârstele', 'en' => 'Activities and shows for all ages'],
                'children' => [
                    ['icon' => '🧸', 'color' => '#EA580C', 'sort' => 1, 'name' => ['ro' => 'Spectacole pentru copii', 'en' => 'Kids Shows'], 'desc' => ['ro' => 'Spectacole de teatru, animație și povești pentru copii', 'en' => 'Theatre, animation and storytelling shows for children']],
                    ['icon' => '🎡', 'color' => '#F97316', 'sort' => 2, 'name' => ['ro' => 'Parcuri de distracții', 'en' => 'Amusement Parks'], 'desc' => ['ro' => 'Evenimente la parcuri de distracții și zone de joacă', 'en' => 'Events at amusement parks and playgrounds']],
                    ['icon' => '🎨', 'color' => '#C2410C', 'sort' => 3, 'name' => ['ro' => 'Ateliere creative', 'en' => 'Creative Workshops'], 'desc' => ['ro' => 'Ateliere de pictură, ceramică, olărit și artă pentru copii și adulți', 'en' => 'Painting, ceramics, pottery and art workshops for kids and adults']],
                ],
            ],
            [
                'icon'  => '🍷',
                'color' => '#84CC16',
                'sort'  => 7,
                'name'  => ['ro' => 'Gastronomie & Lifestyle', 'en' => 'Food & Lifestyle'],
                'desc'  => ['ro' => 'Festivaluri culinare, degustări și experiențe gastronomice', 'en' => 'Culinary festivals, tastings and gastronomic experiences'],
                'children' => [
                    ['icon' => '🍽️', 'color' => '#65A30D', 'sort' => 1, 'name' => ['ro' => 'Festivaluri gastronomice', 'en' => 'Food Festivals'], 'desc' => ['ro' => 'Festivaluri de mâncare, street food și bucătărie internațională', 'en' => 'Food festivals, street food and international cuisine events']],
                    ['icon' => '🍷', 'color' => '#4D7C0F', 'sort' => 2, 'name' => ['ro' => 'Degustări vin & bere', 'en' => 'Wine & Beer Tastings'], 'desc' => ['ro' => 'Degustări de vin, bere artizanală și spirtoase', 'en' => 'Wine, craft beer and spirits tasting events']],
                    ['icon' => '🌮', 'color' => '#84CC16', 'sort' => 3, 'name' => ['ro' => 'Street Food', 'en' => 'Street Food'], 'desc' => ['ro' => 'Evenimente de street food și food truck festivals', 'en' => 'Street food events and food truck festivals']],
                ],
            ],
            [
                'icon'  => '🌿',
                'color' => '#06B6D4',
                'sort'  => 8,
                'name'  => ['ro' => 'Natură & Aventură', 'en' => 'Nature & Adventure'],
                'desc'  => ['ro' => 'Activități în aer liber și experiențe de neuitat', 'en' => 'Outdoor activities and unforgettable experiences'],
                'children' => [
                    ['icon' => '🥾', 'color' => '#0891B2', 'sort' => 1, 'name' => ['ro' => 'Excursii & Drumeții', 'en' => 'Trips & Hiking'], 'desc' => ['ro' => 'Excursii organizate, drumeții și trasee montane', 'en' => 'Organized trips, hiking and mountain trails']],
                    ['icon' => '🧗', 'color' => '#0E7490', 'sort' => 2, 'name' => ['ro' => 'Sporturi extreme', 'en' => 'Extreme Sports'], 'desc' => ['ro' => 'Alpinism, paintball, quad și alte sporturi extreme', 'en' => 'Rock climbing, paintball, quad and extreme sports events']],
                    ['icon' => '🤝', 'color' => '#06B6D4', 'sort' => 3, 'name' => ['ro' => 'Team Building', 'en' => 'Team Building'], 'desc' => ['ro' => 'Activități de team building și coeziune de echipă', 'en' => 'Team building activities and corporate bonding events']],
                ],
            ],
            [
                'icon'  => '🎉',
                'color' => '#EF4444',
                'sort'  => 9,
                'name'  => ['ro' => 'Petreceri & Gale', 'en' => 'Parties & Galas'],
                'desc'  => ['ro' => 'Celebrări, gale și evenimente sociale de excepție', 'en' => 'Celebrations, galas and exceptional social events'],
                'children' => [
                    ['icon' => '🏆', 'color' => '#DC2626', 'sort' => 1, 'name' => ['ro' => 'Gale & Premii', 'en' => 'Galas & Awards'], 'desc' => ['ro' => 'Ceremonii de premiere, gale de excelență și decernări', 'en' => 'Award ceremonies, excellence galas and prize-giving events']],
                    ['icon' => '🎊', 'color' => '#EF4444', 'sort' => 2, 'name' => ['ro' => 'Petreceri tematice', 'en' => 'Themed Parties'], 'desc' => ['ro' => 'Petreceri cu tematică specifică, costume și decoruri', 'en' => 'Costume and themed parties with specific decor']],
                    ['icon' => '🥂', 'color' => '#B91C1C', 'sort' => 3, 'name' => ['ro' => 'Revelion & Sărbători', 'en' => 'New Year & Celebrations'], 'desc' => ['ro' => 'Petreceri de Revelion, Crăciun și alte sărbători', 'en' => 'New Year, Christmas and other holiday celebration events']],
                ],
            ],
        ];

        $sort = 0;
        foreach ($categories as $cat) {
            $parent = MarketplaceVenueCategory::create([
                'marketplace_client_id' => $mcId,
                'parent_id'             => null,
                'name'                  => $cat['name'],
                'description'           => $cat['desc'],
                'icon'                  => $cat['icon'],
                'color'                 => $cat['color'],
                'sort_order'            => $cat['sort'],
                'is_active'             => true,
                'is_featured'           => false,
            ]);

            foreach ($cat['children'] as $child) {
                MarketplaceVenueCategory::create([
                    'marketplace_client_id' => $mcId,
                    'parent_id'             => $parent->id,
                    'name'                  => $child['name'],
                    'description'           => $child['desc'],
                    'icon'                  => $child['icon'],
                    'color'                 => $child['color'],
                    'sort_order'            => $child['sort'],
                    'is_active'             => true,
                    'is_featured'           => false,
                ]);
            }

            $this->command->line("  ✓ {$cat['icon']} {$cat['name']['ro']} + " . count($cat['children']) . " subcategorii");
        }

        $total = MarketplaceVenueCategory::where('marketplace_client_id', $mcId)->count();
        $this->command->info("Done! {$total} categorii create (9 parinte + 38 copii).");
    }
}
