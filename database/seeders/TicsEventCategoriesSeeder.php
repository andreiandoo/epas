<?php

namespace Database\Seeders;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEventCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicsEventCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $marketplace = MarketplaceClient::find(2);

        if (! $marketplace) {
            $this->command->error('Marketplace client cu id=2 nu a fost găsit. Aborting.');
            return;
        }

        $mcId = 2;

        $this->command->info("Seeding event categories for marketplace: {$marketplace->name} (id={$mcId})");

        // Delete existing event categories for this marketplace
        MarketplaceEventCategory::where('marketplace_client_id', $mcId)->forceDelete();

        $categories = [
            [
                'slug'       => 'concerte',
                'icon_emoji' => '🎵',
                'color'      => '#7C3AED',
                'sort'       => 1,
                'name'       => ['ro' => 'Concerte', 'en' => 'Concerts'],
                'desc'       => ['ro' => 'Concerte live de toate genurile muzicale', 'en' => 'Live concerts across all music genres'],
                'children'   => [
                    ['slug' => 'rock', 'icon_emoji' => '🎸', 'color' => '#6D28D9', 'sort' => 1, 'name' => ['ro' => 'Rock & Alternative', 'en' => 'Rock & Alternative'], 'desc' => ['ro' => 'Concerte rock, alternative și metal', 'en' => 'Rock, alternative and metal concerts']],
                    ['slug' => 'pop', 'icon_emoji' => '🎤', 'color' => '#8B5CF6', 'sort' => 2, 'name' => ['ro' => 'Pop', 'en' => 'Pop'], 'desc' => ['ro' => 'Concerte pop și muzică mainstream', 'en' => 'Pop and mainstream music concerts']],
                    ['slug' => 'electronic', 'icon_emoji' => '🎧', 'color' => '#A78BFA', 'sort' => 3, 'name' => ['ro' => 'Electronic / DJ', 'en' => 'Electronic / DJ'], 'desc' => ['ro' => 'Muzică electronică, techno, house și seri de club', 'en' => 'Electronic music, techno, house and club nights']],
                    ['slug' => 'hip-hop', 'icon_emoji' => '🎵', 'color' => '#7C3AED', 'sort' => 4, 'name' => ['ro' => 'Hip-Hop & Rap', 'en' => 'Hip-Hop & Rap'], 'desc' => ['ro' => 'Concerte hip-hop, rap și R&B', 'en' => 'Hip-hop, rap and R&B concerts']],
                    ['slug' => 'jazz-blues', 'icon_emoji' => '🎷', 'color' => '#5B21B6', 'sort' => 5, 'name' => ['ro' => 'Jazz & Blues', 'en' => 'Jazz & Blues'], 'desc' => ['ro' => 'Concerte jazz, blues și soul', 'en' => 'Jazz, blues and soul concerts']],
                    ['slug' => 'clasica', 'icon_emoji' => '🎻', 'color' => '#4C1D95', 'sort' => 6, 'name' => ['ro' => 'Clasică & Operă', 'en' => 'Classical & Opera'], 'desc' => ['ro' => 'Concerte simfonice, recitaluri și spectacole de operă', 'en' => 'Symphonic concerts, recitals and opera performances']],
                    ['slug' => 'folk', 'icon_emoji' => '🪗', 'color' => '#9333EA', 'sort' => 7, 'name' => ['ro' => 'Folk & Populară', 'en' => 'Folk & Traditional'], 'desc' => ['ro' => 'Muzică folk, populară și tradiţională', 'en' => 'Folk, traditional and world music concerts']],
                    ['slug' => 'reggae', 'icon_emoji' => '🌴', 'color' => '#7E22CE', 'sort' => 8, 'name' => ['ro' => 'Reggae & World', 'en' => 'Reggae & World Music'], 'desc' => ['ro' => 'Reggae, world music și muzică etnică', 'en' => 'Reggae, world music and ethnic sounds']],
                ],
            ],
            [
                'slug'       => 'festivaluri',
                'icon_emoji' => '🎪',
                'color'      => '#F59E0B',
                'sort'       => 2,
                'name'       => ['ro' => 'Festivaluri', 'en' => 'Festivals'],
                'desc'       => ['ro' => 'Festivaluri de muzică, artă, film și cultură', 'en' => 'Music, arts, film and culture festivals'],
                'children'   => [
                    ['slug' => 'festivaluri-muzica', 'icon_emoji' => '🎶', 'color' => '#D97706', 'sort' => 1, 'name' => ['ro' => 'Festivaluri Muzică', 'en' => 'Music Festivals'], 'desc' => ['ro' => 'Festivaluri cu mai mulți artiști și zile multiple', 'en' => 'Multi-artist, multi-day music festivals']],
                    ['slug' => 'festivaluri-film', 'icon_emoji' => '🎬', 'color' => '#B45309', 'sort' => 2, 'name' => ['ro' => 'Festivaluri Film', 'en' => 'Film Festivals'], 'desc' => ['ro' => 'Festivaluri și proiecții de film', 'en' => 'Film festivals and screenings']],
                    ['slug' => 'festivaluri-arta', 'icon_emoji' => '🎨', 'color' => '#92400E', 'sort' => 3, 'name' => ['ro' => 'Festivaluri Artă', 'en' => 'Arts Festivals'], 'desc' => ['ro' => 'Festivaluri de artă stradală și cultură', 'en' => 'Street art and cultural festivals']],
                    ['slug' => 'festivaluri-gastronomie', 'icon_emoji' => '🍔', 'color' => '#F59E0B', 'sort' => 4, 'name' => ['ro' => 'Street Food & Gastronomie', 'en' => 'Street Food & Gastronomy'], 'desc' => ['ro' => 'Festivaluri culinare, street food și food truck events', 'en' => 'Culinary festivals, street food and food truck events']],
                ],
            ],
            [
                'slug'       => 'stand-up',
                'icon_emoji' => '😂',
                'color'      => '#F97316',
                'sort'       => 3,
                'name'       => ['ro' => 'Stand-up Comedy', 'en' => 'Stand-up Comedy'],
                'desc'       => ['ro' => 'Stand-up comedy, improvizație și umor', 'en' => 'Stand-up comedy, improv and humour shows'],
                'children'   => [
                    ['slug' => 'stand-up-comedy', 'icon_emoji' => '🎤', 'color' => '#EA580C', 'sort' => 1, 'name' => ['ro' => 'Stand-up Solo', 'en' => 'Solo Stand-up'], 'desc' => ['ro' => 'Spectacole solo de stand-up comedy', 'en' => 'Solo stand-up comedy specials']],
                    ['slug' => 'improvizatie', 'icon_emoji' => '🎭', 'color' => '#C2410C', 'sort' => 2, 'name' => ['ro' => 'Improvizație', 'en' => 'Improv Comedy'], 'desc' => ['ro' => 'Spectacole de teatru de improvizație și comedy improv', 'en' => 'Improv theatre and comedy improv shows']],
                    ['slug' => 'comedy-gala', 'icon_emoji' => '🎊', 'color' => '#F97316', 'sort' => 3, 'name' => ['ro' => 'Gale de Comedy', 'en' => 'Comedy Galas'], 'desc' => ['ro' => 'Gale cu mai mulți comedianți', 'en' => 'Multi-comedian comedy galas']],
                ],
            ],
            [
                'slug'       => 'teatru',
                'icon_emoji' => '🎭',
                'color'      => '#EC4899',
                'sort'       => 4,
                'name'       => ['ro' => 'Teatru', 'en' => 'Theatre'],
                'desc'       => ['ro' => 'Spectacole de teatru, balet și artele spectacolului', 'en' => 'Theatre, ballet and performing arts'],
                'children'   => [
                    ['slug' => 'drama', 'icon_emoji' => '🎭', 'color' => '#DB2777', 'sort' => 1, 'name' => ['ro' => 'Dramă & Tragedie', 'en' => 'Drama & Tragedy'], 'desc' => ['ro' => 'Piese clasice și contemporane de dramă', 'en' => 'Classic and contemporary drama plays']],
                    ['slug' => 'comedie-teatru', 'icon_emoji' => '😄', 'color' => '#EC4899', 'sort' => 2, 'name' => ['ro' => 'Comedie', 'en' => 'Comedy Theatre'], 'desc' => ['ro' => 'Piese de comedie și umor pe scenă', 'en' => 'Comedy and humour on stage']],
                    ['slug' => 'musical', 'icon_emoji' => '🎶', 'color' => '#BE185D', 'sort' => 3, 'name' => ['ro' => 'Musical', 'en' => 'Musical'], 'desc' => ['ro' => 'Spectacole de musical și comedii muzicale', 'en' => 'Musical and music comedy shows']],
                    ['slug' => 'balet-dans', 'icon_emoji' => '🩰', 'color' => '#9D174D', 'sort' => 4, 'name' => ['ro' => 'Balet & Dans', 'en' => 'Ballet & Dance'], 'desc' => ['ro' => 'Spectacole de balet, dans contemporan și folcloric', 'en' => 'Ballet, contemporary and folk dance performances']],
                    ['slug' => 'opera', 'icon_emoji' => '🎻', 'color' => '#831843', 'sort' => 5, 'name' => ['ro' => 'Operă & Operetă', 'en' => 'Opera & Operetta'], 'desc' => ['ro' => 'Spectacole de operă și operetă', 'en' => 'Opera and operetta performances']],
                ],
            ],
            [
                'slug'       => 'sport',
                'icon_emoji' => '⚽',
                'color'      => '#10B981',
                'sort'       => 5,
                'name'       => ['ro' => 'Sport', 'en' => 'Sports'],
                'desc'       => ['ro' => 'Competiții sportive de toate tipurile', 'en' => 'Sports competitions of all kinds'],
                'children'   => [
                    ['slug' => 'fotbal', 'icon_emoji' => '⚽', 'color' => '#059669', 'sort' => 1, 'name' => ['ro' => 'Fotbal', 'en' => 'Football'], 'desc' => ['ro' => 'Meciuri și turnee de fotbal', 'en' => 'Football matches and tournaments']],
                    ['slug' => 'tenis', 'icon_emoji' => '🎾', 'color' => '#34D399', 'sort' => 2, 'name' => ['ro' => 'Tenis', 'en' => 'Tennis'], 'desc' => ['ro' => 'Meciuri și turnee de tenis', 'en' => 'Tennis matches and tournaments']],
                    ['slug' => 'baschet', 'icon_emoji' => '🏀', 'color' => '#047857', 'sort' => 3, 'name' => ['ro' => 'Baschet', 'en' => 'Basketball'], 'desc' => ['ro' => 'Meciuri și competiții de baschet', 'en' => 'Basketball matches and competitions']],
                    ['slug' => 'box-arte-martiale', 'icon_emoji' => '🥊', 'color' => '#065F46', 'sort' => 4, 'name' => ['ro' => 'Box & Arte Marțiale', 'en' => 'Boxing & Martial Arts'], 'desc' => ['ro' => 'Gale de box, MMA, kickboxing și arte marțiale', 'en' => 'Boxing, MMA, kickboxing and martial arts events']],
                    ['slug' => 'handbal-volei', 'icon_emoji' => '🏐', 'color' => '#10B981', 'sort' => 5, 'name' => ['ro' => 'Handbal & Volei', 'en' => 'Handball & Volleyball'], 'desc' => ['ro' => 'Meciuri de handbal, volei și sporturi de echipă', 'en' => 'Handball, volleyball and team sports matches']],
                    ['slug' => 'fitness-wellness', 'icon_emoji' => '🏋️', 'color' => '#6EE7B7', 'sort' => 6, 'name' => ['ro' => 'Fitness & Wellness', 'en' => 'Fitness & Wellness'], 'desc' => ['ro' => 'Competiții de fitness, yoga și evenimente wellness', 'en' => 'Fitness competitions, yoga and wellness events']],
                ],
            ],
            [
                'slug'       => 'arta-muzee',
                'icon_emoji' => '🎨',
                'color'      => '#06B6D4',
                'sort'       => 6,
                'name'       => ['ro' => 'Artă & Muzee', 'en' => 'Art & Museums'],
                'desc'       => ['ro' => 'Expoziții, vernisaje, muzee și arte vizuale', 'en' => 'Exhibitions, gallery openings, museums and visual arts'],
                'children'   => [
                    ['slug' => 'expozitii', 'icon_emoji' => '🖼️', 'color' => '#0891B2', 'sort' => 1, 'name' => ['ro' => 'Expoziții', 'en' => 'Exhibitions'], 'desc' => ['ro' => 'Expoziții de artă, fotografie și design', 'en' => 'Art, photography and design exhibitions']],
                    ['slug' => 'vernisaje', 'icon_emoji' => '🎨', 'color' => '#0E7490', 'sort' => 2, 'name' => ['ro' => 'Vernisaje', 'en' => 'Gallery Openings'], 'desc' => ['ro' => 'Vernisaje și deschideri de galerii de artă', 'en' => 'Art gallery openings and vernissages']],
                    ['slug' => 'muzee', 'icon_emoji' => '🏛️', 'color' => '#155E75', 'sort' => 3, 'name' => ['ro' => 'Muzee', 'en' => 'Museums'], 'desc' => ['ro' => 'Vizite și evenimente la muzee', 'en' => 'Museum visits and events']],
                    ['slug' => 'instalatii', 'icon_emoji' => '✨', 'color' => '#06B6D4', 'sort' => 4, 'name' => ['ro' => 'Instalații & Performance', 'en' => 'Installations & Performance Art'], 'desc' => ['ro' => 'Instalații artistice și arte performative', 'en' => 'Art installations and performance art events']],
                ],
            ],
            [
                'slug'       => 'familie',
                'icon_emoji' => '👨‍👩‍👧',
                'color'      => '#F472B6',
                'sort'       => 7,
                'name'       => ['ro' => 'Familie', 'en' => 'Family'],
                'desc'       => ['ro' => 'Activități și spectacole pentru întreaga familie', 'en' => 'Activities and shows for the whole family'],
                'children'   => [
                    ['slug' => 'spectacole-copii', 'icon_emoji' => '🧸', 'color' => '#EC4899', 'sort' => 1, 'name' => ['ro' => 'Spectacole Copii', 'en' => 'Kids Shows'], 'desc' => ['ro' => 'Spectacole de animație, teatru de păpuși și povești', 'en' => 'Animation, puppet theatre and story-telling shows for kids']],
                    ['slug' => 'ateliere-creative', 'icon_emoji' => '🎨', 'color' => '#DB2777', 'sort' => 2, 'name' => ['ro' => 'Ateliere Creative', 'en' => 'Creative Workshops'], 'desc' => ['ro' => 'Ateliere de pictură, ceramică și artă pentru copii', 'en' => 'Painting, ceramics and art workshops for kids']],
                    ['slug' => 'parcuri-distractii', 'icon_emoji' => '🎡', 'color' => '#BE185D', 'sort' => 3, 'name' => ['ro' => 'Parcuri & Distracție', 'en' => 'Parks & Fun'], 'desc' => ['ro' => 'Activități la parcuri de distracții și zone de joacă', 'en' => 'Activities at amusement parks and playgrounds']],
                ],
            ],
            [
                'slug'       => 'business',
                'icon_emoji' => '💼',
                'color'      => '#3B82F6',
                'sort'       => 8,
                'name'       => ['ro' => 'Business', 'en' => 'Business'],
                'desc'       => ['ro' => 'Conferințe, workshop-uri și evenimente profesionale', 'en' => 'Conferences, workshops and professional events'],
                'children'   => [
                    ['slug' => 'conferinte', 'icon_emoji' => '🎤', 'color' => '#2563EB', 'sort' => 1, 'name' => ['ro' => 'Conferințe & Summit-uri', 'en' => 'Conferences & Summits'], 'desc' => ['ro' => 'Conferințe de business, summit-uri și panel-uri', 'en' => 'Business conferences, summits and panels']],
                    ['slug' => 'workshopuri', 'icon_emoji' => '📚', 'color' => '#1D4ED8', 'sort' => 2, 'name' => ['ro' => 'Workshop-uri & Training', 'en' => 'Workshops & Training'], 'desc' => ['ro' => 'Workshop-uri practice și sesiuni de training', 'en' => 'Practical workshops and professional training']],
                    ['slug' => 'networking', 'icon_emoji' => '🤝', 'color' => '#1E40AF', 'sort' => 3, 'name' => ['ro' => 'Networking', 'en' => 'Networking'], 'desc' => ['ro' => 'Evenimente de networking profesional', 'en' => 'Professional networking events']],
                    ['slug' => 'tech-hackathoane', 'icon_emoji' => '💻', 'color' => '#3B82F6', 'sort' => 4, 'name' => ['ro' => 'Tech & Hackathoane', 'en' => 'Tech & Hackathons'], 'desc' => ['ro' => 'Conferințe tech, hackathoane și startup events', 'en' => 'Tech conferences, hackathons and startup events']],
                ],
            ],
            [
                'slug'       => 'educatie',
                'icon_emoji' => '🎓',
                'color'      => '#8B5CF6',
                'sort'       => 9,
                'name'       => ['ro' => 'Educație', 'en' => 'Education'],
                'desc'       => ['ro' => 'Cursuri, seminarii și activități educative', 'en' => 'Courses, seminars and educational activities'],
                'children'   => [
                    ['slug' => 'cursuri', 'icon_emoji' => '📖', 'color' => '#7C3AED', 'sort' => 1, 'name' => ['ro' => 'Cursuri & Seminarii', 'en' => 'Courses & Seminars'], 'desc' => ['ro' => 'Cursuri de specializare și seminarii educative', 'en' => 'Specialization courses and educational seminars']],
                    ['slug' => 'lansari-carte', 'icon_emoji' => '📚', 'color' => '#6D28D9', 'sort' => 2, 'name' => ['ro' => 'Lansări de Carte', 'en' => 'Book Launches'], 'desc' => ['ro' => 'Lansări de carte și întâlniri cu autorii', 'en' => 'Book launches and author meet-and-greets']],
                    ['slug' => 'tedx-talks', 'icon_emoji' => '💡', 'color' => '#5B21B6', 'sort' => 3, 'name' => ['ro' => 'Talks & Dezbateri', 'en' => 'Talks & Debates'], 'desc' => ['ro' => 'TEDx, dezbateri și prezentări publice', 'en' => 'TEDx talks, debates and public presentations']],
                ],
            ],
        ];

        $totalParents  = 0;
        $totalChildren = 0;

        foreach ($categories as $cat) {
            $parent = MarketplaceEventCategory::create([
                'marketplace_client_id' => $mcId,
                'parent_id'             => null,
                'name'                  => $cat['name'],
                'slug'                  => $cat['slug'],
                'description'           => $cat['desc'],
                'icon_emoji'            => $cat['icon_emoji'],
                'color'                 => $cat['color'],
                'sort_order'            => $cat['sort'],
                'is_visible'            => true,
                'is_featured'           => false,
            ]);

            $totalParents++;

            foreach ($cat['children'] as $child) {
                MarketplaceEventCategory::create([
                    'marketplace_client_id' => $mcId,
                    'parent_id'             => $parent->id,
                    'name'                  => $child['name'],
                    'slug'                  => $child['slug'],
                    'description'           => $child['desc'],
                    'icon_emoji'            => $child['icon_emoji'],
                    'color'                 => $child['color'],
                    'sort_order'            => $child['sort'],
                    'is_visible'            => true,
                    'is_featured'           => false,
                ]);

                $totalChildren++;
            }

            $this->command->line("  ✓ {$cat['icon_emoji']} {$cat['name']['ro']} + " . count($cat['children']) . " subcategorii");
        }

        $this->command->info("Done! {$totalParents} categorii parinte + {$totalChildren} categorii copil create.");
    }
}
