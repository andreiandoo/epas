<?php

namespace Database\Seeders;

use App\Models\EventGenre;
use App\Models\EventType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class EventTypesAndGenresSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1) EVENT TYPES (fostele categories)
            $types = [
                ['name' => 'Muzică & divertisment', 'slug' => 'muzica-divertisment'],
                ['name' => 'Arte & cultură',        'slug' => 'arte-cultura'],
                ['name' => 'Business & tech',       'slug' => 'business-tech'],
                ['name' => 'Educație & academic',   'slug' => 'educatie-academic'],

                ['name' => 'Concert',               'slug' => 'concert',              'parent' => 'muzica-divertisment'],
                ['name' => 'DJ set / Club night',   'slug' => 'dj-set-club-night',    'parent' => 'muzica-divertisment'],
                ['name' => 'Spectacol de teatru',   'slug' => 'spectacol-de-teatru',  'parent' => 'arte-cultura'],
                ['name' => 'Stand-up comedy',       'slug' => 'stand-up-comedy',      'parent' => 'arte-cultura'],
                ['name' => 'Proiecție de film',     'slug' => 'proiectie-de-film',    'parent' => 'arte-cultura'],
                ['name' => 'Expoziție',             'slug' => 'expozitie',            'parent' => 'arte-cultura'],
                ['name' => 'Workshop',              'slug' => 'workshop',             'parent' => 'business-tech'],
                ['name' => 'Conferință',            'slug' => 'conferinta',           'parent' => 'business-tech'],
                ['name' => 'Hackathon',             'slug' => 'hackathon',            'parent' => 'business-tech'],

                ['name' => 'Tur ghidat',            'slug' => 'tur-ghidat',           'parent' => 'turism-city'],
                ['name' => 'Maraton',               'slug' => 'maraton',              'parent' => 'sport-esports-outdoor'],
                ['name' => 'eSports: turneu',       'slug' => 'esports-turneu',       'parent' => 'sport-esports-outdoor'],
                ['name' => 'Festival culinar',      'slug' => 'festival-culinar',     'parent' => 'food-beverage'],
                ['name' => 'Webinar',               'slug' => 'webinar',              'parent' => 'online-virtual-hibrid'],
            ];

            $bySlug = [];
            foreach ($types as $t) {
                $parentId = null;
                if (!empty($t['parent'])) {
                    $parentSlug = $t['parent'];
                    if (!isset($bySlug[$parentSlug])) {
                        $bySlug[$parentSlug] = EventType::firstOrCreate(
                            ['slug' => $parentSlug],
                            ['name' => Str::headline(str_replace('-', ' ', $parentSlug))]
                        );
                    }
                    $parentId = $bySlug[$parentSlug]->id;
                }

                $type = EventType::firstOrCreate(
                    ['slug' => $t['slug']],
                    ['name' => $t['name'], 'parent_id' => $parentId]
                );

                $bySlug[$t['slug']] = $type;

                // If parent_id changed later, ensure it's set:
                if ($parentId && $type->parent_id !== $parentId) {
                    $type->parent_id = $parentId;
                    $type->save();
                }
            }

            // 2) EVENT GENRES (groups + children) – din ce ai dat anterior
            $genreGroups = [
                'education-training' => [
                    'early-childhood','k-12','higher-education','adult-learning',
                    'sel','literacy','numeracy','stem-steam','arts-education',
                    'language-learning','inclusion-sen','leadership-management',
                    'assessment-feedback','curriculum-ubd','classroom-management','edtech-integration',
                ],
                'wellness-lifestyle' => [
                    'mindfulness','meditation','breathwork','sound-healing',
                    'yoga-hatha','yoga-vinyasa','yoga-yin-restorative','pilates',
                    'crossfit-functional','running','cycling','hiking','dance-fitness',
                ],
                'sports-esports' => [
                    'football','basketball','tennis','rugby','handball','volleyball',
                    'athletics','swimming','gymnastics','table-tennis','badminton',
                    'boxing','mma','judo','wrestling','climbing','cycling-road-mtb','motorsport','chess',
                    'esports-moba','esports-fps','esports-battle-royale','esports-sports-sim',
                ],
                'tourism-city' => [
                    'heritage-walk','architecture-tour','street-art-tour','nature-walk',
                    'food-tour','wine-route','photography-walk','city-highlights',
                ],
                'religious-spiritual' => [
                    'christian','orthodox','catholic','interfaith','buddhist','vedic-yoga','sufi','gospel',
                ],
                // Grupe implicite întâlnite în allowed_map (fără listă explicită) — le creăm ca „goale”
                'theatre' => [
                    'tragedy','comedy-theatre','improv','physical-theatre','pantomime','puppetry'
                ],
                'film-tv' => [
                    'action','adventure','animation','comedy','crime','documentary','drama','family',
                    'fantasy','historical-period','horror','mystery','romance','sci-fi','thriller',
                    'war','western','musical','biography','indie-film','short-film'
                ],
                'visual-arts-design' => [
                    'contemporary-art','modern-art','street-art','graffiti',
                    'photography','sculpture','installation','video-art',
                    'digital-art','new-media','illustration','comics',
                    'graphic-design','industrial-design','architecture'
                ],
                'business-tech' => [
                    'ai-ml','data-analytics','cloud-devops','cybersecurity','blockchain-web3',
                    'fintech','healthtech','biotech','edtech','greentech-climate',
                    'mobility-autotech','ecommerce','marketing-martech','product-ux',
                    'gamedev','ar-vr','iot','robotics','hardware','open-source','startups-vc'
                ],
                'food-beverage' => [
                    'vegan-vegetarian','bbq-grill','street-food','dessert-pastry','chocolate',
                    'coffee','tea','wine','craft-beer','whisky','cocktails-mixology',
                    'italian','french','japanese','chinese','thai','indian','mexican','middle-eastern',
                    'romanian','balkan','mediterranean'
                ],
                'music' => [
                    'rock','alternative-rock','indie-rock','classic-rock','hard-rock','punk',
                    'metal','heavy-metal','death-metal','black-metal','metalcore','progressive-metal',
                    'pop','dance-pop','synthpop','k-pop',
                    'electronic','house','techno','trance','drum-and-bass','dubstep','edm','ambient','downtempo',
                    'hip-hop','rap','trap','drill','rnb','soul','funk','disco',
                    'jazz','swing','bebop','jazz-fusion','smooth-jazz',
                    'blues','folk','country','bluegrass',
                    'reggae','ska','dub','dancehall',
                    'latin','reggaeton','salsa','bachata','merengue',
                    'afrobeat','afropop','world-music','balkan','celtic','flamenco',
                    'classical','baroque','romantic','contemporary-classical','opera','choral',
                    'soundtrack','singer-songwriter','experimental-avant-garde'
                ], // pentru 'concert'
                'online-virtual-hibrid' => [],
                'sport-esports-outdoor' => [],
                'turism-city' => [], // alternativ slug pentru 'tourism-city'
            ];

            $genreBySlug = [];

            foreach ($genreGroups as $groupSlug => $children) {
                $group = EventGenre::firstOrCreate(
                    ['slug' => $groupSlug],
                    ['name' => Str::headline(str_replace('-', ' ', $groupSlug))]
                );
                $genreBySlug[$groupSlug] = $group;

                foreach ($children as $childSlug) {
                    $child = EventGenre::firstOrCreate(
                        ['slug' => $childSlug],
                        ['name' => Str::headline(str_replace('-', ' ', $childSlug)), 'parent_id' => $group->id]
                    );
                    $genreBySlug[$childSlug] = $child;
                }
            }

            // 3) ALLOWED MAP: event_type (slug) -> array de "group:<slug>" sau genuri concrete
            $allowedMap = [
                'concert'              => ['group:music'],
                'dj-set-club-night'    => ['electronic','house','techno','trance','drum-and-bass','dubstep','edm','ambient','downtempo','disco','funk','synthpop'],
                'spectacol-de-teatru'  => ['group:theatre'],
                'stand-up-comedy'      => ['stand-up-comedy','sketch-comedy','improv'],
                'proiectie-de-film'    => ['group:film-tv'],
                'expozitie'            => ['group:visual-arts-design'],
                'workshop'             => ['group:education-training','group:business-tech'],
                'conferinta'           => ['group:business-tech','group:education-training'],
                'hackathon'            => ['ai-ml','data-analytics','cloud-devops','cybersecurity','blockchain-web3','gamedev','ar-vr','product-ux','open-source','iot','robotics','hardware'],
                'tur-ghidat'           => ['group:tourism-city'],
                'maraton'              => ['running'],
                'esports-turneu'       => ['esports-moba','esports-fps','esports-battle-royale','esports-sports-sim'],
                'festival-culinar'     => ['group:food-beverage'],
                'webinar'              => ['group:business-tech','group:education-training'],
            ];

            foreach ($allowedMap as $typeSlug => $items) {
                $type = EventType::where('slug', $typeSlug)->first();
                if (! $type) continue;

                $genreIds = [];
                foreach ($items as $it) {
                    if (str_starts_with($it, 'group:')) {
                        $gSlug = substr($it, 6);
                        if (!isset($genreBySlug[$gSlug])) continue;
                        $groupId = $genreBySlug[$gSlug]->id;
                        // toți copiii grupului (dacă nu are, mapăm măcar grupul)
                        $childIds = EventGenre::where('parent_id', $groupId)->pluck('id')->all();
                        $genreIds = array_merge($genreIds, $childIds ?: [$groupId]);
                    } else {
                        if (isset($genreBySlug[$it])) {
                            $genreIds[] = $genreBySlug[$it]->id;
                        } else {
                            // creăm on-the-fly dacă nu există
                            $new = EventGenre::firstOrCreate(
                                ['slug' => $it],
                                ['name' => Str::headline(str_replace('-', ' ', $it))]
                            );
                            $genreBySlug[$it] = $new;
                            $genreIds[] = $new->id;
                        }
                    }
                }

                $genreIds = array_values(array_unique($genreIds));
                if ($genreIds) {
                    $type->allowedEventGenres()->syncWithoutDetaching($genreIds);
                }
            }
        });
    }
}
