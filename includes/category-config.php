<?php
/**
 * Ambilet - Centralized Category & Genre Configuration
 *
 * Hero images, descriptions, icons and metadata for all categories and genres.
 * All images sourced from Unsplash (free to use).
 */

// =============================================================================
// CATEGORIES CONFIGURATION
// =============================================================================
$CATEGORIES = [
    'concerte' => [
        'name' => 'Concerte',
        'slug' => 'concerte',
        'icon' => '🎸',
        'description' => 'Descopera cele mai tari concerte din Romania. De la artisti internationali la talente locale, gaseste bilete pentru show-uri memorabile.',
        'hero_image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1920&q=80', // Concert crowd with lights - Photo by Yvette de Wit
        'hero_image_credit' => 'Photo by Yvette de Wit on Unsplash',
        'meta_title' => 'Bilete Concerte Romania | Ambilet',
        'meta_description' => 'Cumpara bilete online pentru concerte in Romania. Artisti internationali si locali, rock, pop, jazz si multe altele.',
        'color' => '#A51C30',
    ],
    'festivaluri' => [
        'name' => 'Festivaluri',
        'slug' => 'festivaluri',
        'icon' => '🎪',
        'description' => 'Cele mai mari festivaluri de muzica din Romania. De la Untold si Electric Castle la festivaluri locale, traieste experiente unice.',
        'hero_image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=1920&q=80', // Festival crowd at sunset - Photo by Aditya Chinchure
        'hero_image_credit' => 'Photo by Aditya Chinchure on Unsplash',
        'meta_title' => 'Bilete Festivaluri Romania | Ambilet',
        'meta_description' => 'Bilete pentru cele mai mari festivaluri din Romania. Untold, Electric Castle, Neversea si multe altele.',
        'color' => '#E67E22',
    ],
    'teatru' => [
        'name' => 'Teatru',
        'slug' => 'teatru',
        'icon' => '🎭',
        'description' => 'Spectacole de teatru, opere si balet. Descopera productii clasice si contemporane pe cele mai importante scene din Romania.',
        'hero_image' => 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1920&q=80', // Theater stage with red curtains - Photo by Kyle Head
        'hero_image_credit' => 'Photo by Kyle Head on Unsplash',
        'meta_title' => 'Bilete Teatru Romania | Ambilet',
        'meta_description' => 'Bilete pentru spectacole de teatru, opera si balet in Romania. Cele mai bune productii pe scenele romanesti.',
        'color' => '#8B1728',
    ],
    'stand-up' => [
        'name' => 'Stand-up Comedy',
        'slug' => 'stand-up',
        'icon' => '😂',
        'description' => 'Show-uri de stand-up comedy cu cei mai amuzanti comedianti. Rade cu pofta la cele mai tari spectacole de comedie din Romania.',
        'hero_image' => 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=1920&q=80', // Comedy microphone stage - Photo by Bogomil Mihaylov
        'hero_image_credit' => 'Photo by Bogomil Mihaylov on Unsplash',
        'meta_title' => 'Bilete Stand-up Comedy | Ambilet',
        'meta_description' => 'Bilete pentru show-uri de stand-up comedy in Romania. Cei mai buni comedianti, spectacole memorabile.',
        'color' => '#F59E0B',
    ],
    'copii' => [
        'name' => 'Evenimente pentru Copii',
        'slug' => 'copii',
        'icon' => '👶',
        'description' => 'Spectacole si evenimente pentru cei mici. Teatru de papusi, circuri, concerte educative si multe alte activitati pentru copii.',
        'hero_image' => 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=1920&q=80', // Kids at performance - Photo by Robo Wunderkind
        'hero_image_credit' => 'Photo by Robo Wunderkind on Unsplash',
        'meta_title' => 'Evenimente pentru Copii Romania | Ambilet',
        'meta_description' => 'Bilete pentru evenimente si spectacole pentru copii. Teatru de papusi, circ, concerte educative.',
        'color' => '#10B981',
    ],
    'sport' => [
        'name' => 'Sport',
        'slug' => 'sport',
        'icon' => '⚽',
        'description' => 'Evenimente sportive din Romania. Meciuri de fotbal, baschet, tenis si alte competitii. Fii parte din actiune!',
        'hero_image' => 'https://images.unsplash.com/photo-1461896836934- voices-of-the-game?w=1920&q=80', // Stadium - Photo by Thomas Serer
        'hero_image_credit' => 'Photo by Thomas Serer on Unsplash',
        'meta_title' => 'Bilete Evenimente Sportive Romania | Ambilet',
        'meta_description' => 'Bilete pentru evenimente sportive in Romania. Fotbal, baschet, tenis si multe alte sporturi.',
        'color' => '#3B82F6',
    ],
    'moto' => [
        'name' => 'Moto & Auto',
        'slug' => 'moto',
        'icon' => '🏍️',
        'description' => 'Evenimente auto si moto. Curse, rally-uri, expozitii de masini si motociclete. Pentru pasionatii de viteza.',
        'hero_image' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1920&q=80', // Racing track - Photo by Campbell
        'hero_image_credit' => 'Photo by Campbell on Unsplash',
        'meta_title' => 'Evenimente Moto & Auto Romania | Ambilet',
        'meta_description' => 'Bilete pentru evenimente moto si auto in Romania. Curse, rally-uri, expozitii auto.',
        'color' => '#EF4444',
    ],
    'expozitii' => [
        'name' => 'Expozitii',
        'slug' => 'expozitii',
        'icon' => '🖼️',
        'description' => 'Expozitii de arta, fotografie si cultura. Descopera cele mai interesante expozitii din muzeele si galeriile din Romania.',
        'hero_image' => 'https://images.unsplash.com/photo-1536924940846-227afb31e2a5?w=1920&q=80', // Art gallery - Photo by Steve Johnson
        'hero_image_credit' => 'Photo by Steve Johnson on Unsplash',
        'meta_title' => 'Expozitii de Arta Romania | Ambilet',
        'meta_description' => 'Bilete pentru expozitii de arta, fotografie si cultura in Romania. Muzee si galerii.',
        'color' => '#8B5CF6',
    ],
    'conferinte' => [
        'name' => 'Conferinte & Business',
        'slug' => 'conferinte',
        'icon' => '💼',
        'description' => 'Conferinte, seminarii si evenimente de business. Networking si dezvoltare profesionala cu speakeri de top.',
        'hero_image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1920&q=80', // Conference room - Photo by Product School
        'hero_image_credit' => 'Photo by Product School on Unsplash',
        'meta_title' => 'Conferinte Business Romania | Ambilet',
        'meta_description' => 'Bilete pentru conferinte si evenimente de business in Romania. Networking si dezvoltare profesionala.',
        'color' => '#1E293B',
    ],
    'workshop' => [
        'name' => 'Workshop-uri',
        'slug' => 'workshop',
        'icon' => '🛠️',
        'description' => 'Workshop-uri practice si cursuri interactive. Invata abilitati noi de la experti in diverse domenii.',
        'hero_image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1920&q=80', // Workshop session - Photo by Jason Goodman
        'hero_image_credit' => 'Photo by Jason Goodman on Unsplash',
        'meta_title' => 'Workshop-uri Romania | Ambilet',
        'meta_description' => 'Bilete pentru workshop-uri si cursuri interactive in Romania. Invata de la experti.',
        'color' => '#06B6D4',
    ],
];

