<?php

namespace Database\Seeders;

use App\Models\MarketplaceEventCategory;
use Illuminate\Database\Seeder;

/**
 * Second pass: populates SEO/content for the bilete.online (marketplace_client_id = 3)
 * event SUBcategories that existed in DB but had NO entry in the first SEO CSV.
 * Companion to BileteOnlineEventCategoriesSeoSeeder.
 *
 * Source: resources/marketplaces/bileteonline/documents/
 *         "bilete-online-categorii-lipsa-descrieri-seo - Categorii lipsa.csv"
 *
 * Column mapping (CSV -> model field):
 *   Titlu RO              -> name['ro']
 *   Titlu EN             -> name['en']
 *   Descriere scurta RO  -> description['ro']
 *   Descriere scurta EN  -> description['en']
 *   Descriere SEO RO     -> seo_body['ro']   (SEO Body / Corp text)
 *   Descriere SEO EN     -> seo_body['en']
 *   Meta title RO        -> meta_title['ro']
 *   Meta title EN        -> meta_title['en']
 *   Meta description RO  -> meta_description['ro']
 *   Meta description EN  -> meta_description['en']
 *   (seo_body_title is intentionally left untouched)
 *
 * Matching is by slug, resolved against the live DB by RO name within the set
 * of 18 rows the first seeder left empty. NOTE: in the CSV, Muzee / Galerii /
 * Expozitii are labelled under parent "Muzee & expozitii", but in the live tree
 * they sit under "Cultura & arta" (ids 124/125/126) — they are matched by RO
 * name against that missing set, so the parent label discrepancy is harmless.
 *
 * Rows are UPDATED IN PLACE (no create / no delete) -> fully non-breaking.
 * Idempotent. Run:
 *   php artisan db:seed --class=BileteOnlineEventCategoriesSeoMissingSeeder
 */
class BileteOnlineEventCategoriesSeoMissingSeeder extends Seeder
{
    protected const MARKETPLACE_CLIENT_ID = 3;

    public function run(): void
    {
        $mcId = self::MARKETPLACE_CLIENT_ID;
        $this->command->info("Populating SEO/content for the remaining bilete.online subcategories (marketplace_client_id={$mcId})");

        $updated = 0;
        $missing = 0;

        foreach ($this->data() as $item) {
            $category = MarketplaceEventCategory::where('marketplace_client_id', $mcId)
                ->where('slug', $item['slug'])
                ->first();

            if (! $category) {
                $missing++;
                $this->command->warn("  ! slug not found, skipped: {$item['slug']}");
                continue;
            }

            $category->name             = $this->merge($category->name, $item['name']);
            $category->description      = $this->merge($category->description, $item['description']);
            $category->meta_title       = $this->merge($category->meta_title, $item['meta_title']);
            $category->meta_description = $this->merge($category->meta_description, $item['meta_description']);
            $category->seo_body         = $this->merge($category->seo_body, $item['seo_body']);
            $category->save();

            $updated++;
            $this->command->line("  ✓ {$item['slug']}");
        }

        $this->command->info("Done. Updated: {$updated} | Missing (skipped): {$missing}");
    }

