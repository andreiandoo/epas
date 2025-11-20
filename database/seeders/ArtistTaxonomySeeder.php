<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArtistTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        // Artist Types
        $artistTypes = [
            ['name' => 'Solo Artist', 'slug' => 'solo-artist'],
            ['name' => 'Band', 'slug' => 'band'],
            ['name' => 'DJ', 'slug' => 'dj'],
            ['name' => 'Producer', 'slug' => 'producer'],
            ['name' => 'Singer-Songwriter', 'slug' => 'singer-songwriter-artist'],
            ['name' => 'Orchestra', 'slug' => 'orchestra'],
            ['name' => 'Choir', 'slug' => 'choir'],
            ['name' => 'Chamber Ensemble', 'slug' => 'chamber-ensemble'],
            ['name' => 'Rapper / MC', 'slug' => 'rapper-mc'],
            ['name' => 'MC / Host', 'slug' => 'mc-host'],
            ['name' => 'Comedian', 'slug' => 'comedian'],
            ['name' => 'Theatre Troupe', 'slug' => 'theatre-troupe'],
            ['name' => 'Dance Company', 'slug' => 'dance-company'],
            ['name' => 'Dancer (Solo)', 'slug' => 'solo-dancer'],
            ['name' => 'Visual Artist', 'slug' => 'visual-artist'],
            ['name' => 'Photographer', 'slug' => 'photographer'],
            ['name' => 'Filmmaker / Director', 'slug' => 'filmmaker'],
            ['name' => 'VJ / Visuals', 'slug' => 'vj-visuals'],
            ['name' => 'Author / Writer', 'slug' => 'author-writer'],
            ['name' => 'Poet / Spoken Word', 'slug' => 'poet-spoken-word'],
            ['name' => 'Magician / Illusionist', 'slug' => 'magician-illusionist'],
            ['name' => 'Circus Artist', 'slug' => 'circus-artist'],
            ['name' => 'Chef', 'slug' => 'chef'],
            ['name' => 'Mixologist / Bartender', 'slug' => 'mixologist'],
            ['name' => 'Speaker', 'slug' => 'speaker'],
            ['name' => 'Trainer / Coach', 'slug' => 'trainer-coach'],
            ['name' => 'Influencer / Creator', 'slug' => 'influencer-creator'],
            ['name' => 'eSports Player', 'slug' => 'esports-player'],
            ['name' => 'eSports Team', 'slug' => 'esports-team'],
            ['name' => 'Athlete', 'slug' => 'athlete'],
        ];

        // Genre groups - all genres flattened with group info
        $genreGroups = [
            'music' => [
                'rock', 'alternative-rock', 'indie-rock', 'classic-rock', 'hard-rock', 'punk',
                'metal', 'heavy-metal', 'death-metal', 'black-metal', 'metalcore', 'progressive-metal',
                'pop', 'dance-pop', 'synthpop', 'k-pop',
                'electronic', 'house', 'techno', 'trance', 'drum-and-bass', 'dubstep', 'edm', 'ambient', 'downtempo',
                'hip-hop', 'rap', 'trap', 'drill', 'rnb', 'soul', 'funk', 'disco',
                'jazz', 'swing', 'bebop', 'jazz-fusion', 'smooth-jazz',
                'blues', 'folk', 'country', 'bluegrass',
                'reggae', 'ska', 'dub', 'dancehall',
                'latin', 'reggaeton', 'salsa', 'bachata', 'merengue',
                'afrobeat', 'afropop', 'world-music', 'balkan', 'celtic', 'flamenco',
                'classical', 'baroque', 'romantic', 'contemporary-classical', 'opera', 'choral',
                'soundtrack', 'singer-songwriter', 'experimental-avant-garde', 'gospel',
            ],
            'dj-electronic' => [
                'house', 'techno', 'trance', 'drum-and-bass', 'dubstep', 'edm', 'breaks', 'uk-garage',
                'deep-house', 'minimal', 'progressive-house', 'tech-house', 'electro', 'hardstyle',
            ],
            'classical-core' => [
                'classical', 'baroque', 'romantic', 'contemporary-classical', 'opera', 'choral', 'film-score',
            ],
            'theatre' => [
                'tragedy', 'comedy-theatre', 'improv', 'physical-theatre', 'pantomime', 'puppetry',
            ],
            'comedy' => [
                'stand-up-comedy', 'sketch-comedy', 'improv', 'musical-comedy', 'satire', 'roast',
            ],
            'dance' => [
                'ballet', 'dance-contemporary', 'dance-ballroom', 'dance-folk', 'street-dance', 'hip-hop-dance',
                'salsa-dance', 'tango', 'kizomba', 'flamenco-dance', 'breakdance',
            ],
            'visual-arts' => [
                'painting', 'sculpture', 'installation', 'video-art', 'digital-art', 'new-media',
                'illustration', 'comics', 'printmaking', 'mixed-media', 'street-art', 'graffiti',
                'contemporary-art', 'modern-art',
            ],
            'photography' => [
                'portrait-photography', 'street-photography', 'fashion-photography', 'wedding-photography',
                'nature-photography', 'wildlife-photography', 'sports-photography', 'documentary-photography',
                'event-photography', 'travel-photography', 'architecture-photography', 'product-photography',
            ],
            'film-tv' => [
                'action', 'adventure', 'animation', 'comedy', 'crime', 'documentary', 'drama', 'family',
                'fantasy', 'historical-period', 'horror', 'mystery', 'romance', 'sci-fi', 'thriller',
                'war', 'western', 'musical', 'biography', 'indie-film', 'short-film', 'experimental-film',
            ],
            'literature' => [
                'poetry', 'spoken-word', 'poetry-slam', 'literary-fiction', 'science-fiction', 'fantasy',
                'crime-noir', 'thriller', 'romance', 'young-adult', 'childrens', 'memoir', 'essay',
                'non-fiction', 'history', 'philosophy',
            ],
            'culinary-cuisine' => [
                'italian', 'french', 'japanese', 'chinese', 'thai', 'indian', 'mexican', 'middle-eastern',
                'romanian', 'balkan', 'mediterranean', 'american', 'bbq-grill', 'vegan-vegetarian',
                'dessert-pastry', 'chocolate', 'street-food',
            ],
            'culinary-beverage' => [
                'coffee', 'tea', 'wine', 'craft-beer', 'whisky', 'cocktails-mixology', 'sake', 'rum', 'gin',
            ],
            'speaker-topics' => [
                'leadership', 'motivation', 'marketing', 'sales', 'product', 'ux', 'ai-ml', 'data-analytics',
                'cybersecurity', 'fintech', 'healthtech', 'edtech', 'climate', 'sustainability',
                'diversity-inclusion', 'education', 'psychology', 'mindfulness', 'wellbeing',
                'entrepreneurship', 'social-impact',
            ],
            'wellness-lifestyle' => [
                'mindfulness', 'meditation', 'breathwork', 'sound-healing',
                'yoga-hatha', 'yoga-vinyasa', 'yoga-yin-restorative', 'pilates',
                'crossfit-functional', 'running', 'cycling', 'hiking', 'dance-fitness',
            ],
            'influencer-niches' => [
                'fashion', 'beauty', 'travel', 'tech', 'gaming', 'fitness', 'food', 'parenting',
                'finance', 'luxury', 'streetwear', 'photography', 'lifestyle',
            ],
            'sports' => [
                'football', 'basketball', 'tennis', 'rugby', 'handball', 'volleyball', 'athletics', 'swimming',
                'gymnastics', 'boxing', 'mma', 'judo', 'wrestling', 'climbing', 'cycling-road', 'cycling-mtb',
                'motorsport', 'skateboarding', 'parkour',
            ],
            'esports' => [
                'moba', 'fps', 'battle-royale', 'sports-sim', 'fighting', 'racing', 'strategy-rts', 'card-ccg',
            ],
            'magic' => [
                'close-up-magic', 'stage-magic', 'mentalism', 'illusions', 'escape-artistry',
            ],
            'circus-arts' => [
                'aerial-arts', 'acrobatics', 'juggling', 'clowning', 'contortion', 'tightrope',
            ],
        ];

        // Allowed map - which genres are allowed for each artist type
        $allowedMap = [
            'solo-artist' => ['group:music'],
            'band' => ['group:music'],
            'dj' => ['group:dj-electronic', 'hip-hop', 'rap', 'rnb', 'disco', 'synthpop', 'funk'],
            'producer' => ['group:dj-electronic', 'hip-hop', 'trap', 'pop', 'soundtrack', 'experimental-avant-garde'],
            'singer-songwriter-artist' => ['singer-songwriter', 'folk', 'country', 'indie-rock', 'pop', 'blues'],
            'orchestra' => ['group:classical-core'],
            'choir' => ['choral', 'gospel', 'classical', 'contemporary-classical'],
            'chamber-ensemble' => ['group:classical-core', 'jazz', 'jazz-fusion', 'contemporary-classical'],
            'rapper-mc' => ['hip-hop', 'rap', 'drill', 'trap'],
            'mc-host' => ['stand-up-comedy', 'improv'],
            'comedian' => ['group:comedy'],
            'theatre-troupe' => ['group:theatre'],
            'dance-company' => ['group:dance'],
            'solo-dancer' => ['group:dance'],
            'visual-artist' => ['group:visual-arts'],
            'photographer' => ['group:photography'],
            'filmmaker' => ['group:film-tv'],
            'vj-visuals' => ['video-art', 'digital-art', 'new-media'],
            'author-writer' => ['group:literature'],
            'poet-spoken-word' => ['poetry', 'spoken-word', 'poetry-slam'],
            'magician-illusionist' => ['group:magic'],
            'circus-artist' => ['group:circus-arts'],
            'chef' => ['group:culinary-cuisine'],
            'mixologist' => ['group:culinary-beverage'],
            'speaker' => ['group:speaker-topics'],
            'trainer-coach' => ['group:speaker-topics', 'group:wellness-lifestyle'],
            'influencer-creator' => ['group:influencer-niches'],
            'esports-player' => ['group:esports'],
            'esports-team' => ['group:esports'],
            'athlete' => ['group:sports'],
        ];

        // Insert artist types
        $typeIds = [];
        foreach ($artistTypes as $type) {
            $id = DB::table('artist_types')->insertGetId([
                'name' => json_encode(['en' => $type['name'], 'ro' => '']),
                'slug' => $type['slug'],
                'description' => json_encode(['en' => '', 'ro' => '']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $typeIds[$type['slug']] = $id;
        }

        // Collect all unique genres
        $allGenres = [];
        $genreToGroups = []; // Track which groups each genre belongs to

        foreach ($genreGroups as $groupName => $genres) {
            foreach ($genres as $genre) {
                if (!isset($allGenres[$genre])) {
                    $allGenres[$genre] = $this->slugToName($genre);
                    $genreToGroups[$genre] = [];
                }
                $genreToGroups[$genre][] = $groupName;
            }
        }

        // Insert artist genres
        $genreIds = [];
        foreach ($allGenres as $slug => $name) {
            $id = DB::table('artist_genres')->insertGetId([
                'name' => json_encode(['en' => $name, 'ro' => '']),
                'slug' => $slug,
                'description' => json_encode(['en' => '', 'ro' => '']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $genreIds[$slug] = $id;
        }

        // Create relationships based on allowed_map
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
                        $allowedGenreSlugs = array_merge($allowedGenreSlugs, $genreGroups[$groupName]);
                    }
                } else {
                    // Individual genre
                    $allowedGenreSlugs[] = $item;
                }
            }

            // Insert pivot records
            $allowedGenreSlugs = array_unique($allowedGenreSlugs);
            foreach ($allowedGenreSlugs as $genreSlug) {
                if (isset($genreIds[$genreSlug])) {
                    DB::table('artist_type_artist_genre')->insert([
                        'artist_type_id' => $typeId,
                        'artist_genre_id' => $genreIds[$genreSlug],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Artist types: ' . count($artistTypes));
        $this->command->info('Artist genres: ' . count($allGenres));
        $this->command->info('Relationships created based on allowed_map');
    }

    private function slugToName(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