// =============================================================================
// GENRES CONFIGURATION (Music/Event genres)
// =============================================================================
$GENRES = [
    // Rock & Alternative
    'rock' => [
        'name' => 'Rock',
        'slug' => 'rock',
        'category' => 'concerte',
        'icon' => '🎸',
        'description' => 'Energie pura si riff-uri de chitara. Cele mai tari concerte rock din Romania, de la clasici la artisti contemporani.',
        'hero_image' => 'https://images.unsplash.com/photo-1498038432885-c6f3f1b912ee?w=1920&q=80', // Rock concert - Photo by Nainoa Shizuru
        'hero_image_credit' => 'Photo by Nainoa Shizuru on Unsplash',
        'color' => '#DC2626',
    ],
    'alternative' => [
        'name' => 'Alternative',
        'slug' => 'alternative',
        'category' => 'concerte',
        'icon' => '🎧',
        'description' => 'Sunete indie si alternative pentru cei care cauta ceva diferit. Descopera artisti inovatori.',
        'hero_image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1920&q=80', // Guitar player - Photo by Gabriel Gurrola
        'hero_image_credit' => 'Photo by Gabriel Gurrola on Unsplash',
        'color' => '#7C3AED',
    ],
    'metal' => [
        'name' => 'Metal',
        'slug' => 'metal',
        'category' => 'concerte',
        'icon' => '🤘',
        'description' => 'Heavy metal, thrash, death metal si toate subgenurile. Pentru fanii sunetelor intense.',
        'hero_image' => 'https://images.unsplash.com/photo-1501612780327-45045538702b?w=1920&q=80', // Metal concert - Photo by Vishnu R Nair
        'hero_image_credit' => 'Photo by Vishnu R Nair on Unsplash',
        'color' => '#1F2937',
    ],
    'punk' => [
        'name' => 'Punk',
        'slug' => 'punk',
        'category' => 'concerte',
        'icon' => '🎤',
        'description' => 'Atitudine punk rock autentica. Energie bruta si mesaje directe.',
        'hero_image' => 'https://images.unsplash.com/photo-1574169208507-84376144848b?w=1920&q=80', // Punk aesthetic - Photo by Alexander Popov
        'hero_image_credit' => 'Photo by Alexander Popov on Unsplash',
        'color' => '#EA580C',
    ],

    // Pop & Dance
    'pop' => [
        'name' => 'Pop',
        'slug' => 'pop',
        'category' => 'concerte',
        'icon' => '🎵',
        'description' => 'Hituri pop si artisti de top. Cele mai populare melodii live pe scenele din Romania.',
        'hero_image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=1920&q=80', // Pop concert crowd - Photo by Anthony DELANOIX
        'hero_image_credit' => 'Photo by Anthony DELANOIX on Unsplash',
        'color' => '#EC4899',
    ],
    'electronic' => [
        'name' => 'Electronic / EDM',
        'slug' => 'electronic',
        'category' => 'concerte',
        'icon' => '🎛️',
        'description' => 'Muzica electronica, house, techno, trance si EDM. DJ-i de renume mondial.',
        'hero_image' => 'https://images.unsplash.com/photo-1571266028243-e4c3a0d64c10?w=1920&q=80', // DJ concert lights - Photo by Marcela Laskoski
        'hero_image_credit' => 'Photo by Marcela Laskoski on Unsplash',
        'color' => '#8B5CF6',
    ],
    'dance' => [
        'name' => 'Dance',
        'slug' => 'dance',
        'category' => 'concerte',
        'icon' => '💃',
        'description' => 'Ritmuri dance si eurodance. Muzica care te pune in miscare.',
        'hero_image' => 'https://images.unsplash.com/photo-1504609813442-a8924e83f76e?w=1920&q=80', // Dance party - Photo by Karina Lago
        'hero_image_credit' => 'Photo by Karina Lago on Unsplash',
        'color' => '#D946EF',
    ],

    // Hip-Hop & R&B
    'hip-hop' => [
        'name' => 'Hip-Hop / Rap',
        'slug' => 'hip-hop',
        'category' => 'concerte',
        'icon' => '🎤',
        'description' => 'Cultura hip-hop si rap romanesc si international. Flow-uri si beat-uri de exceptie.',
        'hero_image' => 'https://images.unsplash.com/photo-1547355253-ff0740f6e8c1?w=1920&q=80', // Hip-hop artist - Photo by Vidar Nordli-Mathisen
        'hero_image_credit' => 'Photo by Vidar Nordli-Mathisen on Unsplash',
        'color' => '#F97316',
    ],
    'rnb' => [
        'name' => 'R&B / Soul',
        'slug' => 'rnb',
        'category' => 'concerte',
        'icon' => '🎙️',
        'description' => 'Ritmuri R&B si soul. Voci puternice si emotii autentice.',
        'hero_image' => 'https://images.unsplash.com/photo-1415201364774-f6f0bb35f28f?w=1920&q=80', // Soul singer - Photo by Jens Thekkeveettil
        'hero_image_credit' => 'Photo by Jens Thekkeveettil on Unsplash',
        'color' => '#BE185D',
    ],

    // Classical & Jazz
    'jazz' => [
        'name' => 'Jazz',
        'slug' => 'jazz',
        'category' => 'concerte',
        'icon' => '🎷',
        'description' => 'Jazz clasic si contemporan. Improvizatii de exceptie si atmosfera unica.',
        'hero_image' => 'https://images.unsplash.com/photo-1415201364774-f6f0bb35f28f?w=1920&q=80', // Jazz band - Photo by Jens Thekkeveettil
        'hero_image_credit' => 'Photo by Jens Thekkeveettil on Unsplash',
        'color' => '#0D9488',
    ],
    'blues' => [
        'name' => 'Blues',
        'slug' => 'blues',
        'category' => 'concerte',
        'icon' => '🎸',
        'description' => 'Blues autentic si emotionant. Radacinile muzicii rock si jazz.',
        'hero_image' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?w=1920&q=80', // Blues guitar - Photo by Caught In Joy
        'hero_image_credit' => 'Photo by Caught In Joy on Unsplash',
        'color' => '#1E40AF',
    ],
    'clasic' => [
        'name' => 'Muzica Clasica',
        'slug' => 'clasic',
        'category' => 'concerte',
        'icon' => '🎻',
        'description' => 'Simfonii si concerte clasice. Orchestra si muzicieni de renume pe scene prestigioase.',
        'hero_image' => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?w=1920&q=80', // Orchestra - Photo by Manuel Nageli
        'hero_image_credit' => 'Photo by Manuel Nageli on Unsplash',
        'color' => '#92400E',
    ],
    'opera' => [
        'name' => 'Opera',
        'slug' => 'opera',
        'category' => 'teatru',
        'icon' => '🎭',
        'description' => 'Spectacole de opera si arii celebre. Voci magnifice si productii grandioase.',
        'hero_image' => 'https://images.unsplash.com/photo-1580809361436-42a7ec204889?w=1920&q=80', // Opera house - Photo by Vlad Busuioc
        'hero_image_credit' => 'Photo by Vlad Busuioc on Unsplash',
        'color' => '#7C2D12',
    ],

    // Folk & World
    'folk' => [
        'name' => 'Folk',
        'slug' => 'folk',
        'category' => 'concerte',
        'icon' => '🪕',
        'description' => 'Muzica folk si traditii muzicale. Povesti autentice si sunete organice.',
        'hero_image' => 'https://images.unsplash.com/photo-1511192336575-5a79af67a629?w=1920&q=80', // Folk instruments - Photo by Zachary Nelson
        'hero_image_credit' => 'Photo by Zachary Nelson on Unsplash',
        'color' => '#65A30D',
    ],
    'etno' => [
        'name' => 'Etno / World Music',
        'slug' => 'etno',
        'category' => 'concerte',
        'icon' => '🌍',
        'description' => 'Muzica world si traditii din intreaga lume. Sunete exotice si ritmuri diverse.',
        'hero_image' => 'https://images.unsplash.com/photo-1504898770365-14faca6a7320?w=1920&q=80', // World music - Photo by Rashid Khreiss
        'hero_image_credit' => 'Photo by Rashid Khreiss on Unsplash',
        'color' => '#CA8A04',
    ],
    'romaneasca' => [
        'name' => 'Muzica Romaneasca',
        'slug' => 'romaneasca',
        'category' => 'concerte',
        'icon' => '🇷🇴',
        'description' => 'Muzica populara romaneasca si artisti de top din Romania. Traditie si modernitate.',
        'hero_image' => 'https://images.unsplash.com/photo-1577563908411-5077b6dc7624?w=1920&q=80', // Romanian folk - Generic folk image
        'hero_image_credit' => 'Photo on Unsplash',
        'color' => '#0369A1',
    ],
    'latino' => [
        'name' => 'Latino / Reggaeton',
        'slug' => 'latino',
        'category' => 'concerte',
        'icon' => '💃',
        'description' => 'Ritmuri latino, reggaeton si salsa. Muzica care te face sa dansezi.',
        'hero_image' => 'https://images.unsplash.com/photo-1504609773096-104ff2c73ba4?w=1920&q=80', // Latin dance - Photo by Ardian Lumi
        'hero_image_credit' => 'Photo by Ardian Lumi on Unsplash',
        'color' => '#DC2626',
    ],

    // Other genres
    'country' => [
        'name' => 'Country',
        'slug' => 'country',
        'category' => 'concerte',
        'icon' => '🤠',
        'description' => 'Muzica country americana. Chitari acustice si povesti de viata.',
        'hero_image' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?w=1920&q=80', // Country guitar - Photo by Caught In Joy
        'hero_image_credit' => 'Photo by Caught In Joy on Unsplash',
        'color' => '#B45309',
    ],
    'reggae' => [
        'name' => 'Reggae',
        'slug' => 'reggae',
        'category' => 'concerte',
        'icon' => '🎶',
        'description' => 'Vibes reggae si dub. Ritmuri relaxate si mesaje pozitive.',
        'hero_image' => 'https://images.unsplash.com/photo-1528489496900-d841a5ee37b5?w=1920&q=80', // Reggae vibes - Photo by Foad Roshan
        'hero_image_credit' => 'Photo by Foad Roshan on Unsplash',
        'color' => '#059669',
    ],
    'trap' => [
        'name' => 'Trap',
        'slug' => 'trap',
        'category' => 'concerte',
        'icon' => '🔥',
        'description' => 'Muzica trap si beat-uri heavy. Energie intensa si bass puternic.',
        'hero_image' => 'https://images.unsplash.com/photo-1571330735066-03aaa9429d89?w=1920&q=80', // Trap concert - Photo by Aditya Chinchure
        'hero_image_credit' => 'Photo by Aditya Chinchure on Unsplash',
        'color' => '#7C3AED',
    ],
    'dnb' => [
        'name' => 'Drum & Bass',
        'slug' => 'dnb',
        'category' => 'concerte',
        'icon' => '🥁',
        'description' => 'Drum and bass si jungle. Beat-uri rapide si bass-uri profunde.',
        'hero_image' => 'https://images.unsplash.com/photo-1598387993281-cecf8b71a8f8?w=1920&q=80', // DnB event - Photo by Alexander Popov
        'hero_image_credit' => 'Photo by Alexander Popov on Unsplash',
        'color' => '#4F46E5',
    ],
];

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get category configuration by slug
 *
 * @param string $slug Category slug
 * @return array|null Category config or null if not found
 */
