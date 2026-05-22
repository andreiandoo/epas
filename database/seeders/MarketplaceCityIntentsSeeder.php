<?php

namespace Database\Seeders;

use App\Models\MarketplaceCityIntent;
use Illuminate\Database\Seeder;

/**
 * Seeds the canonical intent catalog for bilete.online (or any leisure
 * marketplace). Each intent generates BOTH:
 *   /{intent-slug}            — global landing
 *   /{city}/{intent-slug}     — city-scoped landing
 *
 * Usage: MARKETPLACE_ID=3 php artisan db:seed --class=MarketplaceCityIntentsSeeder
 *
 * Idempotent: re-running updates existing intents in place rather than duplicating.
 */
class MarketplaceCityIntentsSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaceClientId = (int) env('MARKETPLACE_ID', 1);
        $this->command->info("Seeding city intents for marketplace_client_id: {$marketplaceClientId}");

        foreach ($this->intents() as $sortOrder => $data) {
            MarketplaceCityIntent::updateOrCreate(
                [
                    'marketplace_client_id' => $marketplaceClientId,
                    'slug' => $data['slug'],
                ],
                [
                    'name' => $data['name'],
                    'title_template' => $data['title_template'],
                    'h1_template' => $data['h1_template'] ?? $data['title_template'],
                    'meta_description_template' => $data['meta_description_template'],
                    'intro_copy' => $data['intro_copy'],
                    'seo_copy' => $data['seo_copy'],
                    'filter_rule_json' => $data['filter_rule_json'],
                    'icon' => $data['icon'] ?? null,
                    'accent_color' => $data['accent_color'] ?? 'vermilion',
                    'min_results_for_index' => $data['min_results_for_index'] ?? 3,
                    'is_active' => true,
                    'sort_order' => $sortOrder + 1,
                ]
            );
            $this->command->info("  • {$data['slug']}");
        }
    }

    /**
     * Each row defines one intent. The filter_rule_json column drives the
     * actual event filtering at request time (see IntentFilterResolver).
     *
     * Conventions:
     *   - Slugs all start with `activitati-` so .htaccess routing can pick
     *     them out unambiguously from category / city / event URLs.
     *   - title_template / meta_description_template use {city_name} and
     *     {result_count} placeholders. For global landings these get an
     *     empty string for {city_name} — title strings are written to read
     *     gracefully either way (e.g. "Activități indoor în România" vs
     *     "Activități indoor în Brașov").
     */
    protected function intents(): array
    {
        // The {city_name} placeholder is interpolated by the resolver. When
        // a city is set the controller passes the city's translated name;
        // for global pages it falls back to "România" via the seo_copy layer.
        return [
            // ===== TEMPORAL =====
            [
                'slug' => 'activitati-azi',
                'name' => ['ro' => 'Azi', 'en' => 'Today'],
                'title_template' => ['ro' => 'Activități azi în {city_name} · {result_count} disponibile · bilete.online', 'en' => 'Things to do today in {city_name} · bilete.online'],
                'h1_template' => ['ro' => 'Activități azi în {city_name}', 'en' => 'What to do today in {city_name}'],
                'meta_description_template' => ['ro' => 'Toate activitățile disponibile azi în {city_name}. Rezervi în 30 de secunde, intri cu QR.'],
                'intro_copy' => ['ro' => 'Nu mai pierde timpul căutând: vezi tot ce e disponibil azi, cu bilete la îndemână.'],
                'seo_copy' => ['ro' => 'Dacă vrei să faci ceva azi în {city_name}, aici găsești cele mai bune opțiuni cu disponibilitate confirmată pentru ziua de astăzi. De la escape rooms la muzee și parcuri de aventură, toate locurile listate au sesiuni programate în decursul zilei.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'has_session_today'],
                ]],
                'icon' => '⏰',
                'accent_color' => 'vermilion',
            ],
            [
                'slug' => 'activitati-maine',
                'name' => ['ro' => 'Mâine', 'en' => 'Tomorrow'],
                'title_template' => ['ro' => 'Activități mâine în {city_name} · bilete.online'],
                'h1_template' => ['ro' => 'Ce să faci mâine în {city_name}'],
                'meta_description_template' => ['ro' => 'Planuri pentru mâine în {city_name}? Vezi activitățile cu disponibilitate confirmată.'],
                'intro_copy' => ['ro' => 'Planifică-ți ziua de mâine — rezervi acum, primești QR-ul instant.'],
                'seo_copy' => ['ro' => 'Mâine ai timp liber în {city_name}? Iată activitățile disponibile pentru rezervare cu sesiuni programate. Bilete cu QR, intrare rapidă, fără cozi.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'has_session_tomorrow'],
                ]],
                'icon' => '📅',
                'accent_color' => 'forest',
            ],
            [
                'slug' => 'activitati-weekend',
                'name' => ['ro' => 'Weekend', 'en' => 'Weekend'],
                'title_template' => ['ro' => 'Activități de weekend în {city_name} · bilete.online'],
                'h1_template' => ['ro' => 'Ce să faci în weekend în {city_name}'],
                'meta_description_template' => ['ro' => 'Cele mai bune activități de weekend în {city_name}. Rezervi online, intri cu QR.'],
                'intro_copy' => ['ro' => 'Weekend-ul e scurt — alege rapid din activitățile disponibile sâmbătă și duminică.'],
                'seo_copy' => ['ro' => 'Selecția de weekend pentru {city_name}: activitățile potrivite pentru o ieșire cu prietenii, familia sau copiii. Programe verificate, bilete cu QR, comision 1% pentru organizatori.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'has_session_this_weekend'],
                ]],
                'icon' => '🎉',
                'accent_color' => 'ochre',
            ],

            // ===== WEATHER / VENUE TYPE =====
            [
                'slug' => 'activitati-indoor',
                'name' => ['ro' => 'Indoor', 'en' => 'Indoor'],
                'title_template' => ['ro' => 'Activități indoor în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități în interior din {city_name}: escape rooms, muzee, ateliere, planetarii. Perfect pentru orice vreme.'],
                'intro_copy' => ['ro' => 'Vremea nu contează — toate activitățile de aici sunt indoor.'],
                'seo_copy' => ['ro' => 'Activitățile indoor sunt salvarea când plouă, e prea cald sau pur și simplu vrei să stai la adăpost. În {city_name} găsești escape rooms, muzee interactive, planetarii, ateliere creative și multe alte locuri unde experiența nu depinde de meteo.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'event_attr', 'field' => 'is_indoor', 'value' => true],
                ]],
                'icon' => '🏛️',
                'accent_color' => 'sky',
            ],
            [
                'slug' => 'activitati-outdoor',
                'name' => ['ro' => 'Outdoor', 'en' => 'Outdoor'],
                'title_template' => ['ro' => 'Activități outdoor în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Aventură în natură: parcuri de aventură, tiroliene, trasee și activități în aer liber din {city_name}.'],
                'intro_copy' => ['ro' => 'Adrenalină, aer curat și experiențe în natură — toate într-un singur loc.'],
                'seo_copy' => ['ro' => 'În {city_name} ai parte de o gamă largă de activități în aer liber, de la tiroliene și parcuri de aventură la trasee tematice și experiențe la înălțime. Verifică vremea înainte și pregătește-te de adrenalină.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'event_attr', 'field' => 'is_outdoor', 'value' => true],
                ]],
                'icon' => '🌲',
                'accent_color' => 'forest',
            ],
            [
                'slug' => 'activitati-zile-ploioase',
                'name' => ['ro' => 'Zile ploioase', 'en' => 'Rainy days'],
                'title_template' => ['ro' => 'Activități pentru zile ploioase în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Plouă? Iată ce poți face în {city_name}: activități indoor cu sesiuni programate.'],
                'intro_copy' => ['ro' => 'Activități la adăpost, perfecte pentru zilele ploioase. Toate au sesiuni programate.'],
                'seo_copy' => ['ro' => 'Ploaia nu trebuie să-ți strice planurile. Selectăm pentru tine activitățile indoor din {city_name} — locuri unde poți intra direct, fără să te uzi, și unde experiența e la fel de bună indiferent de meteo.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'event_attr', 'field' => 'is_indoor', 'value' => true],
                    ['type' => 'event_attr', 'field' => 'is_weather_sensitive', 'value' => false],
                ]],
                'icon' => '🌧️',
                'accent_color' => 'sky',
            ],
            [
                'slug' => 'activitati-zile-caniculare',
                'name' => ['ro' => 'Zile caniculare', 'en' => 'Hot days'],
                'title_template' => ['ro' => 'Activități pentru zile caniculare în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Caniculă în {city_name}? Iată unde te poți răcori — activități indoor cu AC.'],
                'intro_copy' => ['ro' => 'Caldură? Salvează-te în spațiile cu aer condiționat sau locuri răcoroase.'],
                'seo_copy' => ['ro' => 'Când termometrul depășește 35°C în {city_name}, soluția e să te muți indoor sau în spații răcoroase: muzee, acvarii, planetarii, escape rooms — toate cu climatizare și fără expunere la soare.'],
                'filter_rule_json' => ['any' => [
                    ['all' => [
                        ['type' => 'in_city', 'param' => '$city'],
                        ['type' => 'event_attr', 'field' => 'is_indoor', 'value' => true],
                    ]],
                ]],
                'icon' => '☀️',
                'accent_color' => 'ochre',
            ],

            // ===== PRICE =====
            [
                'slug' => 'activitati-gratuite',
                'name' => ['ro' => 'Gratuite', 'en' => 'Free'],
                'title_template' => ['ro' => 'Activități gratuite în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități gratuite în {city_name}. Bilete cu intrare liberă, doar QR.'],
                'intro_copy' => ['ro' => 'Distracție gratuită — rezervi locul cu QR și ești bun.'],
                'seo_copy' => ['ro' => 'Lista activităților gratuite din {city_name}, actualizată zilnic. Chiar dacă intrarea e liberă, locurile sunt limitate și o rezervare îți garantează accesul.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'cheapest_price_eq', 'value' => 0],
                ]],
                'icon' => '🎁',
                'accent_color' => 'forest',
            ],
            [
                'slug' => 'activitati-sub-50-lei',
                'name' => ['ro' => 'Sub 50 lei', 'en' => 'Under 50 lei'],
                'title_template' => ['ro' => 'Activități sub 50 lei în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități accesibile sub 50 lei în {city_name}. Bilete cu QR, intrare rapidă.'],
                'intro_copy' => ['ro' => 'Buget mic, distracție mare. Toate biletele sub 50 lei.'],
                'seo_copy' => ['ro' => 'Selecția noastră de activități cu prețul biletului sub 50 lei în {city_name}. Muzee, ateliere, vizite ghidate și experiențe locale — toate la îndemâna oricui.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'cheapest_price_min', 'value' => 1],
                    ['type' => 'cheapest_price_max', 'value' => 50],
                ]],
                'icon' => '💰',
                'accent_color' => 'ochre',
            ],
            [
                'slug' => 'activitati-sub-100-lei',
                'name' => ['ro' => 'Sub 100 lei', 'en' => 'Under 100 lei'],
                'title_template' => ['ro' => 'Activități sub 100 lei în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități sub 100 lei în {city_name}. Selecție pentru buget mediu, cu bilete cu QR.'],
                'intro_copy' => ['ro' => 'Distracție de calitate fără să te dai peste cap la portofel.'],
                'seo_copy' => ['ro' => 'Activitățile din {city_name} cu prețul biletului sub 100 lei. O selecție potrivită pentru weekend-uri, ieșiri cu familia sau cu prietenii.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'cheapest_price_max', 'value' => 100],
                ]],
                'icon' => '💸',
                'accent_color' => 'vermilion',
            ],

            // ===== AUDIENCE =====
            [
                'slug' => 'activitati-copii',
                'name' => ['ro' => 'Copii', 'en' => 'Kids'],
                'title_template' => ['ro' => 'Activități pentru copii în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități pentru copii în {city_name}: muzee, ateliere, parcuri și experiențe potrivite vârstei.'],
                'intro_copy' => ['ro' => 'Locuri unde copiii sunt bineveniți și au experiențe pe măsura lor.'],
                'seo_copy' => ['ro' => 'În {city_name} găsești o varietate de activități gândite pentru copii: muzee interactive, ateliere de pictură și olărit, planetarii, parcuri de aventură potrivite vârstei. Toate locurile au sesiuni programate și bilete cu QR.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'event_attr', 'field' => 'is_kid_friendly', 'value' => true],
                ]],
                'icon' => '🧒',
                'accent_color' => 'ochre',
            ],
            [
                'slug' => 'activitati-familie',
                'name' => ['ro' => 'Familie', 'en' => 'Family'],
                'title_template' => ['ro' => 'Activități în familie în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Idei de petrecere a timpului în familie în {city_name}. Activități pentru toate vârstele.'],
                'intro_copy' => ['ro' => 'Activități pentru toată familia — de la cei mici la bunici.'],
                'seo_copy' => ['ro' => 'Selecția pentru familie din {city_name}: activități care plac tuturor membrilor, de la copii la adulți. Vizite ghidate, parcuri tematice, muzee interactive, ateliere comune.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'familie'],
                ]],
                'icon' => '👨‍👩‍👧',
                'accent_color' => 'forest',
            ],
            [
                'slug' => 'activitati-romantice',
                'name' => ['ro' => 'Romantice', 'en' => 'Romantic'],
                'title_template' => ['ro' => 'Activități romantice în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Idei de date romantice în {city_name}: experiențe pentru cuplu cu bilete online.'],
                'intro_copy' => ['ro' => 'Idei de date care nu se termină cu un film. Rezervi pentru doi, intrați cu QR.'],
                'seo_copy' => ['ro' => 'Selecția noastră de experiențe romantice din {city_name} — locuri unde ieșirea cu partenerul devine memorabilă. De la degustări și ateliere pentru cuplu, la experiențe la înălțime și expoziții imersive.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'romantic'],
                ]],
                'icon' => '💞',
                'accent_color' => 'vermilion',
            ],
            [
                'slug' => 'activitati-corporate',
                'name' => ['ro' => 'Corporate', 'en' => 'Corporate'],
                'title_template' => ['ro' => 'Activități corporate în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Team building și activități corporate în {city_name}. Rezervare în grup, factură fiscală.'],
                'intro_copy' => ['ro' => 'Ieșiri de echipă cu rezervare în grup și factură fiscală.'],
                'seo_copy' => ['ro' => 'Pentru companiile din {city_name}, oferim o selecție de activități potrivite pentru team building, evenimente de echipă sau ieșiri corporative. Capacitate confirmată pentru grupuri mari, factură fiscală instant.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'corporate'],
                ]],
                'icon' => '💼',
                'accent_color' => 'sky',
            ],
            [
                'slug' => 'activitati-team-building',
                'name' => ['ro' => 'Team building', 'en' => 'Team building'],
                'title_template' => ['ro' => 'Activități de team building în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Idei de team building în {city_name}: escape rooms, ateliere, sport — pentru echipe de 5-50.'],
                'intro_copy' => ['ro' => 'Activități care strâng echipa mai bine decât orice trust fall.'],
                'seo_copy' => ['ro' => 'În {city_name} găsești locuri excelente pentru team building, de la escape rooms colaborative la ateliere de gătit, sport sau experiențe outdoor. Capacitate adaptată pentru grupuri mici și mari.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'team-building'],
                ]],
                'icon' => '🤝',
                'accent_color' => 'forest',
            ],
            [
                'slug' => 'activitati-aniversari',
                'name' => ['ro' => 'Aniversări', 'en' => 'Birthdays'],
                'title_template' => ['ro' => 'Activități pentru aniversări în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Petrece-ți aniversarea altfel: activități memorabile în {city_name} cu rezervare în grup.'],
                'intro_copy' => ['ro' => 'O aniversare merită mai mult decât tort. Iată locurile potrivite.'],
                'seo_copy' => ['ro' => 'Pentru cei care vor să-și sărbătorească aniversarea în {city_name} cu o experiență, nu doar cu un tort: parcuri tematice, escape rooms cu temă, ateliere creative și degustări — toate cu rezervare în grup.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'aniversari'],
                ]],
                'icon' => '🎂',
                'accent_color' => 'vermilion',
            ],
            [
                'slug' => 'activitati-grupuri',
                'name' => ['ro' => 'Grupuri', 'en' => 'Groups'],
                'title_template' => ['ro' => 'Activități pentru grupuri în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități în grup în {city_name}: capacitate confirmată pentru 10+ persoane.'],
                'intro_copy' => ['ro' => 'Vii cu un grup mare? Iată locurile cu capacitatea potrivită.'],
                'seo_copy' => ['ro' => 'Organizezi o ieșire în grup în {city_name}? Aici găsești activitățile care acceptă rezervări mari, cu prețuri pentru grupuri și capacitate confirmată în avans.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'grupuri'],
                ]],
                'icon' => '👥',
                'accent_color' => 'sky',
            ],
            [
                'slug' => 'activitati-cuplu',
                'name' => ['ro' => 'Cuplu', 'en' => 'Couple'],
                'title_template' => ['ro' => 'Activități pentru cuplu în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Idei de date și activități pentru doi în {city_name}.'],
                'intro_copy' => ['ro' => 'Locuri în care voi doi vă veți distra fără să stați la coadă.'],
                'seo_copy' => ['ro' => 'Activități în {city_name} pentru două persoane: experiențe culinare, ateliere artistice, parcuri tematice și escape rooms — toate cu opțiunea de rezervare pentru cuplu.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'cuplu'],
                ]],
                'icon' => '💑',
                'accent_color' => 'vermilion',
            ],
            [
                'slug' => 'activitati-seniori',
                'name' => ['ro' => 'Seniori', 'en' => 'Seniors'],
                'title_template' => ['ro' => 'Activități pentru seniori în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități potrivite pentru seniori în {city_name}: muzee, tururi culturale, ateliere relaxate.'],
                'intro_copy' => ['ro' => 'Locuri prietenoase, fără efort fizic intens, cu acces facil.'],
                'seo_copy' => ['ro' => 'În {city_name} avem o selecție de activități prietenoase cu vârsta a treia: vizite ghidate, muzee cu trasee accesibile, ateliere și degustări — toate cu acces pentru persoane în vârstă.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'seniori'],
                ]],
                'icon' => '👵',
                'accent_color' => 'forest',
            ],
            [
                'slug' => 'activitati-adolescenti',
                'name' => ['ro' => 'Adolescenți', 'en' => 'Teenagers'],
                'title_template' => ['ro' => 'Activități pentru adolescenți în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități pentru adolescenți (13-18) în {city_name}: escape rooms, VR, parcuri.'],
                'intro_copy' => ['ro' => 'Energie multă, telefon în buzunar, distracție garantată.'],
                'seo_copy' => ['ro' => 'Pentru adolescenți, {city_name} oferă escape rooms tematice, săli VR, parcuri de aventură și ateliere creative — locuri unde se simt în largul lor și ies cu povești de spus.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'adolescenti'],
                ]],
                'icon' => '🧑‍🎓',
                'accent_color' => 'sky',
            ],
            [
                'slug' => 'activitati-scoala',
                'name' => ['ro' => 'Excursii școlare', 'en' => 'School trips'],
                'title_template' => ['ro' => 'Activități pentru excursii școlare în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Locuri educaționale și activități pentru clase de elevi în {city_name}. Tarife de grup.'],
                'intro_copy' => ['ro' => 'Activități educaționale cu rezervare în grup și tarife pentru școli.'],
                'seo_copy' => ['ro' => 'Pentru profesorii care planifică excursii cu clasa în {city_name}: muzee cu programe educaționale, planetarii, ateliere științifice și parcuri tematice — toate cu tarife dedicate grupurilor de elevi.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'scoala'],
                ]],
                'icon' => '🎒',
                'accent_color' => 'ochre',
            ],

            // ===== ACCESIBILITATE =====
            [
                'slug' => 'activitati-accesibile',
                'name' => ['ro' => 'Accesibile', 'en' => 'Accessible'],
                'title_template' => ['ro' => 'Activități accesibile în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Activități accesibile pentru persoane cu mobilitate redusă în {city_name}.'],
                'intro_copy' => ['ro' => 'Locuri cu acces fără bariere — verificate de operatori.'],
                'seo_copy' => ['ro' => 'În {city_name} listăm locațiile care declară explicit acces pentru persoane cu dizabilități motorii — rampe, lift, băi adaptate. Fiecare loc are detalii precise despre tipul de acces oferit.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'event_attr', 'field' => 'is_accessible', 'value' => true],
                ]],
                'icon' => '♿',
                'accent_color' => 'sky',
            ],

            // ===== EDUCATIONAL / CULTURAL =====
            [
                'slug' => 'activitati-educationale',
                'name' => ['ro' => 'Educaționale', 'en' => 'Educational'],
                'title_template' => ['ro' => 'Activități educaționale în {city_name} · bilete.online'],
                'meta_description_template' => ['ro' => 'Muzee, planetarii, ateliere științifice și experiențe care învață ceva.'],
                'intro_copy' => ['ro' => 'Distracție cu valoare adăugată — copiii (și adulții) învață fără să-și dea seama.'],
                'seo_copy' => ['ro' => 'Educația poate fi fun. În {city_name} alege dintre muzee interactive, planetarii, ateliere STEM, vizite ghidate și experiențe imersive care învață ceva nou, fără tonul plictisitor de manual.'],
                'filter_rule_json' => ['all' => [
                    ['type' => 'in_city', 'param' => '$city'],
                    ['type' => 'tag', 'value' => 'educational'],
                ]],
                'icon' => '🔬',
                'accent_color' => 'forest',
            ],
        ];
    }
}
