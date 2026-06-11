<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityScheduleException;
use App\Models\ActivityVariant;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceOrganizer;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demo data for the Activities module on bilete.online (marketplace_client_id = 3).
 *
 * Builds a small but diverse fixture set so QA can exercise every part of the
 * stack introduced in A0-A4:
 *
 *   • 2 marketplace organizers ("locations" in user-facing terms)
 *   • 2 venues (one per organizer)
 *   • 5 activities covering different rhythms:
 *        1. Escape room          — short 60-min slots, multiple per evening
 *        2. Museum               — long open hours, large capacity
 *        3. Adventure park       — outdoor, weather-sensitive, full-day
 *        4. Workshop             — weekend-only, kid-friendly, capped at 8
 *        5. Walking tour         — one slot a day at 10:00
 *   • Each activity has weekly schedules, a single exception (the next
 *     Romanian holiday), and 2-3 pricing variants.
 *
 * Hardcoded to marketplace_client_id = 3 by design (per task brief).
 * Idempotent — uses updateOrCreate keyed on slug for organizers/venues
 * and on (marketplace_client_id, slug) for activities. Re-running the
 * seeder updates field values but preserves IDs and FK relationships.
 *
 * Usage:
 *   php artisan db:seed --class=BileteOnlineActivitiesDemoSeeder
 */
class BileteOnlineActivitiesDemoSeeder extends Seeder
{
    private const MP_ID = 3;

    /** Plain-text password used for both demo organizers. Hashed via model cast. */
    private const DEMO_PASSWORD = 'demo1234';

    public function run(): void
    {
        $this->command->info("Seeding bilete.online activities demo data (marketplace_client_id = " . self::MP_ID . ")");

        $organizers = $this->seedOrganizers();
        $this->command->info('  ✓ ' . count($organizers) . ' organizatori');

        $venues = $this->seedVenues($organizers);
        $this->command->info('  ✓ ' . count($venues) . ' venues');

        $cities = $this->resolveCities();
        $categories = $this->resolveCategories();

        if (empty($cities) || empty($categories)) {
            $this->command->error('  ✗ Lipsesc orașe sau categorii pe marketplace ' . self::MP_ID . '. Rulează întâi:');
            $this->command->line('       MARKETPLACE_ID=' . self::MP_ID . ' php artisan db:seed --class=RomaniaRegionsCitiesSeeder');
            $this->command->line('       MARKETPLACE_ID=' . self::MP_ID . ' php artisan db:seed --class=MarketplaceEventCategoriesLeisureSeeder');
            return;
        }

        $activities = $this->seedActivities($organizers, $venues, $cities, $categories);
        $this->command->info('  ✓ ' . count($activities) . ' activități');

        // Refresh cheapest_price_cents after all variants are in place. The
        // observer fires on each variant write but a deliberate batch refresh
        // here mirrors what the hourly scheduler would do — handy if anyone
        // edits the seeder later and changes prices.
        \Illuminate\Support\Facades\Artisan::call('activities:refresh-intent-aggregates', [
            '--marketplace' => self::MP_ID,
            '--include-unpublished' => true,
        ]);

        $this->command->info('Done. Vezi /marketplace/activities și /activitate/{slug} pe bilete.online.');
    }