    /**
     * Overlay non-null CSV values onto the existing translation array,
     * preserving any locale the CSV does not supply.
     */
    protected function merge($existing, array $incoming): array
    {
        $existing = is_array($existing) ? $existing : [];
        foreach ($incoming as $locale => $value) {
            if ($value !== null && $value !== '') {
                $existing[$locale] = $value;
            }
        }
        return $existing;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function data(): array
    {
        return array (
  0 => array(
    'slug' => 'escape-rooms-adventure-escape-rooms',
    'id' => 21,
    'name' => array(
      'ro' => 'Escape rooms adventure',
      'en' => 'Adventure escape rooms',
    ),
    'description' => array(
      'ro' => 'Camere de escape cu misiuni dinamice, explorare și provocări de echipă.',
      'en' => 'Dynamic escape room adventures with missions, exploration and team challenges.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms adventure | Bilete și experiențe',
      'en' => 'Adventure escape rooms | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă escape rooms adventure cu scenarii pline de acțiune, indicii, misiuni și provocări interactive. Sunt potrivite pentru prieteni, familie,...',
      'en' => 'Discover adventure escape rooms with action-driven scenarios, clues, missions and interactive challenges. Ideal for friends, families, teenagers or...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă escape rooms adventure cu scenarii pline de acțiune, indicii, misiuni și provocări interactive. Sunt potrivite pentru prieteni, familie, adolescenți sau echipe care vor o experiență captivantă, bazată pe explorare, colaborare și adrenalină.',
      'en' => 'Discover adventure escape rooms with action-driven scenarios, clues, missions and interactive challenges. Ideal for friends, families, teenagers or teams looking for a captivating experience built around exploration, collaboration and adrenaline.',
    ),
  ),
  1 => array(
    'slug' => 'escape-rooms-fantasy-sci-fi-escape-rooms',
    'id' => 22,
    'name' => array(
      'ro' => 'Escape rooms fantasy / sci-fi',
      'en' => 'Fantasy / sci-fi escape rooms',
    ),
    'description' => array(
      'ro' => 'Camere tematice inspirate de lumi fantastice, tehnologie și povești SF.',
      'en' => 'Themed rooms inspired by fantasy worlds, technology and sci-fi stories.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms fantasy / sci-fi | Bilete și experiențe',
      'en' => 'Fantasy / sci-fi escape rooms | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Alege escape rooms fantasy și sci-fi pentru aventuri în lumi imaginare, laboratoare futuriste, universuri fantastice sau misiuni tehnologice. Sunt...',
      'en' => 'Choose fantasy and sci-fi escape rooms for adventures in imaginary worlds, futuristic labs, magical universes or tech-driven missions. Ideal for groups...',
    ),
    'seo_body' => array(
      'ro' => 'Alege escape rooms fantasy și sci-fi pentru aventuri în lumi imaginare, laboratoare futuriste, universuri fantastice sau misiuni tehnologice. Sunt experiențe ideale pentru grupuri care caută decoruri imersive, povești spectaculoase și provocări creative.',
      'en' => 'Choose fantasy and sci-fi escape rooms for adventures in imaginary worlds, futuristic labs, magical universes or tech-driven missions. Ideal for groups looking for immersive settings, spectacular stories and creative challenges.',
    ),
  ),
  2 => array(
    'slug' => 'escape-rooms-pentru-adolescenti-escape-rooms',
    'id' => 24,
    'name' => array(
      'ro' => 'Escape rooms pentru adolescenți',
      'en' => 'Escape rooms for teenagers',
    ),
    'description' => array(
      'ro' => 'Escape rooms adaptate adolescenților, cu mister, logică și colaborare.',
      'en' => 'Escape rooms for teenagers, with mystery, logic and collaboration.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms pentru adolescenți | Bilete și experiențe',
      'en' => 'Escape rooms for teenagers | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă escape rooms pentru adolescenți, cu provocări potrivite vârstei, scenarii captivante și enigme care stimulează comunicarea și gândirea logică....',
      'en' => 'Discover escape rooms for teenagers, with age-appropriate challenges, captivating scenarios and puzzles that encourage communication and logical...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă escape rooms pentru adolescenți, cu provocări potrivite vârstei, scenarii captivante și enigme care stimulează comunicarea și gândirea logică. Sunt ideale pentru grupuri de prieteni, excursii, aniversări sau activități de școală.',
      'en' => 'Discover escape rooms for teenagers, with age-appropriate challenges, captivating scenarios and puzzles that encourage communication and logical thinking. Ideal for groups of friends, school outings, birthdays or class activities.',
    ),
  ),
  3 => array(
    'slug' => 'activitati-pentru-familie-familie-copii',
    'id' => 104,
    'name' => array(
      'ro' => 'Activități pentru familie',
      'en' => 'Family activities',
    ),
    'description' => array(
      'ro' => 'Experiențe potrivite pentru părinți, copii și timp petrecut împreună.',
      'en' => 'Experiences for parents, children and quality time together.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru familie | Bilete și experiențe',
      'en' => 'Family activities | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Găsește activități pentru familie: muzee, parcuri, ateliere, natură, spectacole, ferme și experiențe interactive. Alege idei de weekend sau vacanță care...',
      'en' => 'Find family activities including museums, parks, workshops, nature, shows, farms and interactive experiences. Choose weekend or holiday ideas that bring...',
    ),
    'seo_body' => array(
      'ro' => 'Găsește activități pentru familie: muzee, parcuri, ateliere, natură, spectacole, ferme și experiențe interactive. Alege idei de weekend sau vacanță care aduc împreună copiii și adulții într-un mod distractiv, sigur și memorabil.',
      'en' => 'Find family activities including museums, parks, workshops, nature, shows, farms and interactive experiences. Choose weekend or holiday ideas that bring children and adults together in a fun, safe and memorable way.',
    ),
  ),
  4 => array(
    'slug' => 'activitati-pentru-gradinite-familie-copii',
    'id' => 105,
    'name' => array(
      'ro' => 'Activități pentru grădinițe',
      'en' => 'Activities for kindergartens',
    ),
    'description' => array(
      'ro' => 'Activități blânde, sigure și educative pentru preșcolari.',
      'en' => 'Gentle, safe and educational activities for preschool children.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru grădinițe | Bilete și experiențe',
      'en' => 'Activities for kindergartens | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă activități pentru grădinițe, create pentru copii mici: ateliere, vizite, natură, ferme educative, spectacole și experiențe senzoriale. Sunt...',
      'en' => 'Discover activities for kindergartens, designed for young children: workshops, visits, nature, educational farms, shows and sensory experiences....',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă activități pentru grădinițe, create pentru copii mici: ateliere, vizite, natură, ferme educative, spectacole și experiențe senzoriale. Sunt potrivite pentru grupe organizate, ieșiri educative și învățare prin joacă.',
      'en' => 'Discover activities for kindergartens, designed for young children: workshops, visits, nature, educational farms, shows and sensory experiences. Suitable for organized groups, learning outings and play-based discovery.',
    ),
  ),
  5 => array(
    'slug' => 'activitati-pentru-scoli-familie-copii',
    'id' => 106,
    'name' => array(
      'ro' => 'Activități pentru școli',
      'en' => 'Activities for schools',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru clase, excursii și grupuri de elevi.',
      'en' => 'Organized experiences for classes, school trips and student groups.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru școli | Bilete și experiențe',
      'en' => 'Activities for schools | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Alege activități pentru școli, cu programe educative, vizite ghidate, ateliere, muzee, tururi sau experiențe outdoor. Sunt potrivite pentru excursii,...',
      'en' => 'Choose activities for schools with educational programs, guided visits, workshops, museums, tours and outdoor experiences. Ideal for school trips,...',
    ),
    'seo_body' => array(
      'ro' => 'Alege activități pentru școli, cu programe educative, vizite ghidate, ateliere, muzee, tururi sau experiențe outdoor. Sunt potrivite pentru excursii, Școala Altfel, Săptămâna Verde și activități de învățare experiențială.',
      'en' => 'Choose activities for schools with educational programs, guided visits, workshops, museums, tours and outdoor experiences. Ideal for school trips, alternative school weeks, green week programs and experiential learning.',
    ),
  ),
  6 => array(
    'slug' => 'activitati-pentru-adolescenti-familie-copii',
    'id' => 107,
    'name' => array(
      'ro' => 'Activități pentru adolescenți',
      'en' => 'Activities for teenagers',
    ),
    'description' => array(
      'ro' => 'Activități dinamice pentru adolescenți, grupuri de prieteni și clase.',
      'en' => 'Dynamic activities for teenagers, groups of friends and school classes.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru adolescenți | Bilete și experiențe',
      'en' => 'Activities for teenagers | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă activități pentru adolescenți: escape rooms, parcuri de aventură, tururi, ateliere creative, experiențe STEM și activități de grup. Sunt...',
      'en' => 'Discover activities for teenagers: escape rooms, adventure parks, tours, creative workshops, STEM experiences and group activities. Suitable for...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă activități pentru adolescenți: escape rooms, parcuri de aventură, tururi, ateliere creative, experiențe STEM și activități de grup. Sunt potrivite pentru prieteni, școli, zile de naștere sau ieșiri active de weekend.',
      'en' => 'Discover activities for teenagers: escape rooms, adventure parks, tours, creative workshops, STEM experiences and group activities. Suitable for friends, schools, birthdays and active weekend outings.',
    ),
  ),
  7 => array(
    'slug' => 'activitati-pentru-zile-de-nastere-familie-copii',
    'id' => 108,
    'name' => array(
      'ro' => 'Activități pentru zile de naștere',
      'en' => 'Birthday activities',
    ),
    'description' => array(
      'ro' => 'Idei de activități pentru aniversări memorabile, copii sau adulți.',
      'en' => 'Activity ideas for memorable birthdays, for children or adults.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru zile de naștere | Bilete și experiențe',
      'en' => 'Birthday activities | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Găsește activități pentru zile de naștere: ateliere, escape rooms, locuri de joacă, parcuri, experiențe creative, private events sau pachete pentru...',
      'en' => 'Find birthday activities including workshops, escape rooms, playgrounds, parks, creative experiences, private events and group packages. Choose easy-to-...',
    ),
    'seo_body' => array(
      'ro' => 'Găsește activități pentru zile de naștere: ateliere, escape rooms, locuri de joacă, parcuri, experiențe creative, private events sau pachete pentru grupuri. Alege variante ușor de organizat pentru aniversări relaxate și memorabile.',
      'en' => 'Find birthday activities including workshops, escape rooms, playgrounds, parks, creative experiences, private events and group packages. Choose easy-to-organize options for relaxed and memorable celebrations.',
    ),
  ),
  8 => array(
    'slug' => 'ateliere-copii-familie-copii',
    'id' => 110,
    'name' => array(
      'ro' => 'Ateliere copii',
      'en' => 'Kids workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere creative, educative și practice pentru copii.',
      'en' => 'Creative, educational and hands-on workshops for children.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere copii | Bilete și experiențe',
      'en' => 'Kids workshops | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă ateliere pentru copii cu pictură, ceramică, știință, craft, povești, natură sau activități tematice. Sunt potrivite pentru weekend, vacanțe,...',
      'en' => 'Discover kids workshops with painting, pottery, science, craft, storytelling, nature and themed activities. Ideal for weekends, holidays, schools,...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă ateliere pentru copii cu pictură, ceramică, știință, craft, povești, natură sau activități tematice. Sunt potrivite pentru weekend, vacanțe, școli, grădinițe, aniversări și dezvoltarea creativității prin joacă.',
      'en' => 'Discover kids workshops with painting, pottery, science, craft, storytelling, nature and themed activities. Ideal for weekends, holidays, schools, kindergartens, birthdays and developing creativity through play.',
    ),
  ),
  9 => array(
    'slug' => 'muzee-interactive-copii-familie-copii',
    'id' => 111,
    'name' => array(
      'ro' => 'Muzee interactive copii',
      'en' => 'Interactive museums for kids',
    ),
    'description' => array(
      'ro' => 'Muzee unde copiii pot explora, testa și învăța prin joacă.',
      'en' => 'Museums where children can explore, test and learn through play.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee interactive copii | Bilete și experiențe',
      'en' => 'Interactive museums for kids | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Alege muzee interactive pentru copii, cu exponate practice, instalații, experimente și activități adaptate vârstei. Sunt experiențe ideale pentru...',
      'en' => 'Choose interactive museums for children with hands-on exhibits, installations, experiments and age-appropriate activities. Ideal for families, schools...',
    ),
    'seo_body' => array(
      'ro' => 'Alege muzee interactive pentru copii, cu exponate practice, instalații, experimente și activități adaptate vârstei. Sunt experiențe ideale pentru familii, școli și grădinițe care vor învățare prin explorare activă.',
      'en' => 'Choose interactive museums for children with hands-on exhibits, installations, experiments and age-appropriate activities. Ideal for families, schools and kindergartens looking for active learning through exploration.',
    ),
  ),
  10 => array(
    'slug' => 'muzee-cultura-arta',
    'id' => 124,
    'name' => array(
      'ro' => 'Muzee',
      'en' => 'Museums',
    ),
    'description' => array(
      'ro' => 'Spații culturale și educative pentru artă, istorie, știință și patrimoniu.',
      'en' => 'Cultural and educational spaces for art, history, science and heritage.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee | Bilete și experiențe',
      'en' => 'Museums | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă muzee pentru toate vârstele: artă, istorie, știință, natură, patrimoniu și experiențe interactive. Alege vizite culturale, educative sau de...',
      'en' => 'Discover museums for all ages: art, history, science, nature, heritage and interactive experiences. Choose cultural, educational or weekend visits for...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă muzee pentru toate vârstele: artă, istorie, știință, natură, patrimoniu și experiențe interactive. Alege vizite culturale, educative sau de weekend pentru familie, turiști, școli și grupuri.',
      'en' => 'Discover museums for all ages: art, history, science, nature, heritage and interactive experiences. Choose cultural, educational or weekend visits for families, tourists, schools and groups.',
    ),
  ),
  11 => array(
    'slug' => 'galerii-cultura-arta',
    'id' => 125,
    'name' => array(
      'ro' => 'Galerii',
      'en' => 'Galleries',
    ),
    'description' => array(
      'ro' => 'Galerii cu artă, expoziții curatoriate și artiști contemporani.',
      'en' => 'Galleries with art, curated exhibitions and contemporary artists.',
    ),
    'meta_title' => array(
      'ro' => 'Galerii | Bilete și experiențe',
      'en' => 'Galleries | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Explorează galerii de artă cu expoziții contemporane, lucrări de artiști locali sau internaționali, instalații și evenimente curatoriate. Sunt potrivite...',
      'en' => 'Explore art galleries with contemporary exhibitions, works by local or international artists, installations and curated events. Suitable for art lovers,...',
    ),
    'seo_body' => array(
      'ro' => 'Explorează galerii de artă cu expoziții contemporane, lucrări de artiști locali sau internaționali, instalații și evenimente curatoriate. Sunt potrivite pentru iubitori de artă, turiști, colecționari și public curios.',
      'en' => 'Explore art galleries with contemporary exhibitions, works by local or international artists, installations and curated events. Suitable for art lovers, tourists, collectors and curious visitors.',
    ),
  ),
  12 => array(
    'slug' => 'expozitii-cultura-arta',
    'id' => 126,
    'name' => array(
      'ro' => 'Expoziții',
      'en' => 'Exhibitions',
    ),
    'description' => array(
      'ro' => 'Expoziții de artă, știință, istorie, fotografie sau design.',
      'en' => 'Exhibitions about art, science, history, photography or design.',
    ),
    'meta_title' => array(
      'ro' => 'Expoziții | Bilete și experiențe',
      'en' => 'Exhibitions | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă expoziții permanente și temporare, de la artă și fotografie la știință, design, istorie sau experiențe interactive. Alege evenimente culturale...',
      'en' => 'Discover permanent and temporary exhibitions, from art and photography to science, design, history and interactive experiences. Choose current cultural...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă expoziții permanente și temporare, de la artă și fotografie la știință, design, istorie sau experiențe interactive. Alege evenimente culturale actuale, vizite de weekend și activități potrivite pentru toate vârstele.',
      'en' => 'Discover permanent and temporary exhibitions, from art and photography to science, design, history and interactive experiences. Choose current cultural events, weekend visits and activities for all ages.',
    ),
  ),
  13 => array(
    'slug' => 'tururi-culturale-cultura-arta',
    'id' => 127,
    'name' => array(
      'ro' => 'Tururi culturale',
      'en' => 'Cultural tours',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate despre artă, patrimoniu, tradiții și povești locale.',
      'en' => 'Guided tours about art, heritage, traditions and local stories.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi culturale | Bilete și experiențe',
      'en' => 'Cultural tours | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Alege tururi culturale pentru a descoperi orașe, cartiere, monumente, muzee, galerii, tradiții și povești locale. Sunt potrivite pentru turiști,...',
      'en' => 'Choose cultural tours to discover cities, neighborhoods, monuments, museums, galleries, traditions and local stories. Suitable for tourists, locals,...',
    ),
    'seo_body' => array(
      'ro' => 'Alege tururi culturale pentru a descoperi orașe, cartiere, monumente, muzee, galerii, tradiții și povești locale. Sunt potrivite pentru turiști, localnici, școli și grupuri care vor experiențe culturale ghidate.',
      'en' => 'Choose cultural tours to discover cities, neighborhoods, monuments, museums, galleries, traditions and local stories. Suitable for tourists, locals, schools and groups looking for guided cultural experiences.',
    ),
  ),
  14 => array(
    'slug' => 'ateliere-artistice-cultura-arta',
    'id' => 128,
    'name' => array(
      'ro' => 'Ateliere artistice',
      'en' => 'Art workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere de creație pentru pictură, ceramică, craft și expresie artistică.',
      'en' => 'Creative workshops for painting, pottery, craft and artistic expression.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere artistice | Bilete și experiențe',
      'en' => 'Art workshops | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă ateliere artistice pentru copii, adulți sau grupuri: pictură, ceramică, desen, colaj, design floral, craft și alte experiențe creative. Sunt...',
      'en' => 'Discover art workshops for children, adults or groups: painting, pottery, drawing, collage, floral design, craft and other creative experiences. Ideal...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă ateliere artistice pentru copii, adulți sau grupuri: pictură, ceramică, desen, colaj, design floral, craft și alte experiențe creative. Sunt ideale pentru relaxare, învățare și timp de calitate.',
      'en' => 'Discover art workshops for children, adults or groups: painting, pottery, drawing, collage, floral design, craft and other creative experiences. Ideal for relaxation, learning and quality time.',
    ),
  ),
  15 => array(
    'slug' => 'experiente-imersive-cultura-arta',
    'id' => 129,
    'name' => array(
      'ro' => 'Experiențe imersive',
      'en' => 'Immersive experiences',
    ),
    'description' => array(
      'ro' => 'Experiențe multisenzoriale cu lumină, sunet, proiecții și interacțiune.',
      'en' => 'Multisensory experiences with light, sound, projections and interaction.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe imersive | Bilete și experiențe',
      'en' => 'Immersive experiences | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Alege experiențe imersive cu proiecții, sunet, lumină, instalații digitale și decoruri interactive. Sunt potrivite pentru cupluri, prieteni, familii și...',
      'en' => 'Choose immersive experiences with projections, sound, light, digital installations and interactive environments. Suitable for couples, friends, families...',
    ),
    'seo_body' => array(
      'ro' => 'Alege experiențe imersive cu proiecții, sunet, lumină, instalații digitale și decoruri interactive. Sunt potrivite pentru cupluri, prieteni, familii și vizitatori care caută activități moderne, spectaculoase și memorabile.',
      'en' => 'Choose immersive experiences with projections, sound, light, digital installations and interactive environments. Suitable for couples, friends, families and visitors looking for modern, spectacular and memorable activities.',
    ),
  ),
  16 => array(
    'slug' => 'evenimente-culturale-cu-bilete-cultura-arta',
    'id' => 130,
    'name' => array(
      'ro' => 'Evenimente culturale cu bilete',
      'en' => 'Ticketed cultural events',
    ),
    'description' => array(
      'ro' => 'Evenimente culturale accesibile prin bilet: spectacole, expoziții și seri speciale.',
      'en' => 'Ticketed cultural events: shows, exhibitions and special evenings.',
    ),
    'meta_title' => array(
      'ro' => 'Evenimente culturale cu bilete | Bilete și experiențe',
      'en' => 'Ticketed cultural events | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă evenimente culturale cu bilete: teatru, concerte, expoziții, proiecții, seri tematice, festivaluri și activități artistice. Alege experiențe...',
      'en' => 'Discover ticketed cultural events: theatre, concerts, exhibitions, screenings, themed evenings, festivals and artistic activities. Choose cultural...',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă evenimente culturale cu bilete: teatru, concerte, expoziții, proiecții, seri tematice, festivaluri și activități artistice. Alege experiențe culturale pentru familie, cupluri, prieteni sau grupuri.',
      'en' => 'Discover ticketed cultural events: theatre, concerts, exhibitions, screenings, themed evenings, festivals and artistic activities. Choose cultural experiences for families, couples, friends or groups.',
    ),
  ),
  17 => array(
    'slug' => 'activitati-de-patrimoniu-cultura-arta',
    'id' => 131,
    'name' => array(
      'ro' => 'Activități de patrimoniu',
      'en' => 'Heritage activities',
    ),
    'description' => array(
      'ro' => 'Experiențe care valorifică istoria, arhitectura, tradițiile și patrimoniul local.',
      'en' => 'Experiences around history, architecture, traditions and local heritage.',
    ),
    'meta_title' => array(
      'ro' => 'Activități de patrimoniu | Bilete și experiențe',
      'en' => 'Heritage activities | Tickets & experiences',
    ),
    'meta_description' => array(
      'ro' => 'Explorează activități de patrimoniu prin tururi, vizite ghidate, muzee, situri istorice, case memoriale, monumente și experiențe culturale locale. Sunt...',
      'en' => 'Explore heritage activities through tours, guided visits, museums, historical sites, memorial houses, monuments and local cultural experiences. Suitable...',
    ),
    'seo_body' => array(
      'ro' => 'Explorează activități de patrimoniu prin tururi, vizite ghidate, muzee, situri istorice, case memoriale, monumente și experiențe culturale locale. Sunt potrivite pentru turiști, școli, familii și pasionați de istorie.',
      'en' => 'Explore heritage activities through tours, guided visits, museums, historical sites, memorial houses, monuments and local cultural experiences. Suitable for tourists, schools, families and history enthusiasts.',
    ),
  ),
);
    }
}