function getCategory($slug) {
    global $CATEGORIES;
    return $CATEGORIES[$slug] ?? null;
}

/**
 * Get all categories
 *
 * @return array All categories
 */
function getAllCategories() {
    global $CATEGORIES;
    return $CATEGORIES;
}

/**
 * Get genre configuration by slug
 *
 * @param string $slug Genre slug
 * @return array|null Genre config or null if not found
 */
function getGenre($slug) {
    global $GENRES;
    return $GENRES[$slug] ?? null;
}

/**
 * Get all genres
 *
 * @param string|null $category Filter by category slug
 * @return array All genres (optionally filtered by category)
 */
function getAllGenres($category = null) {
    global $GENRES;

    if ($category === null) {
        return $GENRES;
    }

    return array_filter($GENRES, function($genre) use ($category) {
        return $genre['category'] === $category;
    });
}

/**
 * Get hero image URL for category or genre
 *
 * @param string $slug Category or genre slug
 * @param string $type 'category' or 'genre'
 * @return string Image URL
 */
function getHeroImage($slug, $type = 'category') {
    if ($type === 'category') {
        $config = getCategory($slug);
    } else {
        $config = getGenre($slug);
    }

    if ($config && isset($config['hero_image'])) {
        return $config['hero_image'];
    }

    // Default fallback images
    return $type === 'category'
        ? 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1920&q=80'
        : 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1920&q=80';
}

/**
 * Get description for category or genre
 *
 * @param string $slug Category or genre slug
 * @param string $type 'category' or 'genre'
 * @return string Description
 */
function getDescription($slug, $type = 'category') {
    if ($type === 'category') {
        $config = getCategory($slug);
    } else {
        $config = getGenre($slug);
    }

    return $config['description'] ?? 'Descopera cele mai tari evenimente din aceasta categorie.';
}

/**
 * Get icon for category or genre
 *
 * @param string $slug Category or genre slug
 * @param string $type 'category' or 'genre'
 * @return string Emoji icon
 */
function getIcon($slug, $type = 'category') {
    if ($type === 'category') {
        $config = getCategory($slug);
    } else {
        $config = getGenre($slug);
    }

    return $config['icon'] ?? '🎫';
}

/**
 * Get accent color for category or genre
 *
 * @param string $slug Category or genre slug
 * @param string $type 'category' or 'genre'
 * @return string Hex color
 */
function getAccentColor($slug, $type = 'category') {
    if ($type === 'category') {
        $config = getCategory($slug);
    } else {
        $config = getGenre($slug);
    }

    return $config['color'] ?? '#A51C30';
}