    // ============================================================
    // ORGANIZERS
    // ============================================================
    private function seedOrganizers(): array
    {
        $defs = [
            [
                'slug' => 'mystery-rooms-bucuresti',
                'email' => 'demo+mystery@bilete.online',
                'name' => 'Mystery Rooms București',
                'contact_name' => 'Andrei Popescu',
                'phone' => '+40 723 123 456',
                'description' => 'Operator escape rooms cu 3 camere diferite în centrul Bucureștiului.',
                'website' => 'https://mystery-rooms-bucuresti.example',
            ],
            [
                'slug' => 'aventura-brasov',
                'email' => 'demo+aventura@bilete.online',
                'name' => 'Aventura Brașov',
                'contact_name' => 'Maria Ionescu',
                'phone' => '+40 766 987 654',
                'description' => 'Parc de aventură outdoor în Poiana Brașov — trasee, tiroliene, ateliere creative.',
                'website' => 'https://aventura-brasov.example',
            ],
        ];

        $result = [];
        foreach ($defs as $def) {
            $result[$def['slug']] = MarketplaceOrganizer::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'marketplace_client_id' => self::MP_ID,
                    'email' => $def['email'],
                    'password' => self::DEMO_PASSWORD,   // hashed cast in model
                    'name' => $def['name'],
                    'contact_name' => $def['contact_name'],
                    'phone' => $def['phone'],
                    'description' => $def['description'],
                    'website' => $def['website'],
                    'status' => 'active',
                    'verified_at' => now(),
                    'email_verified_at' => now(),
                ]
            );
        }
        return $result;
    }

    // ============================================================
    // VENUES
    // ============================================================
    private function seedVenues(array $organizers): array
    {
        $defs = [
            [
                'key' => 'mystery-bucuresti',
                'slug' => 'mystery-rooms-centru',
                'name' => ['ro' => 'Mystery Rooms — Centru', 'en' => 'Mystery Rooms — City Center'],
                'address' => 'Str. Lipscani 25',
                'city' => 'București',
                'state' => 'București',
                'country' => 'RO',
                'lat' => 44.4314,
                'lng' => 26.1014,
                'capacity' => 30,
            ],
            [
                'key' => 'aventura-poiana',
                'slug' => 'aventura-poiana-brasov',
                'name' => ['ro' => 'Aventura Park — Poiana Brașov', 'en' => 'Aventura Park — Poiana Brașov'],
                'address' => 'Drumul Poiana Brașov 1',
                'city' => 'Poiana Brașov',
                'state' => 'Brașov',
                'country' => 'RO',
                'lat' => 45.5984,
                'lng' => 25.5519,
                'capacity' => 200,
            ],
        ];

        $result = [];
        foreach ($defs as $def) {
            $result[$def['key']] = Venue::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'tenant_id' => null,  // marketplace-only — not a tenant venue
                    'marketplace_client_id' => self::MP_ID,
                    'name' => $def['name'],
                    'address' => $def['address'],
                    'city' => $def['city'],
                    'state' => $def['state'],
                    'country' => $def['country'],
                    'lat' => $def['lat'],
                    'lng' => $def['lng'],
                    'capacity' => $def['capacity'],
                    'capacity_total' => $def['capacity'],
                    'is_partner' => true,
                    'is_featured' => false,
                ]
            );
        }
        return $result;
    }

    // ============================================================
    // ACTIVITIES
    // ============================================================
    private function seedActivities(array $organizers, array $venues, array $cities, array $categories): array
    {
        // Each definition is a fully self-contained activity spec —
        // a tiny DSL that handle()-loops into a real model + relations.
        $defs = [
            // -------- 1. Escape Room — București --------
            [
                'slug' => 'demo-camera-13-escape-room',
                'organizer' => 'mystery-rooms-bucuresti',
                'venue' => 'mystery-bucuresti',
                'city' => 'bucuresti',
                'category' => 'escape-rooms',
                'title' => ['ro' => 'Camera 13 — Escape Room Mister', 'en' => 'Room 13 — Mystery Escape'],
                'subtitle' => ['ro' => 'Cea mai aleasă cameră din 2026', 'en' => 'Top-rated room of 2026'],
                'short_description' => [
                    'ro' => 'Un escape room horror de 60 minute, în centrul Bucureștiului. Echipe de 2-4 jucători.',
                    'en' => 'A 60-minute horror escape room in central Bucharest. Teams of 2-4 players.',
                ],
                'description' => ['ro' => '<p>Intri într-o cameră aparent obișnuită. Apoi se sting luminile. Ai 60 de minute să descoperi misterul Camerei 13 și să ieși înainte ca timpul să se scurgă. Cea mai populară cameră a noastră — pretabilă pentru evening date night, team building, sau aniversări.</p>'],
                'cover_image_url' => null,
                'duration_minutes' => 60,
                'slot_interval_minutes' => 90,   // 60 min joc + 30 min reset
                'buffer_minutes' => 30,
                'capacity_per_slot' => 4,
                'min_participants' => 2,
                'max_participants' => 4,
                'booking_lead_time_hours' => 2,
                'booking_max_advance_days' => 60,
                'meeting_point' => 'La intrarea Mystery Rooms, Str. Lipscani 25. Sosirea cu 10 minute înainte de slot.',
                'included' => ['Acces 60 min', 'Game master', 'Apă'],
                'not_included' => ['Mâncare', 'Băuturi alcoolice'],
                'requirements' => ['Minim 14 ani', 'Echipe de 2-4 persoane'],
                'languages_offered' => ['ro', 'en'],
                'flags' => ['is_indoor' => true, 'is_kid_friendly' => false, 'is_weather_sensitive' => false],
                'age_min' => 14,
                'age_max' => null,
                'difficulty_level' => 'medium',
                'cancellation_policy' => 'Anularea cu minim 24h înainte de slot oferă refund integral. Anularea cu mai puțin de 24h nu este eligibilă pentru refund.',
                'is_published' => true,
                'is_featured' => true,
                'is_homepage_featured' => true,
                // Mar-Sun, 16:00-22:00 (closed Mon-Tue)
                'schedule' => [
                    [3, '16:00', '22:00'], [4, '16:00', '22:00'],
                    [5, '16:00', '23:00'], [6, '12:00', '23:00'], [7, '12:00', '22:00'],
                ],
                'variants' => [
                    ['name' => ['ro' => 'Echipa standard (2-3 jucători)', 'en' => 'Standard team (2-3)'], 'price_cents' => 18000, 'capacity_share' => 1, 'min_per_order' => 2, 'max_per_order' => 3],
                    ['name' => ['ro' => 'Echipa completă (4 jucători)', 'en' => 'Full team (4)'], 'price_cents' => 22000, 'capacity_share' => 4, 'min_per_order' => 1, 'max_per_order' => 1],
                ],
            ],

            // -------- 2. Museum — București --------
            [
                'slug' => 'demo-muzeul-curiozitatilor',
                'organizer' => 'mystery-rooms-bucuresti',  // same operator for demo simplicity
                'venue' => 'mystery-bucuresti',
                'city' => 'bucuresti',
                'category' => 'muzee-expozitii',
                'title' => ['ro' => 'Muzeul Curiozităților', 'en' => 'Museum of Curiosities'],
                'subtitle' => ['ro' => 'Experiență interactivă pentru toată familia', 'en' => 'Interactive family experience'],
                'short_description' => [
                    'ro' => '40 de exponate interactive pentru copii și adulți, deschis toată ziua.',
                    'en' => '40 interactive exhibits for children and adults, all-day access.',
                ],
                'description' => ['ro' => '<p>Un muzeu altfel — fără sticle, fără reguli rigide. Atingi, înveți, te miri. 40+ exponate interactive de știință și artă, în 1200m². Ideal pentru școli (grupuri de minim 10 elevi cu preț redus), familii cu copii peste 4 ani, și adulții curioși.</p>'],
                'duration_minutes' => 90,
                'slot_interval_minutes' => 60,
                'buffer_minutes' => 0,
                'capacity_per_slot' => 50,
                'min_participants' => 1,
                'max_participants' => 10,
                'booking_lead_time_hours' => 1,
                'booking_max_advance_days' => 90,
                'meeting_point' => 'Recepția muzeului, parter. Bilete cu QR scanate la intrare.',
                'included' => ['Acces 90 min', 'Audio guide RO/EN', 'Acces toate exponatele'],
                'not_included' => ['Mâncare', 'Suveniruri'],
                'requirements' => [],
                'languages_offered' => ['ro', 'en'],
                'flags' => ['is_indoor' => true, 'is_kid_friendly' => true, 'is_accessible' => true],
                'age_min' => 0,
                'difficulty_level' => 'easy',
                'cancellation_policy' => 'Anularea cu minim 4h înainte aduce refund integral.',
                'is_published' => true,
                'is_featured' => false,
                // Tue-Sun 10:00-19:00, closed Mon
                'schedule' => [
                    [2, '10:00', '19:00'], [3, '10:00', '19:00'], [4, '10:00', '19:00'],
                    [5, '10:00', '19:00'], [6, '10:00', '20:00'], [7, '10:00', '18:00'],
                ],
                'variants' => [
                    ['name' => ['ro' => 'Adult', 'en' => 'Adult'], 'price_cents' => 4500, 'min_age' => 13, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 10],
                    ['name' => ['ro' => 'Copil (4-12 ani)', 'en' => 'Child (4-12)'], 'price_cents' => 2500, 'min_age' => 4, 'max_age' => 12, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 10],
                    ['name' => ['ro' => 'Familie (2A + 2C)', 'en' => 'Family (2A + 2C)'], 'price_cents' => 12000, 'capacity_share' => 4, 'min_per_order' => 1, 'max_per_order' => 2],
                ],
            ],

            // -------- 3. Adventure Park — Poiana Brașov --------
            [
                'slug' => 'demo-aventura-park-poiana',
                'organizer' => 'aventura-brasov',
                'venue' => 'aventura-poiana',
                'city' => 'brasov',
                'category' => 'parcuri-de-aventura',
                'title' => ['ro' => 'Aventura Park Poiana Brașov', 'en' => 'Aventura Park Poiana Brașov'],
                'subtitle' => ['ro' => 'Trasee în copaci + 4 tiroliene + perete escaladă', 'en' => 'Tree-top course + 4 zip-lines + climbing wall'],
                'short_description' => [
                    'ro' => 'Parc de aventură outdoor, 4 nivele de dificultate. Bilet 4h. Necesar pantofi sport + îmbrăcăminte sport.',
                    'en' => 'Outdoor adventure park, 4 difficulty levels. 4-hour pass. Sportswear required.',
                ],
                'description' => ['ro' => '<p>4 trasee de aventură printre copaci, dificultate progresivă: verde (copii 4-7 ani), albastru (familii), roșu (adolescenți), negru (extremă). Plus 4 tiroliene panoramice (cea mai lungă: 240m) și perete de escaladă natural. Echipament integral inclus în bilet.</p>'],
                'duration_minutes' => 240,    // 4 ore acces
                'slot_interval_minutes' => 60,
                'buffer_minutes' => 30,
                'capacity_per_slot' => 30,
                'min_participants' => 1,
                'max_participants' => 10,
                'booking_lead_time_hours' => 3,
                'booking_max_advance_days' => 45,
                'meeting_point' => 'Intrarea principală Aventura Park, Poiana Brașov. Recepția deschide cu 30 min înainte de slot.',
                'included' => ['Acces 4h trasee', 'Echipament siguranță complet', 'Briefing 15 min', 'Instructor pe traseu'],
                'not_included' => ['Mâncare', 'Băuturi', 'Asigurare medicală'],
                'requirements' => ['Minim 4 ani (traseu verde)', 'Pantofi sport închiși', 'Îmbrăcăminte care nu se prinde'],
                'languages_offered' => ['ro', 'en', 'hu'],
                'flags' => ['is_outdoor' => true, 'is_weather_sensitive' => true, 'is_kid_friendly' => true],
                'age_min' => 4,
                'difficulty_level' => 'medium',
                'cancellation_policy' => 'Anularea cu minim 24h înainte aduce refund integral. În caz de vreme nefavorabilă, reprogramare gratuită oferită automat.',
                'is_published' => true,
                'is_featured' => true,
                'is_city_featured' => true,
                // Mon-Sun 9:00-18:00 in season — note close at 18, last slot needs to fit 4h duration
                'schedule' => [
                    [1, '09:00', '17:00'], [2, '09:00', '17:00'], [3, '09:00', '17:00'],
                    [4, '09:00', '17:00'], [5, '09:00', '18:00'], [6, '09:00', '18:00'], [7, '09:00', '18:00'],
                ],
                'variants' => [
                    ['name' => ['ro' => 'Adult', 'en' => 'Adult'], 'price_cents' => 9500, 'min_age' => 14, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 8],
                    ['name' => ['ro' => 'Copil (4-13 ani)', 'en' => 'Child (4-13)'], 'price_cents' => 6500, 'min_age' => 4, 'max_age' => 13, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 8],
                    ['name' => ['ro' => 'Grup 6+ (per persoană)', 'en' => 'Group 6+ (per person)'], 'price_cents' => 7500, 'capacity_share' => 1, 'min_per_order' => 6, 'max_per_order' => 12],
                ],
            ],

            // -------- 4. Workshop — Brașov, weekend-only --------
            [
                'slug' => 'demo-atelier-ceramica-copii',
                'organizer' => 'aventura-brasov',
                'venue' => 'aventura-poiana',
                'city' => 'brasov',
                'category' => 'ateliere-experiente-creative',
                'title' => ['ro' => 'Atelier ceramică pentru copii', 'en' => 'Kids ceramics workshop'],
                'subtitle' => ['ro' => 'Sâmbătă + Duminică, 4-12 ani', 'en' => 'Weekends, ages 4-12'],
                'short_description' => [
                    'ro' => 'Atelier de modelaj ceramic pentru copii. 90 min, 8 copii max, materiale incluse.',
                    'en' => 'Kids ceramics workshop. 90 minutes, max 8 children, materials included.',
                ],
                'description' => ['ro' => '<p>Copilul tău descoperă bucuria modelajului în lut: prima oală, primul vas, primul cadou făcut de el. Atelierul durează 90 minute. Ne ocupăm de tot — șorț, materiale, lut, arderea finală. Tu vii doar să iei produsul finit a doua săptămână.</p>'],
                'duration_minutes' => 90,
                'slot_interval_minutes' => 120,
                'buffer_minutes' => 30,
                'capacity_per_slot' => 8,
                'min_participants' => 1,
                'max_participants' => 4,
                'booking_lead_time_hours' => 24,
                'booking_max_advance_days' => 30,
                'meeting_point' => 'Aventura Park, sala ateliere (etaj 1). Părinții pot aștepta în cafenea.',
                'included' => ['Materiale (lut, glazuri)', 'Șorț', 'Instructor', 'Arderea ceramică', 'Pick-up săptămâna următoare'],
                'not_included' => ['Mâncare', 'Băuturi', 'Transport pick-up'],
                'requirements' => ['Vârstă 4-12 ani', 'Haine care se pot murdări'],
                'languages_offered' => ['ro'],
                'flags' => ['is_indoor' => true, 'is_kid_friendly' => true, 'is_accessible' => true],
                'age_min' => 4,
                'age_max' => 12,
                'difficulty_level' => 'easy',
                'cancellation_policy' => 'Anularea cu minim 48h înainte aduce refund integral. Materialele sunt deja pregătite — sub 48h nu se mai poate.',
                'is_published' => true,
                'is_featured' => false,
                // Doar Sâmbătă + Duminică, 10:00-16:00
                'schedule' => [
                    [6, '10:00', '16:00'],
                    [7, '10:00', '16:00'],
                ],
                'variants' => [
                    ['name' => ['ro' => '1 copil', 'en' => '1 child'], 'price_cents' => 8500, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 4],
                    ['name' => ['ro' => '2 frați (10% reducere)', 'en' => '2 siblings (10% off)'], 'price_cents' => 15300, 'capacity_share' => 2, 'min_per_order' => 1, 'max_per_order' => 2],
                ],
            ],

            // -------- 5. Walking Tour — București, single slot/day --------
            [
                'slug' => 'demo-bucuresti-walking-tour',
                'organizer' => 'mystery-rooms-bucuresti',
                'venue' => 'mystery-bucuresti',
                'city' => 'bucuresti',
                'category' => 'tururi-experiente-turistice',
                'title' => ['ro' => 'București Walking Tour — Centrul Vechi', 'en' => 'Bucharest Walking Tour — Old Town'],
                'subtitle' => ['ro' => '2 ore, ghid certificat, 10 locuri max', 'en' => '2 hours, certified guide, 10 spots max'],
                'short_description' => [
                    'ro' => 'Tur ghidat de 2 ore în Centrul Vechi al Bucureștiului. Pleacă zilnic la 10:00. Doar 10 locuri.',
                    'en' => '2-hour guided walking tour of Bucharest Old Town. Daily at 10:00. Only 10 spots.',
                ],
                'description' => ['ro' => '<p>Bucureștiul are mai multă istorie decât pare la prima privire. Tur pe jos de 2 ore prin Centrul Vechi (Lipscani, Hanul lui Manuc, Curtea Veche, Stavropoleos, Macca-Vilacrosse). Ghidul nostru este istoric absolvent — povești adevărate, anecdote, locuri pe care turistii obisnuiti le rateaza.</p>'],
                'duration_minutes' => 120,
                'slot_interval_minutes' => 120,
                'buffer_minutes' => 0,
                'capacity_per_slot' => 10,
                'min_participants' => 1,
                'max_participants' => 6,
                'booking_lead_time_hours' => 12,
                'booking_max_advance_days' => 60,
                'meeting_point' => 'Statuia lui Manole, Str. Lipscani. Ghidul te așteaptă cu o pancartă "BILETE.ONLINE TOUR".',
                'included' => ['Ghid certificat', 'Tur 2h', 'Acces în 2 obiective'],
                'not_included' => ['Mâncare', 'Băuturi', 'Acces alte muzee'],
                'requirements' => ['Pantofi confortabili pentru mers'],
                'languages_offered' => ['ro', 'en'],
                'flags' => ['is_outdoor' => true, 'is_weather_sensitive' => true, 'is_kid_friendly' => true],
                'age_min' => 6,
                'difficulty_level' => 'easy',
                'cancellation_policy' => 'Anularea cu minim 12h înainte aduce refund integral. În caz de ploaie torențială, reprogramare automată.',
                'is_published' => true,
                'is_featured' => false,
                // Daily 10:00-12:00 — single slot per day
                'schedule' => [
                    [1, '10:00', '12:00'], [2, '10:00', '12:00'], [3, '10:00', '12:00'],
                    [4, '10:00', '12:00'], [5, '10:00', '12:00'], [6, '10:00', '12:00'], [7, '10:00', '12:00'],
                ],
                'variants' => [
                    ['name' => ['ro' => 'Adult', 'en' => 'Adult'], 'price_cents' => 6500, 'min_age' => 13, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 6],
                    ['name' => ['ro' => 'Copil (6-12 ani)', 'en' => 'Child (6-12)'], 'price_cents' => 3500, 'min_age' => 6, 'max_age' => 12, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 6],
                    ['name' => ['ro' => 'Student/Senior', 'en' => 'Student/Senior'], 'price_cents' => 4500, 'capacity_share' => 1, 'min_per_order' => 1, 'max_per_order' => 6],
                ],
            ],
        ];

        $result = [];
        foreach ($defs as $def) {
            $organizer = $organizers[$def['organizer']] ?? null;
            $venue = $venues[$def['venue']] ?? null;
            $cityId = $cities[$def['city']] ?? null;
            $categoryId = $categories[$def['category']] ?? null;

            if (! $organizer || ! $venue) {
                $this->command->warn("Skipping {$def['slug']}: missing organizer or venue");
                continue;
            }

            $activity = Activity::updateOrCreate(
                [
                    'marketplace_client_id' => self::MP_ID,
                    'slug' => $def['slug'],
                ],
                [
                    'marketplace_organizer_id' => $organizer->id,
                    'venue_id' => $venue->id,
                    'marketplace_city_id' => $cityId,
                    'marketplace_category_id' => $categoryId,
                    'title' => $def['title'],
                    'subtitle' => $def['subtitle'] ?? null,
                    'short_description' => $def['short_description'] ?? null,
                    'description' => $def['description'] ?? null,
                    'cover_image_url' => $def['cover_image_url'] ?? null,
                    'duration_minutes' => $def['duration_minutes'],
                    'slot_interval_minutes' => $def['slot_interval_minutes'],
                    'buffer_minutes' => $def['buffer_minutes'] ?? 0,
                    'capacity_per_slot' => $def['capacity_per_slot'],
                    'min_participants' => $def['min_participants'] ?? 1,
                    'max_participants' => $def['max_participants'] ?? 10,
                    'booking_lead_time_hours' => $def['booking_lead_time_hours'] ?? 2,
                    'booking_max_advance_days' => $def['booking_max_advance_days'] ?? 60,
                    'meeting_point' => $def['meeting_point'] ?? null,
                    'included_items' => $def['included'] ?? [],
                    'not_included' => $def['not_included'] ?? [],
                    'requirements' => $def['requirements'] ?? [],
                    'languages_offered' => $def['languages_offered'] ?? ['ro'],
                    'cancellation_policy' => $def['cancellation_policy'] ?? null,
                    'age_min' => $def['age_min'] ?? null,
                    'age_max' => $def['age_max'] ?? null,
                    'difficulty_level' => $def['difficulty_level'] ?? null,
                    'is_indoor' => $def['flags']['is_indoor'] ?? false,
                    'is_outdoor' => $def['flags']['is_outdoor'] ?? false,
                    'is_kid_friendly' => $def['flags']['is_kid_friendly'] ?? false,
                    'is_accessible' => $def['flags']['is_accessible'] ?? false,
                    'is_weather_sensitive' => $def['flags']['is_weather_sensitive'] ?? false,
                    'is_published' => $def['is_published'] ?? true,
                    'is_featured' => $def['is_featured'] ?? false,
                    'is_homepage_featured' => $def['is_homepage_featured'] ?? false,
                    'is_category_featured' => $def['is_category_featured'] ?? false,
                    'is_city_featured' => $def['is_city_featured'] ?? false,
                ]
            );

            // Wipe + reseed schedules (deterministic across re-runs).
            $activity->schedules()->delete();
            foreach ($def['schedule'] as [$dow, $open, $close]) {
                $activity->schedules()->create([
                    'day_of_week' => $dow,
                    'open_time' => $open . ':00',
                    'close_time' => $close . ':00',
                    'is_active' => true,
                    'sort_order' => 0,
                ]);
            }

            // One exception: Romania holiday — Crăciun. Gives QA a concrete date
            // to test the "closed exception" path.
            $activity->scheduleExceptions()->updateOrCreate(
                ['exception_date' => '2026-12-25'],
                ['is_closed' => true, 'reason' => 'Crăciun — închis']
            );

            // Variants — wipe + reseed too.
            $activity->variants()->forceDelete();
            $sort = 0;
            foreach ($def['variants'] as $v) {
                $activity->variants()->create([
                    'name' => $v['name'],
                    'sku' => Str::upper(Str::slug($def['slug'])) . '-V' . (++$sort),
                    'price_cents' => $v['price_cents'],
                    'currency' => 'RON',
                    'min_age' => $v['min_age'] ?? null,
                    'max_age' => $v['max_age'] ?? null,
                    'capacity_share' => $v['capacity_share'] ?? 1,
                    'min_per_order' => $v['min_per_order'] ?? 0,
                    'max_per_order' => $v['max_per_order'] ?? 10,
                    'is_active' => true,
                    'is_refundable' => true,
                    'sort_order' => $sort,
                ]);
            }

            $result[] = $activity;
        }

        return $result;
    }

    // ============================================================
    // CITIES / CATEGORIES lookup helpers
    // ============================================================

    /** @return array<string, int>  slug → id */
    private function resolveCities(): array
    {
        return MarketplaceCity::where('marketplace_client_id', self::MP_ID)
            ->pluck('id', 'slug')
            ->toArray();
    }

    /** @return array<string, int>  slug → id (only top-level categories) */
    private function resolveCategories(): array
    {
        return MarketplaceCategory::where('marketplace_client_id', self::MP_ID)
            ->whereNull('parent_id')
            ->pluck('id', 'slug')
            ->toArray();
    }
}
