<?php

namespace Database\Seeders;

use App\Models\VenueCategory;
use App\Models\VenueType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VenueCategoriesAndTypesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'category' => ['ro' => 'Artă & Cultură', 'en' => 'Arts & Culture'],
                'slug' => 'arts-culture',
                'icon' => '🎭',
                'types' => [
                    ['emoji' => '🎭', 'ro' => 'Teatru', 'en' => 'Theater', 'slug' => 'theater'],
                    ['emoji' => '🎭', 'ro' => 'Teatru de păpuși', 'en' => 'Puppet Theater', 'slug' => 'puppet-theater'],
                    ['emoji' => '🎭', 'ro' => 'Teatru de revistă', 'en' => 'Variety Theater', 'slug' => 'variety-theater'],
                    ['emoji' => '🎪', 'ro' => 'Circ', 'en' => 'Circus', 'slug' => 'circus'],
                    ['emoji' => '🎬', 'ro' => 'Cinematograf', 'en' => 'Cinema', 'slug' => 'cinema'],
                    ['emoji' => '🎬', 'ro' => 'Cinematograf drive-in', 'en' => 'Drive-in Cinema', 'slug' => 'drive-in-cinema'],
                    ['emoji' => '🎬', 'ro' => 'Cinematograf IMAX', 'en' => 'IMAX Theater', 'slug' => 'imax-theater'],
                    ['emoji' => '🏛️', 'ro' => 'Operă', 'en' => 'Opera House', 'slug' => 'opera-house'],
                    ['emoji' => '🎻', 'ro' => 'Filarmonică', 'en' => 'Philharmonic Hall', 'slug' => 'philharmonic-hall'],
                    ['emoji' => '🎼', 'ro' => 'Sală de concerte', 'en' => 'Concert Hall', 'slug' => 'concert-hall'],
                    ['emoji' => '🎵', 'ro' => 'Conservator', 'en' => 'Conservatory', 'slug' => 'conservatory'],
                    ['emoji' => '🏛️', 'ro' => 'Centru cultural', 'en' => 'Cultural Center', 'slug' => 'cultural-center'],
                    ['emoji' => '🎨', 'ro' => 'Galerie de artă', 'en' => 'Art Gallery', 'slug' => 'art-gallery'],
                    ['emoji' => '🖼️', 'ro' => 'Muzeu', 'en' => 'Museum', 'slug' => 'museum'],
                    ['emoji' => '📚', 'ro' => 'Bibliotecă', 'en' => 'Library', 'slug' => 'library'],
                ],
            ],
            [
                'category' => ['ro' => 'Muzică & Entertainment', 'en' => 'Music & Entertainment'],
                'slug' => 'music-entertainment',
                'icon' => '🎤',
                'types' => [
                    ['emoji' => '🎤', 'ro' => 'Sală de spectacole', 'en' => 'Performance Hall', 'slug' => 'performance-hall'],
                    ['emoji' => '🎸', 'ro' => 'Club de muzică', 'en' => 'Music Club', 'slug' => 'music-club'],
                    ['emoji' => '🍺', 'ro' => 'Pub cu muzică live', 'en' => 'Live Music Pub', 'slug' => 'live-music-pub'],
                    ['emoji' => '🎧', 'ro' => 'Club de noapte', 'en' => 'Nightclub', 'slug' => 'nightclub'],
                    ['emoji' => '💃', 'ro' => 'Discotecă', 'en' => 'Disco', 'slug' => 'disco'],
                    ['emoji' => '🎹', 'ro' => 'Piano bar', 'en' => 'Piano Bar', 'slug' => 'piano-bar'],
                    ['emoji' => '🎷', 'ro' => 'Club de jazz', 'en' => 'Jazz Club', 'slug' => 'jazz-club'],
                    ['emoji' => '🤘', 'ro' => 'Club de rock', 'en' => 'Rock Club', 'slug' => 'rock-club'],
                    ['emoji' => '🎵', 'ro' => 'Club underground', 'en' => 'Underground Club', 'slug' => 'underground-club'],
                    ['emoji' => '🎶', 'ro' => 'Sală de karaoke', 'en' => 'Karaoke Venue', 'slug' => 'karaoke-venue'],
                    ['emoji' => '🎰', 'ro' => 'Cazinou', 'en' => 'Casino', 'slug' => 'casino'],
                    ['emoji' => '🎭', 'ro' => 'Cabaret', 'en' => 'Cabaret', 'slug' => 'cabaret'],
                    ['emoji' => '😂', 'ro' => 'Club de comedie', 'en' => 'Comedy Club', 'slug' => 'comedy-club'],
                ],
            ],
            [
                'category' => ['ro' => 'Sport & Arene', 'en' => 'Sports & Arenas'],
                'slug' => 'sports-arenas',
                'icon' => '🏟️',
                'types' => [
                    ['emoji' => '🏟️', 'ro' => 'Stadion', 'en' => 'Stadium', 'slug' => 'stadium'],
                    ['emoji' => '🏟️', 'ro' => 'Arenă', 'en' => 'Arena', 'slug' => 'arena'],
                    ['emoji' => '🏟️', 'ro' => 'Arenă multifuncțională', 'en' => 'Multi-purpose Arena', 'slug' => 'multi-purpose-arena'],
                    ['emoji' => '⚽', 'ro' => 'Stadion de fotbal', 'en' => 'Football Stadium', 'slug' => 'football-stadium'],
                    ['emoji' => '🏀', 'ro' => 'Sală de sport', 'en' => 'Sports Hall', 'slug' => 'sports-hall'],
                    ['emoji' => '🎾', 'ro' => 'Teren de tenis', 'en' => 'Tennis Court', 'slug' => 'tennis-court'],
                    ['emoji' => '🏊', 'ro' => 'Complex acvatic', 'en' => 'Aquatic Center', 'slug' => 'aquatic-center'],
                    ['emoji' => '🏒', 'ro' => 'Patinoar', 'en' => 'Ice Rink', 'slug' => 'ice-rink'],
                    ['emoji' => '🏇', 'ro' => 'Hipodrom', 'en' => 'Hippodrome', 'slug' => 'hippodrome'],
                    ['emoji' => '🏎️', 'ro' => 'Circuit auto', 'en' => 'Racing Circuit', 'slug' => 'racing-circuit'],
                    ['emoji' => '🥊', 'ro' => 'Sală de box', 'en' => 'Boxing Arena', 'slug' => 'boxing-arena'],
                    ['emoji' => '⛳', 'ro' => 'Club de golf', 'en' => 'Golf Club', 'slug' => 'golf-club'],
                    ['emoji' => '🎳', 'ro' => 'Bowling', 'en' => 'Bowling Alley', 'slug' => 'bowling-alley'],
                ],
            ],
            [
                'category' => ['ro' => 'HoReCa', 'en' => 'Hospitality'],
                'slug' => 'hospitality',
                'icon' => '🍽️',
                'types' => [
                    ['emoji' => '🍽️', 'ro' => 'Restaurant', 'en' => 'Restaurant', 'slug' => 'restaurant'],
                    ['emoji' => '🍕', 'ro' => 'Pizzerie', 'en' => 'Pizzeria', 'slug' => 'pizzeria'],
                    ['emoji' => '🍺', 'ro' => 'Berărie', 'en' => 'Beer Hall', 'slug' => 'beer-hall'],
                    ['emoji' => '🍷', 'ro' => 'Cramă / Vinărie', 'en' => 'Winery', 'slug' => 'winery'],
                    ['emoji' => '🍸', 'ro' => 'Bar', 'en' => 'Bar', 'slug' => 'bar'],
                    ['emoji' => '🍹', 'ro' => 'Cocktail bar', 'en' => 'Cocktail Bar', 'slug' => 'cocktail-bar'],
                    ['emoji' => '☕', 'ro' => 'Cafenea', 'en' => 'Café', 'slug' => 'cafe'],
                    ['emoji' => '🫖', 'ro' => 'Ceainărie', 'en' => 'Tea House', 'slug' => 'tea-house'],
                    ['emoji' => '🍔', 'ro' => 'Fast food', 'en' => 'Fast Food', 'slug' => 'fast-food'],
                    ['emoji' => '🌮', 'ro' => 'Food court', 'en' => 'Food Court', 'slug' => 'food-court'],
                    ['emoji' => '🏨', 'ro' => 'Hotel', 'en' => 'Hotel', 'slug' => 'hotel'],
                    ['emoji' => '🏩', 'ro' => 'Boutique hotel', 'en' => 'Boutique Hotel', 'slug' => 'boutique-hotel'],
                    ['emoji' => '🛎️', 'ro' => 'Resort', 'en' => 'Resort', 'slug' => 'resort'],
                    ['emoji' => '🏠', 'ro' => 'Pensiune', 'en' => 'Guesthouse', 'slug' => 'guesthouse'],
                    ['emoji' => '🛖', 'ro' => 'Hostel', 'en' => 'Hostel', 'slug' => 'hostel'],
                    ['emoji' => '🏰', 'ro' => 'Castel hotel', 'en' => 'Castle Hotel', 'slug' => 'castle-hotel'],
                ],
            ],
            [
                'category' => ['ro' => 'Business & Conferințe', 'en' => 'Business & Conferences'],
                'slug' => 'business-conferences',
                'icon' => '🏢',
                'types' => [
                    ['emoji' => '🏢', 'ro' => 'Centru de conferințe', 'en' => 'Conference Center', 'slug' => 'conference-center'],
                    ['emoji' => '🏛️', 'ro' => 'Centru de congrese', 'en' => 'Congress Center', 'slug' => 'congress-center'],
                    ['emoji' => '💼', 'ro' => 'Centru de afaceri', 'en' => 'Business Center', 'slug' => 'business-center'],
                    ['emoji' => '🖥️', 'ro' => 'Spațiu de coworking', 'en' => 'Coworking Space', 'slug' => 'coworking-space'],
                    ['emoji' => '🎓', 'ro' => 'Centru de training', 'en' => 'Training Center', 'slug' => 'training-center'],
                    ['emoji' => '📊', 'ro' => 'Sală de ședințe', 'en' => 'Meeting Room', 'slug' => 'meeting-room'],
                    ['emoji' => '🎤', 'ro' => 'Auditorium', 'en' => 'Auditorium', 'slug' => 'auditorium'],
                    ['emoji' => '🏫', 'ro' => 'Centru de evenimente corporative', 'en' => 'Corporate Event Center', 'slug' => 'corporate-event-center'],
                    ['emoji' => '🏭', 'ro' => 'Spațiu industrial reconvertit', 'en' => 'Converted Industrial Space', 'slug' => 'converted-industrial-space'],
                ],
            ],
            [
                'category' => ['ro' => 'Educație', 'en' => 'Education'],
                'slug' => 'education',
                'icon' => '🎓',
                'types' => [
                    ['emoji' => '🎓', 'ro' => 'Universitate', 'en' => 'University', 'slug' => 'university'],
                    ['emoji' => '🏫', 'ro' => 'Școală', 'en' => 'School', 'slug' => 'school'],
                    ['emoji' => '📚', 'ro' => 'Campus universitar', 'en' => 'University Campus', 'slug' => 'university-campus'],
                    ['emoji' => '🔬', 'ro' => 'Laborator', 'en' => 'Laboratory', 'slug' => 'laboratory'],
                    ['emoji' => '🎭', 'ro' => 'Aula magna', 'en' => 'Great Hall', 'slug' => 'great-hall'],
                    ['emoji' => '📖', 'ro' => 'Amfiteatru', 'en' => 'Amphitheater', 'slug' => 'amphitheater'],
                    ['emoji' => '🏛️', 'ro' => 'Academie', 'en' => 'Academy', 'slug' => 'academy'],
                ],
            ],
            [
                'category' => ['ro' => 'Outdoor & Natură', 'en' => 'Outdoor & Nature'],
                'slug' => 'outdoor-nature',
                'icon' => '🌳',
                'types' => [
                    ['emoji' => '🌳', 'ro' => 'Parc', 'en' => 'Park', 'slug' => 'park'],
                    ['emoji' => '🌲', 'ro' => 'Pădure', 'en' => 'Forest', 'slug' => 'forest'],
                    ['emoji' => '🏖️', 'ro' => 'Plajă', 'en' => 'Beach', 'slug' => 'beach'],
                    ['emoji' => '⛰️', 'ro' => 'Munte', 'en' => 'Mountain', 'slug' => 'mountain'],
                    ['emoji' => '🏕️', 'ro' => 'Camping', 'en' => 'Campground', 'slug' => 'campground'],
                    ['emoji' => '🎪', 'ro' => 'Teren de festival', 'en' => 'Festival Grounds', 'slug' => 'festival-grounds'],
                    ['emoji' => '🌾', 'ro' => 'Câmp deschis', 'en' => 'Open Field', 'slug' => 'open-field'],
                    ['emoji' => '🏞️', 'ro' => 'Rezervație naturală', 'en' => 'Nature Reserve', 'slug' => 'nature-reserve'],
                    ['emoji' => '🌻', 'ro' => 'Grădină botanică', 'en' => 'Botanical Garden', 'slug' => 'botanical-garden'],
                    ['emoji' => '🦁', 'ro' => 'Grădină zoologică', 'en' => 'Zoo', 'slug' => 'zoo'],
                    ['emoji' => '🎡', 'ro' => 'Parc de distracții', 'en' => 'Amusement Park', 'slug' => 'amusement-park'],
                    ['emoji' => '🎢', 'ro' => 'Aquapark', 'en' => 'Water Park', 'slug' => 'water-park'],
                    ['emoji' => '⛲', 'ro' => 'Piață publică', 'en' => 'Public Square', 'slug' => 'public-square'],
                    ['emoji' => '🌅', 'ro' => 'Promenadă', 'en' => 'Promenade', 'slug' => 'promenade'],
                    ['emoji' => '🏝️', 'ro' => 'Insulă', 'en' => 'Island', 'slug' => 'island'],
                    ['emoji' => '🌊', 'ro' => 'Port / Marina', 'en' => 'Marina', 'slug' => 'marina'],
                    ['emoji' => '⛵', 'ro' => 'Yacht club', 'en' => 'Yacht Club', 'slug' => 'yacht-club'],
                ],
            ],
            [
                'category' => ['ro' => 'Istoric & Patrimoniu', 'en' => 'Historic & Heritage'],
                'slug' => 'historic-heritage',
                'icon' => '🏰',
                'types' => [
                    ['emoji' => '🏰', 'ro' => 'Castel', 'en' => 'Castle', 'slug' => 'castle'],
                    ['emoji' => '🏯', 'ro' => 'Cetate', 'en' => 'Fortress', 'slug' => 'fortress'],
                    ['emoji' => '🏛️', 'ro' => 'Palat', 'en' => 'Palace', 'slug' => 'palace'],
                    ['emoji' => '⛪', 'ro' => 'Biserică', 'en' => 'Church', 'slug' => 'church'],
                    ['emoji' => '🕌', 'ro' => 'Moschee', 'en' => 'Mosque', 'slug' => 'mosque'],
                    ['emoji' => '🕍', 'ro' => 'Sinagogă', 'en' => 'Synagogue', 'slug' => 'synagogue'],
                    ['emoji' => '⛩️', 'ro' => 'Templu', 'en' => 'Temple', 'slug' => 'temple'],
                    ['emoji' => '🏛️', 'ro' => 'Mănăstire', 'en' => 'Monastery', 'slug' => 'monastery'],
                    ['emoji' => '🏚️', 'ro' => 'Conac', 'en' => 'Manor House', 'slug' => 'manor-house'],
                    ['emoji' => '🏠', 'ro' => 'Casă memorială', 'en' => 'Memorial House', 'slug' => 'memorial-house'],
                    ['emoji' => '🗿', 'ro' => 'Sit arheologic', 'en' => 'Archaeological Site', 'slug' => 'archaeological-site'],
                    ['emoji' => '🏛️', 'ro' => 'Monument istoric', 'en' => 'Historic Monument', 'slug' => 'historic-monument'],
                    ['emoji' => '🏰', 'ro' => 'Ruine', 'en' => 'Ruins', 'slug' => 'ruins'],
                ],
            ],
            [
                'category' => ['ro' => 'Evenimente & Petreceri', 'en' => 'Events & Parties'],
                'slug' => 'events-parties',
                'icon' => '🎊',
                'types' => [
                    ['emoji' => '🎊', 'ro' => 'Sală de evenimente', 'en' => 'Event Hall', 'slug' => 'event-hall'],
                    ['emoji' => '💒', 'ro' => 'Sală de nunți', 'en' => 'Wedding Venue', 'slug' => 'wedding-venue'],
                    ['emoji' => '🎈', 'ro' => 'Sală de petreceri', 'en' => 'Party Venue', 'slug' => 'party-venue'],
                    ['emoji' => '🎂', 'ro' => 'Sală de banchete', 'en' => 'Banquet Hall', 'slug' => 'banquet-hall'],
                    ['emoji' => '🥂', 'ro' => 'Sală de recepții', 'en' => 'Reception Hall', 'slug' => 'reception-hall'],
                    ['emoji' => '🎉', 'ro' => 'Centru de petreceri pentru copii', 'en' => 'Kids Party Center', 'slug' => 'kids-party-center'],
                    ['emoji' => '🎪', 'ro' => 'Cort de evenimente', 'en' => 'Event Tent', 'slug' => 'event-tent'],
                    ['emoji' => '🚢', 'ro' => 'Vas de croazieră', 'en' => 'Cruise Ship', 'slug' => 'cruise-ship'],
                    ['emoji' => '🚂', 'ro' => 'Tren turistic', 'en' => 'Tourist Train', 'slug' => 'tourist-train'],
                    ['emoji' => '🎠', 'ro' => 'Carusel / Bâlci', 'en' => 'Fairground', 'slug' => 'fairground'],
                ],
            ],
            [
                'category' => ['ro' => 'Wellness & Spa', 'en' => 'Wellness & Spa'],
                'slug' => 'wellness-spa',
                'icon' => '🧘',
                'types' => [
                    ['emoji' => '🧘', 'ro' => 'Studio de yoga', 'en' => 'Yoga Studio', 'slug' => 'yoga-studio'],
                    ['emoji' => '🏋️', 'ro' => 'Sală de fitness', 'en' => 'Gym / Fitness Center', 'slug' => 'gym'],
                    ['emoji' => '💆', 'ro' => 'Spa', 'en' => 'Spa', 'slug' => 'spa'],
                    ['emoji' => '🧖', 'ro' => 'Centru de wellness', 'en' => 'Wellness Center', 'slug' => 'wellness-center'],
                    ['emoji' => '♨️', 'ro' => 'Băi termale', 'en' => 'Thermal Baths', 'slug' => 'thermal-baths'],
                    ['emoji' => '🏊', 'ro' => 'Piscină', 'en' => 'Swimming Pool', 'slug' => 'swimming-pool'],
                    ['emoji' => '🎿', 'ro' => 'Stațiune de schi', 'en' => 'Ski Resort', 'slug' => 'ski-resort'],
                ],
            ],
            [
                'category' => ['ro' => 'Comercial', 'en' => 'Commercial'],
                'slug' => 'commercial',
                'icon' => '🏬',
                'types' => [
                    ['emoji' => '🏬', 'ro' => 'Mall / Centru comercial', 'en' => 'Shopping Mall', 'slug' => 'shopping-mall'],
                    ['emoji' => '🏪', 'ro' => 'Magazin', 'en' => 'Store', 'slug' => 'store'],
                    ['emoji' => '🛒', 'ro' => 'Hypermarket', 'en' => 'Hypermarket', 'slug' => 'hypermarket'],
                    ['emoji' => '🏢', 'ro' => 'Showroom', 'en' => 'Showroom', 'slug' => 'showroom'],
                    ['emoji' => '🎪', 'ro' => 'Centru expozițional', 'en' => 'Exhibition Center', 'slug' => 'exhibition-center'],
                    ['emoji' => '🏭', 'ro' => 'Fabrică (tururi)', 'en' => 'Factory (Tours)', 'slug' => 'factory-tours'],
                    ['emoji' => '🍇', 'ro' => 'Vie / Podgorie', 'en' => 'Vineyard', 'slug' => 'vineyard'],
                    ['emoji' => '🌾', 'ro' => 'Fermă', 'en' => 'Farm', 'slug' => 'farm'],
                ],
            ],
            [
                'category' => ['ro' => 'Transport & Infrastructură', 'en' => 'Transport & Infrastructure'],
                'slug' => 'transport-infrastructure',
                'icon' => '🚉',
                'types' => [
                    ['emoji' => '🚉', 'ro' => 'Gară', 'en' => 'Train Station', 'slug' => 'train-station'],
                    ['emoji' => '✈️', 'ro' => 'Aeroport', 'en' => 'Airport', 'slug' => 'airport'],
                    ['emoji' => '🚢', 'ro' => 'Port', 'en' => 'Port', 'slug' => 'port'],
                    ['emoji' => '🚏', 'ro' => 'Autogară', 'en' => 'Bus Station', 'slug' => 'bus-station'],
                    ['emoji' => '🅿️', 'ro' => 'Parcare (evenimente)', 'en' => 'Parking Lot (Events)', 'slug' => 'parking-lot'],
                    ['emoji' => '🌉', 'ro' => 'Pod', 'en' => 'Bridge', 'slug' => 'bridge'],
                    ['emoji' => '🚇', 'ro' => 'Stație de metrou', 'en' => 'Metro Station', 'slug' => 'metro-station'],
                ],
            ],
            [
                'category' => ['ro' => 'Rezidențial & Privat', 'en' => 'Residential & Private'],
                'slug' => 'residential-private',
                'icon' => '🏠',
                'types' => [
                    ['emoji' => '🏠', 'ro' => 'Casă privată', 'en' => 'Private House', 'slug' => 'private-house'],
                    ['emoji' => '🏡', 'ro' => 'Vilă', 'en' => 'Villa', 'slug' => 'villa'],
                    ['emoji' => '🏘️', 'ro' => 'Complex rezidențial', 'en' => 'Residential Complex', 'slug' => 'residential-complex'],
                    ['emoji' => '🌳', 'ro' => 'Grădină privată', 'en' => 'Private Garden', 'slug' => 'private-garden'],
                    ['emoji' => '🏊', 'ro' => 'Piscină privată', 'en' => 'Private Pool', 'slug' => 'private-pool'],
                    ['emoji' => '🍃', 'ro' => 'Terasă', 'en' => 'Terrace', 'slug' => 'terrace'],
                    ['emoji' => '🌿', 'ro' => 'Curte interioară', 'en' => 'Courtyard', 'slug' => 'courtyard'],
                    ['emoji' => '🏕️', 'ro' => 'Cabană', 'en' => 'Cabin', 'slug' => 'cabin'],
                    ['emoji' => '🛖', 'ro' => 'Bungalow', 'en' => 'Bungalow', 'slug' => 'bungalow'],
                ],
            ],
            [
                'category' => ['ro' => 'Gaming & Tech', 'en' => 'Gaming & Tech'],
                'slug' => 'gaming-tech',
                'icon' => '🎮',
                'types' => [
                    ['emoji' => '🎮', 'ro' => 'Sală de gaming', 'en' => 'Gaming Arena', 'slug' => 'gaming-arena'],
                    ['emoji' => '🕹️', 'ro' => 'Arcade', 'en' => 'Arcade', 'slug' => 'arcade'],
                    ['emoji' => '🖥️', 'ro' => 'Internet café', 'en' => 'Internet Café', 'slug' => 'internet-cafe'],
                    ['emoji' => '🥽', 'ro' => 'Centru VR', 'en' => 'VR Center', 'slug' => 'vr-center'],
                    ['emoji' => '🤖', 'ro' => 'Centru tech / inovație', 'en' => 'Tech / Innovation Hub', 'slug' => 'tech-hub'],
                    ['emoji' => '🎯', 'ro' => 'Escape room', 'en' => 'Escape Room', 'slug' => 'escape-room'],
                    ['emoji' => '🎱', 'ro' => 'Sală de biliard', 'en' => 'Billiard Hall', 'slug' => 'billiard-hall'],
                ],
            ],
        ];

        $categorySortOrder = 0;

        foreach ($data as $categoryData) {
            $categorySortOrder++;

            // Create or update category
            $category = VenueCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['category'],
                    'icon' => $categoryData['icon'],
                    'sort_order' => $categorySortOrder,
                ]
            );

            $typeSortOrder = 0;

            foreach ($categoryData['types'] as $typeData) {
                $typeSortOrder++;

                // Create or update venue type
                VenueType::updateOrCreate(
                    ['slug' => $typeData['slug']],
                    [
                        'venue_category_id' => $category->id,
                        'name' => [
                            'en' => $typeData['en'],
                            'ro' => $typeData['ro'],
                        ],
                        'icon' => $typeData['emoji'],
                        'sort_order' => $typeSortOrder,
                    ]
                );
            }
        }

        $this->command->info('Created/updated ' . $categorySortOrder . ' venue categories and ' . VenueType::count() . ' venue types.');
    }
}
