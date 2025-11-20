<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('event_type_event_genre')->truncate();
        DB::table('event_types')->truncate();
        DB::table('event_genres')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Event Types with parent-child hierarchy (Romanian source, translated to both languages)
        $eventTypes = [
            // Music & Concerts
            ['name_ro' => 'Muzică & Concerte', 'name_en' => 'Music & Concerts', 'slug' => 'muzica-concerte', 'parent' => null],
            ['name_ro' => 'Concert', 'name_en' => 'Concert', 'slug' => 'concert', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Festival de muzică', 'name_en' => 'Music Festival', 'slug' => 'festival-muzica', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Recital', 'name_en' => 'Recital', 'slug' => 'recital', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Concert simfonic', 'name_en' => 'Symphonic Concert', 'slug' => 'concert-simfonic', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Concert de cameră', 'name_en' => 'Chamber Concert', 'slug' => 'concert-camera', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Club night / DJ set', 'name_en' => 'Club Night / DJ Set', 'slug' => 'club-night-dj', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Open air / Outdoor', 'name_en' => 'Open Air / Outdoor', 'slug' => 'open-air', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Concert acustic', 'name_en' => 'Acoustic Concert', 'slug' => 'concert-acustic', 'parent' => 'muzica-concerte'],
            ['name_ro' => 'Concert coral', 'name_en' => 'Choral Concert', 'slug' => 'concert-coral', 'parent' => 'muzica-concerte'],

            // Performing Arts
            ['name_ro' => 'Arte scenice', 'name_en' => 'Performing Arts', 'slug' => 'arte-scenice', 'parent' => null],
            ['name_ro' => 'Spectacol de teatru', 'name_en' => 'Theatre Play', 'slug' => 'spectacol-teatru', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Musical', 'name_en' => 'Musical', 'slug' => 'musical', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Operă', 'name_en' => 'Opera', 'slug' => 'opera', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Operetă', 'name_en' => 'Operetta', 'slug' => 'opereta', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Balet', 'name_en' => 'Ballet', 'slug' => 'balet', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Dans contemporan', 'name_en' => 'Contemporary Dance', 'slug' => 'dans-contemporan', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Spectacol de circ', 'name_en' => 'Circus Show', 'slug' => 'spectacol-circ', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Improvizație', 'name_en' => 'Improvisation', 'slug' => 'improvizatie', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Pantomimă', 'name_en' => 'Pantomime', 'slug' => 'pantomima', 'parent' => 'arte-scenice'],
            ['name_ro' => 'Păpuși / Marionete', 'name_en' => 'Puppetry / Marionettes', 'slug' => 'papusi-marionete', 'parent' => 'arte-scenice'],

            // Comedy & Entertainment
            ['name_ro' => 'Comedie & Divertisment', 'name_en' => 'Comedy & Entertainment', 'slug' => 'comedie-divertisment', 'parent' => null],
            ['name_ro' => 'Stand-up comedy', 'name_en' => 'Stand-up Comedy', 'slug' => 'stand-up-comedy', 'parent' => 'comedie-divertisment'],
            ['name_ro' => 'One-man show', 'name_en' => 'One-man Show', 'slug' => 'one-man-show', 'parent' => 'comedie-divertisment'],
            ['name_ro' => 'Sketch comedy', 'name_en' => 'Sketch Comedy', 'slug' => 'sketch-comedy', 'parent' => 'comedie-divertisment'],
            ['name_ro' => 'Roast', 'name_en' => 'Roast', 'slug' => 'roast', 'parent' => 'comedie-divertisment'],
            ['name_ro' => 'Varieteu / Cabaret', 'name_en' => 'Variety / Cabaret', 'slug' => 'varieteu-cabaret', 'parent' => 'comedie-divertisment'],
            ['name_ro' => 'Magie / Iluzie', 'name_en' => 'Magic / Illusion', 'slug' => 'magie-iluzie', 'parent' => 'comedie-divertisment'],
            ['name_ro' => 'Mentalism', 'name_en' => 'Mentalism', 'slug' => 'mentalism', 'parent' => 'comedie-divertisment'],

            // Film & Media
            ['name_ro' => 'Film & Media', 'name_en' => 'Film & Media', 'slug' => 'film-media', 'parent' => null],
            ['name_ro' => 'Premieră de film', 'name_en' => 'Film Premiere', 'slug' => 'premiera-film', 'parent' => 'film-media'],
            ['name_ro' => 'Festival de film', 'name_en' => 'Film Festival', 'slug' => 'festival-film', 'parent' => 'film-media'],
            ['name_ro' => 'Proiecție specială', 'name_en' => 'Special Screening', 'slug' => 'proiectie-speciala', 'parent' => 'film-media'],
            ['name_ro' => 'Documentar', 'name_en' => 'Documentary', 'slug' => 'documentar', 'parent' => 'film-media'],
            ['name_ro' => 'Cine-concert', 'name_en' => 'Cine-concert', 'slug' => 'cine-concert', 'parent' => 'film-media'],

            // Literature & Poetry
            ['name_ro' => 'Literatură & Poezie', 'name_en' => 'Literature & Poetry', 'slug' => 'literatura-poezie', 'parent' => null],
            ['name_ro' => 'Lansare de carte', 'name_en' => 'Book Launch', 'slug' => 'lansare-carte', 'parent' => 'literatura-poezie'],
            ['name_ro' => 'Lectură publică', 'name_en' => 'Public Reading', 'slug' => 'lectura-publica', 'parent' => 'literatura-poezie'],
            ['name_ro' => 'Slam poetry', 'name_en' => 'Poetry Slam', 'slug' => 'slam-poetry', 'parent' => 'literatura-poezie'],
            ['name_ro' => 'Spoken word', 'name_en' => 'Spoken Word', 'slug' => 'spoken-word', 'parent' => 'literatura-poezie'],
            ['name_ro' => 'Târg de carte', 'name_en' => 'Book Fair', 'slug' => 'targ-carte', 'parent' => 'literatura-poezie'],

            // Visual Arts
            ['name_ro' => 'Arte vizuale', 'name_en' => 'Visual Arts', 'slug' => 'arte-vizuale', 'parent' => null],
            ['name_ro' => 'Vernisaj / Expoziție', 'name_en' => 'Vernissage / Exhibition', 'slug' => 'vernisaj-expozitie', 'parent' => 'arte-vizuale'],
            ['name_ro' => 'Instalație artistică', 'name_en' => 'Art Installation', 'slug' => 'instalatie-artistica', 'parent' => 'arte-vizuale'],
            ['name_ro' => 'Performance art', 'name_en' => 'Performance Art', 'slug' => 'performance-art', 'parent' => 'arte-vizuale'],
            ['name_ro' => 'Artă digitală / New media', 'name_en' => 'Digital Art / New Media', 'slug' => 'arta-digitala', 'parent' => 'arte-vizuale'],
            ['name_ro' => 'Street art', 'name_en' => 'Street Art', 'slug' => 'street-art', 'parent' => 'arte-vizuale'],

            // Conferences & Business
            ['name_ro' => 'Conferințe & Business', 'name_en' => 'Conferences & Business', 'slug' => 'conferinte-business', 'parent' => null],
            ['name_ro' => 'Conferință', 'name_en' => 'Conference', 'slug' => 'conferinta', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Seminar', 'name_en' => 'Seminar', 'slug' => 'seminar', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Workshop', 'name_en' => 'Workshop', 'slug' => 'workshop', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Networking event', 'name_en' => 'Networking Event', 'slug' => 'networking-event', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Summit', 'name_en' => 'Summit', 'slug' => 'summit', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Hackathon', 'name_en' => 'Hackathon', 'slug' => 'hackathon', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Pitch / Demo day', 'name_en' => 'Pitch / Demo Day', 'slug' => 'pitch-demo-day', 'parent' => 'conferinte-business'],
            ['name_ro' => 'Târg / Expo', 'name_en' => 'Trade Fair / Expo', 'slug' => 'targ-expo', 'parent' => 'conferinte-business'],

            // Education & Learning
            ['name_ro' => 'Educație & Learning', 'name_en' => 'Education & Learning', 'slug' => 'educatie-learning', 'parent' => null],
            ['name_ro' => 'Curs / Training', 'name_en' => 'Course / Training', 'slug' => 'curs-training', 'parent' => 'educatie-learning'],
            ['name_ro' => 'Masterclass', 'name_en' => 'Masterclass', 'slug' => 'masterclass', 'parent' => 'educatie-learning'],
            ['name_ro' => 'Webinar', 'name_en' => 'Webinar', 'slug' => 'webinar', 'parent' => 'educatie-learning'],
            ['name_ro' => 'Tur ghidat', 'name_en' => 'Guided Tour', 'slug' => 'tur-ghidat', 'parent' => 'educatie-learning'],
            ['name_ro' => 'Prelegere / Talk', 'name_en' => 'Lecture / Talk', 'slug' => 'prelegere-talk', 'parent' => 'educatie-learning'],

            // Sports & Fitness
            ['name_ro' => 'Sport & Fitness', 'name_en' => 'Sports & Fitness', 'slug' => 'sport-fitness', 'parent' => null],
            ['name_ro' => 'Competiție sportivă', 'name_en' => 'Sports Competition', 'slug' => 'competitie-sportiva', 'parent' => 'sport-fitness'],
            ['name_ro' => 'Maraton / Cursă', 'name_en' => 'Marathon / Race', 'slug' => 'maraton-cursa', 'parent' => 'sport-fitness'],
            ['name_ro' => 'eSports', 'name_en' => 'eSports', 'slug' => 'esports', 'parent' => 'sport-fitness'],
            ['name_ro' => 'Yoga / Meditație', 'name_en' => 'Yoga / Meditation', 'slug' => 'yoga-meditatie', 'parent' => 'sport-fitness'],
            ['name_ro' => 'Fitness class', 'name_en' => 'Fitness Class', 'slug' => 'fitness-class', 'parent' => 'sport-fitness'],

            // Food & Drink
            ['name_ro' => 'Gastronomie & Băuturi', 'name_en' => 'Food & Drink', 'slug' => 'gastronomie-bauturi', 'parent' => null],
            ['name_ro' => 'Degustare de vin', 'name_en' => 'Wine Tasting', 'slug' => 'degustare-vin', 'parent' => 'gastronomie-bauturi'],
            ['name_ro' => 'Degustare de bere', 'name_en' => 'Beer Tasting', 'slug' => 'degustare-bere', 'parent' => 'gastronomie-bauturi'],
            ['name_ro' => 'Curs de gătit', 'name_en' => 'Cooking Class', 'slug' => 'curs-gatit', 'parent' => 'gastronomie-bauturi'],
            ['name_ro' => 'Festival culinar', 'name_en' => 'Food Festival', 'slug' => 'festival-culinar', 'parent' => 'gastronomie-bauturi'],
            ['name_ro' => 'Cină tematică', 'name_en' => 'Themed Dinner', 'slug' => 'cina-tematica', 'parent' => 'gastronomie-bauturi'],
            ['name_ro' => 'Cocktail masterclass', 'name_en' => 'Cocktail Masterclass', 'slug' => 'cocktail-masterclass', 'parent' => 'gastronomie-bauturi'],

            // Community & Social
            ['name_ro' => 'Comunitate & Social', 'name_en' => 'Community & Social', 'slug' => 'comunitate-social', 'parent' => null],
            ['name_ro' => 'Festival comunitar', 'name_en' => 'Community Festival', 'slug' => 'festival-comunitar', 'parent' => 'comunitate-social'],
            ['name_ro' => 'Eveniment caritabil', 'name_en' => 'Charity Event', 'slug' => 'eveniment-caritabil', 'parent' => 'comunitate-social'],
            ['name_ro' => 'Protest / Marș', 'name_en' => 'Protest / March', 'slug' => 'protest-mars', 'parent' => 'comunitate-social'],
            ['name_ro' => 'Meetup', 'name_en' => 'Meetup', 'slug' => 'meetup', 'parent' => 'comunitate-social'],
            ['name_ro' => 'Petrecere privată', 'name_en' => 'Private Party', 'slug' => 'petrecere-privata', 'parent' => 'comunitate-social'],

            // Family & Kids
            ['name_ro' => 'Familie & Copii', 'name_en' => 'Family & Kids', 'slug' => 'familie-copii', 'parent' => null],
            ['name_ro' => 'Spectacol pentru copii', 'name_en' => 'Children\'s Show', 'slug' => 'spectacol-copii', 'parent' => 'familie-copii'],
            ['name_ro' => 'Workshop pentru copii', 'name_en' => 'Kids Workshop', 'slug' => 'workshop-copii', 'parent' => 'familie-copii'],
            ['name_ro' => 'Petrecere aniversară', 'name_en' => 'Birthday Party', 'slug' => 'petrecere-aniversara', 'parent' => 'familie-copii'],
            ['name_ro' => 'Tabără', 'name_en' => 'Camp', 'slug' => 'tabara', 'parent' => 'familie-copii'],
        ];

        // Event Genres grouped by category (English source, translated to both languages)
        $genreGroups = [
            'music' => [
                ['slug' => 'rock', 'en' => 'Rock', 'ro' => 'Rock'],
                ['slug' => 'pop', 'en' => 'Pop', 'ro' => 'Pop'],
                ['slug' => 'jazz', 'en' => 'Jazz', 'ro' => 'Jazz'],
                ['slug' => 'blues', 'en' => 'Blues', 'ro' => 'Blues'],
                ['slug' => 'classical', 'en' => 'Classical', 'ro' => 'Clasică'],
                ['slug' => 'electronic', 'en' => 'Electronic', 'ro' => 'Electronică'],
                ['slug' => 'hip-hop', 'en' => 'Hip-Hop', 'ro' => 'Hip-Hop'],
                ['slug' => 'rnb', 'en' => 'R&B', 'ro' => 'R&B'],
                ['slug' => 'country', 'en' => 'Country', 'ro' => 'Country'],
                ['slug' => 'folk', 'en' => 'Folk', 'ro' => 'Folk'],
                ['slug' => 'metal', 'en' => 'Metal', 'ro' => 'Metal'],
                ['slug' => 'punk', 'en' => 'Punk', 'ro' => 'Punk'],
                ['slug' => 'reggae', 'en' => 'Reggae', 'ro' => 'Reggae'],
                ['slug' => 'soul', 'en' => 'Soul', 'ro' => 'Soul'],
                ['slug' => 'funk', 'en' => 'Funk', 'ro' => 'Funk'],
                ['slug' => 'latin', 'en' => 'Latin', 'ro' => 'Latină'],
                ['slug' => 'world-music', 'en' => 'World Music', 'ro' => 'World Music'],
                ['slug' => 'indie', 'en' => 'Indie', 'ro' => 'Indie'],
                ['slug' => 'alternative', 'en' => 'Alternative', 'ro' => 'Alternativ'],
                ['slug' => 'ambient', 'en' => 'Ambient', 'ro' => 'Ambient'],
                ['slug' => 'house', 'en' => 'House', 'ro' => 'House'],
                ['slug' => 'techno', 'en' => 'Techno', 'ro' => 'Techno'],
                ['slug' => 'trance', 'en' => 'Trance', 'ro' => 'Trance'],
                ['slug' => 'drum-and-bass', 'en' => 'Drum and Bass', 'ro' => 'Drum and Bass'],
                ['slug' => 'dubstep', 'en' => 'Dubstep', 'ro' => 'Dubstep'],
                ['slug' => 'disco', 'en' => 'Disco', 'ro' => 'Disco'],
                ['slug' => 'opera', 'en' => 'Opera', 'ro' => 'Operă'],
                ['slug' => 'choral', 'en' => 'Choral', 'ro' => 'Corală'],
                ['slug' => 'symphonic', 'en' => 'Symphonic', 'ro' => 'Simfonică'],
                ['slug' => 'chamber', 'en' => 'Chamber', 'ro' => 'De cameră'],
            ],
            'performing-arts' => [
                ['slug' => 'drama', 'en' => 'Drama', 'ro' => 'Dramă'],
                ['slug' => 'comedy-theatre', 'en' => 'Comedy (Theatre)', 'ro' => 'Comedie (Teatru)'],
                ['slug' => 'tragedy', 'en' => 'Tragedy', 'ro' => 'Tragedie'],
                ['slug' => 'musical-theatre', 'en' => 'Musical Theatre', 'ro' => 'Teatru muzical'],
                ['slug' => 'physical-theatre', 'en' => 'Physical Theatre', 'ro' => 'Teatru fizic'],
                ['slug' => 'experimental-theatre', 'en' => 'Experimental', 'ro' => 'Experimental'],
                ['slug' => 'classical-ballet', 'en' => 'Classical Ballet', 'ro' => 'Balet clasic'],
                ['slug' => 'modern-dance', 'en' => 'Modern Dance', 'ro' => 'Dans modern'],
                ['slug' => 'contemporary-dance', 'en' => 'Contemporary Dance', 'ro' => 'Dans contemporan'],
                ['slug' => 'ballroom', 'en' => 'Ballroom', 'ro' => 'Dans de societate'],
                ['slug' => 'street-dance', 'en' => 'Street Dance', 'ro' => 'Street Dance'],
                ['slug' => 'flamenco', 'en' => 'Flamenco', 'ro' => 'Flamenco'],
                ['slug' => 'tango', 'en' => 'Tango', 'ro' => 'Tango'],
                ['slug' => 'acrobatics', 'en' => 'Acrobatics', 'ro' => 'Acrobații'],
                ['slug' => 'aerial', 'en' => 'Aerial', 'ro' => 'Aerian'],
                ['slug' => 'clowning', 'en' => 'Clowning', 'ro' => 'Clovnerie'],
                ['slug' => 'juggling', 'en' => 'Juggling', 'ro' => 'Jonglerie'],
            ],
            'comedy' => [
                ['slug' => 'stand-up', 'en' => 'Stand-up', 'ro' => 'Stand-up'],
                ['slug' => 'improv', 'en' => 'Improv', 'ro' => 'Improvizație'],
                ['slug' => 'sketch', 'en' => 'Sketch', 'ro' => 'Sketch'],
                ['slug' => 'satire', 'en' => 'Satire', 'ro' => 'Satiră'],
                ['slug' => 'parody', 'en' => 'Parody', 'ro' => 'Parodie'],
                ['slug' => 'observational', 'en' => 'Observational', 'ro' => 'Observațional'],
                ['slug' => 'dark-comedy', 'en' => 'Dark Comedy', 'ro' => 'Comedie neagră'],
                ['slug' => 'absurdist', 'en' => 'Absurdist', 'ro' => 'Absurd'],
            ],
            'film' => [
                ['slug' => 'action-film', 'en' => 'Action', 'ro' => 'Acțiune'],
                ['slug' => 'adventure-film', 'en' => 'Adventure', 'ro' => 'Aventură'],
                ['slug' => 'animation', 'en' => 'Animation', 'ro' => 'Animație'],
                ['slug' => 'comedy-film', 'en' => 'Comedy (Film)', 'ro' => 'Comedie (Film)'],
                ['slug' => 'documentary-film', 'en' => 'Documentary', 'ro' => 'Documentar'],
                ['slug' => 'drama-film', 'en' => 'Drama', 'ro' => 'Dramă'],
                ['slug' => 'horror', 'en' => 'Horror', 'ro' => 'Horror'],
                ['slug' => 'sci-fi', 'en' => 'Sci-Fi', 'ro' => 'Sci-Fi'],
                ['slug' => 'thriller-film', 'en' => 'Thriller', 'ro' => 'Thriller'],
                ['slug' => 'romance-film', 'en' => 'Romance', 'ro' => 'Romantic'],
                ['slug' => 'fantasy-film', 'en' => 'Fantasy', 'ro' => 'Fantasy'],
                ['slug' => 'indie-film', 'en' => 'Indie', 'ro' => 'Independent'],
                ['slug' => 'short-film', 'en' => 'Short Film', 'ro' => 'Scurtmetraj'],
            ],
            'literature' => [
                ['slug' => 'poetry', 'en' => 'Poetry', 'ro' => 'Poezie'],
                ['slug' => 'prose', 'en' => 'Prose', 'ro' => 'Proză'],
                ['slug' => 'fiction', 'en' => 'Fiction', 'ro' => 'Ficțiune'],
                ['slug' => 'non-fiction', 'en' => 'Non-fiction', 'ro' => 'Non-ficțiune'],
                ['slug' => 'biography', 'en' => 'Biography', 'ro' => 'Biografie'],
                ['slug' => 'memoir', 'en' => 'Memoir', 'ro' => 'Memorii'],
                ['slug' => 'essay', 'en' => 'Essay', 'ro' => 'Eseu'],
                ['slug' => 'spoken-word-lit', 'en' => 'Spoken Word', 'ro' => 'Spoken Word'],
            ],
            'visual-arts' => [
                ['slug' => 'painting', 'en' => 'Painting', 'ro' => 'Pictură'],
                ['slug' => 'sculpture', 'en' => 'Sculpture', 'ro' => 'Sculptură'],
                ['slug' => 'photography-art', 'en' => 'Photography', 'ro' => 'Fotografie'],
                ['slug' => 'digital-art', 'en' => 'Digital Art', 'ro' => 'Artă digitală'],
                ['slug' => 'installation-art', 'en' => 'Installation', 'ro' => 'Instalație'],
                ['slug' => 'video-art', 'en' => 'Video Art', 'ro' => 'Video art'],
                ['slug' => 'mixed-media', 'en' => 'Mixed Media', 'ro' => 'Tehnică mixtă'],
                ['slug' => 'street-art-visual', 'en' => 'Street Art', 'ro' => 'Street art'],
                ['slug' => 'graffiti', 'en' => 'Graffiti', 'ro' => 'Graffiti'],
                ['slug' => 'contemporary-art', 'en' => 'Contemporary', 'ro' => 'Contemporan'],
            ],
            'business' => [
                ['slug' => 'technology', 'en' => 'Technology', 'ro' => 'Tehnologie'],
                ['slug' => 'marketing', 'en' => 'Marketing', 'ro' => 'Marketing'],
                ['slug' => 'finance', 'en' => 'Finance', 'ro' => 'Finanțe'],
                ['slug' => 'entrepreneurship', 'en' => 'Entrepreneurship', 'ro' => 'Antreprenoriat'],
                ['slug' => 'leadership', 'en' => 'Leadership', 'ro' => 'Leadership'],
                ['slug' => 'innovation', 'en' => 'Innovation', 'ro' => 'Inovație'],
                ['slug' => 'sustainability', 'en' => 'Sustainability', 'ro' => 'Sustenabilitate'],
                ['slug' => 'healthcare', 'en' => 'Healthcare', 'ro' => 'Sănătate'],
                ['slug' => 'education-topic', 'en' => 'Education', 'ro' => 'Educație'],
                ['slug' => 'real-estate', 'en' => 'Real Estate', 'ro' => 'Imobiliare'],
            ],
            'sports' => [
                ['slug' => 'football', 'en' => 'Football', 'ro' => 'Fotbal'],
                ['slug' => 'basketball', 'en' => 'Basketball', 'ro' => 'Baschet'],
                ['slug' => 'tennis', 'en' => 'Tennis', 'ro' => 'Tenis'],
                ['slug' => 'running', 'en' => 'Running', 'ro' => 'Alergare'],
                ['slug' => 'cycling', 'en' => 'Cycling', 'ro' => 'Ciclism'],
                ['slug' => 'swimming', 'en' => 'Swimming', 'ro' => 'Înot'],
                ['slug' => 'martial-arts', 'en' => 'Martial Arts', 'ro' => 'Arte marțiale'],
                ['slug' => 'yoga', 'en' => 'Yoga', 'ro' => 'Yoga'],
                ['slug' => 'fitness', 'en' => 'Fitness', 'ro' => 'Fitness'],
                ['slug' => 'esports-genre', 'en' => 'eSports', 'ro' => 'eSports'],
            ],
            'food-drink' => [
                ['slug' => 'wine', 'en' => 'Wine', 'ro' => 'Vin'],
                ['slug' => 'beer', 'en' => 'Beer', 'ro' => 'Bere'],
                ['slug' => 'spirits', 'en' => 'Spirits', 'ro' => 'Băuturi spirtoase'],
                ['slug' => 'cocktails', 'en' => 'Cocktails', 'ro' => 'Cocktailuri'],
                ['slug' => 'coffee', 'en' => 'Coffee', 'ro' => 'Cafea'],
                ['slug' => 'fine-dining', 'en' => 'Fine Dining', 'ro' => 'Fine Dining'],
                ['slug' => 'street-food', 'en' => 'Street Food', 'ro' => 'Street Food'],
                ['slug' => 'vegan', 'en' => 'Vegan', 'ro' => 'Vegan'],
                ['slug' => 'pastry', 'en' => 'Pastry', 'ro' => 'Patiserie'],
            ],
            'family' => [
                ['slug' => 'educational-kids', 'en' => 'Educational', 'ro' => 'Educațional'],
                ['slug' => 'interactive', 'en' => 'Interactive', 'ro' => 'Interactiv'],
                ['slug' => 'storytelling', 'en' => 'Storytelling', 'ro' => 'Povești'],
                ['slug' => 'arts-crafts', 'en' => 'Arts & Crafts', 'ro' => 'Arte și meșteșuguri'],
                ['slug' => 'outdoor-activities', 'en' => 'Outdoor Activities', 'ro' => 'Activități în aer liber'],
            ],
            'community' => [
                ['slug' => 'networking', 'en' => 'Networking', 'ro' => 'Networking'],
                ['slug' => 'charity', 'en' => 'Charity', 'ro' => 'Caritate'],
                ['slug' => 'cultural', 'en' => 'Cultural', 'ro' => 'Cultural'],
                ['slug' => 'religious', 'en' => 'Religious', 'ro' => 'Religios'],
                ['slug' => 'political', 'en' => 'Political', 'ro' => 'Politic'],
                ['slug' => 'social', 'en' => 'Social', 'ro' => 'Social'],
            ],
        ];

        // Mapping event types to genre groups
        $allowedMap = [
            // Music & Concerts
            'concert' => ['group:music'],
            'festival-muzica' => ['group:music'],
            'recital' => ['group:music'],
            'concert-simfonic' => ['symphonic', 'classical', 'opera', 'chamber'],
            'concert-camera' => ['chamber', 'classical', 'jazz'],
            'club-night-dj' => ['house', 'techno', 'trance', 'drum-and-bass', 'dubstep', 'electronic', 'hip-hop', 'disco'],
            'open-air' => ['group:music'],
            'concert-acustic' => ['folk', 'indie', 'alternative', 'jazz', 'blues'],
            'concert-coral' => ['choral', 'classical', 'opera'],

            // Performing Arts
            'spectacol-teatru' => ['group:performing-arts', 'drama', 'comedy-theatre', 'tragedy'],
            'musical' => ['musical-theatre'],
            'opera' => ['opera', 'classical'],
            'opereta' => ['opera', 'classical', 'comedy-theatre'],
            'balet' => ['classical-ballet', 'modern-dance', 'contemporary-dance'],
            'dans-contemporan' => ['modern-dance', 'contemporary-dance', 'physical-theatre'],
            'spectacol-circ' => ['acrobatics', 'aerial', 'clowning', 'juggling'],
            'improvizatie' => ['improv', 'comedy-theatre', 'physical-theatre'],
            'pantomima' => ['physical-theatre', 'clowning'],
            'papusi-marionete' => ['group:family'],

            // Comedy & Entertainment
            'stand-up-comedy' => ['group:comedy'],
            'one-man-show' => ['group:comedy', 'drama'],
            'sketch-comedy' => ['sketch', 'improv', 'satire'],
            'roast' => ['satire', 'observational', 'dark-comedy'],
            'varieteu-cabaret' => ['group:comedy', 'musical-theatre'],
            'magie-iluzie' => [],
            'mentalism' => [],

            // Film & Media
            'premiera-film' => ['group:film'],
            'festival-film' => ['group:film'],
            'proiectie-speciala' => ['group:film'],
            'documentar' => ['documentary-film'],
            'cine-concert' => ['group:music', 'group:film'],

            // Literature & Poetry
            'lansare-carte' => ['group:literature'],
            'lectura-publica' => ['group:literature'],
            'slam-poetry' => ['poetry', 'spoken-word-lit'],
            'spoken-word' => ['spoken-word-lit', 'poetry'],
            'targ-carte' => ['group:literature'],

            // Visual Arts
            'vernisaj-expozitie' => ['group:visual-arts'],
            'instalatie-artistica' => ['installation-art', 'video-art', 'digital-art', 'mixed-media'],
            'performance-art' => ['group:visual-arts', 'physical-theatre'],
            'arta-digitala' => ['digital-art', 'video-art'],
            'street-art' => ['street-art-visual', 'graffiti'],

            // Conferences & Business
            'conferinta' => ['group:business'],
            'seminar' => ['group:business'],
            'workshop' => ['group:business'],
            'networking-event' => ['networking', 'group:business'],
            'summit' => ['group:business'],
            'hackathon' => ['technology', 'innovation'],
            'pitch-demo-day' => ['entrepreneurship', 'technology', 'innovation'],
            'targ-expo' => ['group:business'],

            // Education & Learning
            'curs-training' => ['group:business'],
            'masterclass' => ['group:music', 'group:visual-arts', 'group:business'],
            'webinar' => ['group:business'],
            'tur-ghidat' => ['cultural'],
            'prelegere-talk' => ['group:business'],

            // Sports & Fitness
            'competitie-sportiva' => ['group:sports'],
            'maraton-cursa' => ['running', 'cycling'],
            'esports' => ['esports-genre'],
            'yoga-meditatie' => ['yoga', 'fitness'],
            'fitness-class' => ['fitness', 'yoga'],

            // Food & Drink
            'degustare-vin' => ['wine'],
            'degustare-bere' => ['beer'],
            'curs-gatit' => ['group:food-drink'],
            'festival-culinar' => ['group:food-drink'],
            'cina-tematica' => ['fine-dining', 'group:food-drink'],
            'cocktail-masterclass' => ['cocktails', 'spirits'],

            // Community & Social
            'festival-comunitar' => ['cultural', 'social', 'group:community'],
            'eveniment-caritabil' => ['charity'],
            'protest-mars' => ['political', 'social'],
            'meetup' => ['networking', 'social'],
            'petrecere-privata' => ['social'],

            // Family & Kids
            'spectacol-copii' => ['group:family', 'storytelling', 'interactive'],
            'workshop-copii' => ['group:family', 'arts-crafts', 'educational-kids'],
            'petrecere-aniversara' => ['group:family', 'interactive'],
            'tabara' => ['group:family', 'outdoor-activities', 'educational-kids'],
        ];

        // Insert event types
        $typeIds = [];
        $parentTypes = [];

        // First pass: insert parent types
        foreach ($eventTypes as $type) {
            if ($type['parent'] === null) {
                $id = DB::table('event_types')->insertGetId([
                    'name' => json_encode(['en' => $type['name_en'], 'ro' => $type['name_ro']]),
                    'slug' => $type['slug'],
                    'parent_id' => null,
                    'description' => json_encode(['en' => '', 'ro' => '']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $typeIds[$type['slug']] = $id;
                $parentTypes[$type['slug']] = $id;
            }
        }

        // Second pass: insert child types
        foreach ($eventTypes as $type) {
            if ($type['parent'] !== null) {
                $parentId = $parentTypes[$type['parent']] ?? null;
                $id = DB::table('event_types')->insertGetId([
                    'name' => json_encode(['en' => $type['name_en'], 'ro' => $type['name_ro']]),
                    'slug' => $type['slug'],
                    'parent_id' => $parentId,
                    'description' => json_encode(['en' => '', 'ro' => '']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $typeIds[$type['slug']] = $id;
            }
        }

        // Collect all genres and insert
        $genreIds = [];
        foreach ($genreGroups as $groupName => $genres) {
            foreach ($genres as $genre) {
                if (!isset($genreIds[$genre['slug']])) {
                    $id = DB::table('event_genres')->insertGetId([
                        'name' => json_encode(['en' => $genre['en'], 'ro' => $genre['ro']]),
                        'slug' => $genre['slug'],
                        'description' => json_encode(['en' => '', 'ro' => '']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $genreIds[$genre['slug']] = $id;
                }
            }
        }

        // Create relationships based on allowedMap
        foreach ($allowedMap as $typeSlug => $allowed) {
            if (!isset($typeIds[$typeSlug])) {
                continue;
            }

            $typeId = $typeIds[$typeSlug];
            $allowedGenreSlugs = [];

            foreach ($allowed as $item) {
                if (str_starts_with($item, 'group:')) {
                    // Expand group to individual genres
                    $groupName = substr($item, 6);
                    if (isset($genreGroups[$groupName])) {
                        foreach ($genreGroups[$groupName] as $genre) {
                            $allowedGenreSlugs[] = $genre['slug'];
                        }
                    }
                } else {
                    // Individual genre
                    $allowedGenreSlugs[] = $item;
                }
            }

            // Insert pivot records (no timestamps)
            $allowedGenreSlugs = array_unique($allowedGenreSlugs);
            foreach ($allowedGenreSlugs as $genreSlug) {
                if (isset($genreIds[$genreSlug])) {
                    DB::table('event_type_event_genre')->insert([
                        'event_type_id' => $typeId,
                        'event_genre_id' => $genreIds[$genreSlug],
                    ]);
                }
            }
        }

        $this->command->info('Event types: ' . count($eventTypes));
        $this->command->info('Event genres: ' . count($genreIds));
        $this->command->info('Relationships created based on allowedMap');
    }
}
