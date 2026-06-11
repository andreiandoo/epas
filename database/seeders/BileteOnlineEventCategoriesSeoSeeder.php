<?php

namespace Database\Seeders;

use App\Models\MarketplaceEventCategory;
use Illuminate\Database\Seeder;

/**
 * Populates SEO/content fields for bilete.online (marketplace_client_id = 3)
 * event categories that ALREADY EXIST in the database.
 *
 * Source: resources/marketplaces/bileteonline/documents/
 *         "bilete-online-categorii-descrieri-seo - Categorii SEO.csv"
 *
 * Column mapping (CSV -> model field):
 *   Categorie / Subcategorie RO   -> name['ro']
 *   Category / Subcategory EN      -> name['en']
 *   Descriere scurta RO            -> description['ro']
 *   Short description EN           -> description['en']
 *   Meta title RO                  -> meta_title['ro']
 *   Meta description RO            -> meta_description['ro']
 *   Meta title EN                  -> meta_title['en']
 *   Meta description EN            -> meta_description['en']
 *   Descriere SEO RO               -> seo_body['ro']   (SEO Body / Corp text)
 *   SEO description EN             -> seo_body['en']
 *   (seo_body_title is intentionally left untouched)
 *
 * Matching is by slug (resolved from the live DB against CSV RO name + parent).
 * Only the 12 parent + 85 child rows that match exactly are touched. Rows are
 * UPDATED IN PLACE (no create / no delete) -> fully non-breaking. Existing
 * structure (parent_id, sort_order, color, icon, visibility) is preserved.
 *
 * Idempotent. Run:
 *   php artisan db:seed --class=BileteOnlineEventCategoriesSeoSeeder
 */
class BileteOnlineEventCategoriesSeoSeeder extends Seeder
{
    protected const MARKETPLACE_CLIENT_ID = 3;

    public function run(): void
    {
        $mcId = self::MARKETPLACE_CLIENT_ID;
        $this->command->info("Populating SEO/content for bilete.online event categories (marketplace_client_id={$mcId})");

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

            // Merge translations so we never wipe a locale the CSV doesn't provide.
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
    'slug' => 'escape-rooms',
    'id' => 17,
    'name' => array(
      'ro' => 'Escape rooms',
      'en' => 'Escape rooms',
    ),
    'description' => array(
      'ro' => 'Camere tematice în care rezolvi indicii, mistere și provocări contra cronometru.',
      'en' => 'Themed rooms where you solve clues, mysteries and timed challenges.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms | Bilete și rezervări online',
      'en' => 'Escape rooms | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă escape rooms pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover escape rooms on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă escape rooms tematice, camere cu enigme, povești interactive și provocări de echipă. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover themed escape rooms, puzzle rooms, interactive stories and team challenges. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  1 => array(
    'slug' => 'escape-rooms-clasice-escape-rooms',
    'id' => 18,
    'name' => array(
      'ro' => 'Escape rooms clasice',
      'en' => 'Classic escape rooms',
    ),
    'description' => array(
      'ro' => 'Experiențe de escape room cu enigme, indicii și camere tematice.',
      'en' => 'Classic escape room experiences with clues, puzzles and themed rooms.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms clasice | Bilete și rezervări online',
      'en' => 'Classic escape rooms | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă escape rooms clasice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book classic escape rooms on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de escape room cu enigme, indicii și camere tematice. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria escape rooms clasice și compară rapid activitățile disponibile.',
      'en' => 'Classic escape room experiences with clues, puzzles and themed rooms. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book classic escape rooms online and compare available experiences quickly.',
    ),
  ),
  2 => array(
    'slug' => 'escape-rooms-horror-escape-rooms',
    'id' => 19,
    'name' => array(
      'ro' => 'Escape rooms horror',
      'en' => 'Horror escape rooms',
    ),
    'description' => array(
      'ro' => 'Camere intense, cu atmosferă tensionată și povești înfricoșătoare.',
      'en' => 'Intense rooms with suspenseful atmosphere and scary storylines.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms horror | Bilete și rezervări online',
      'en' => 'Horror escape rooms | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă escape rooms horror pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book horror escape rooms on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Camere intense, cu atmosferă tensionată și povești înfricoșătoare. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria escape rooms horror și compară rapid activitățile disponibile.',
      'en' => 'Intense rooms with suspenseful atmosphere and scary storylines. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book horror escape rooms online and compare available experiences quickly.',
    ),
  ),
  3 => array(
    'slug' => 'escape-rooms-mystery-detective-escape-rooms',
    'id' => 20,
    'name' => array(
      'ro' => 'Escape rooms mystery / detective',
      'en' => 'Mystery / detective escape rooms',
    ),
    'description' => array(
      'ro' => 'Investigații interactive în care cauți indicii și rezolvi cazuri misterioase.',
      'en' => 'Interactive investigations where you search for clues and solve mysterious cases.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms mystery / detective | Bilete și rezervări online',
      'en' => 'Mystery / detective escape rooms | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă escape rooms mystery / detective pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book mystery / detective escape rooms on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Investigații interactive în care cauți indicii și rezolvi cazuri misterioase. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria escape rooms mystery / detective și compară rapid activitățile disponibile.',
      'en' => 'Interactive investigations where you search for clues and solve mysterious cases. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book mystery / detective escape rooms online and compare available experiences quickly.',
    ),
  ),
  4 => array(
    'slug' => 'escape-rooms-pentru-copii-escape-rooms',
    'id' => 23,
    'name' => array(
      'ro' => 'Escape rooms pentru copii',
      'en' => 'Escape rooms for children',
    ),
    'description' => array(
      'ro' => 'Camere adaptate copiilor, cu enigme accesibile și povești prietenoase.',
      'en' => 'Child-friendly rooms with accessible puzzles and gentle stories.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms pentru copii | Bilete și rezervări online',
      'en' => 'Escape rooms for children | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă escape rooms pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book escape rooms for children on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Camere adaptate copiilor, cu enigme accesibile și povești prietenoase. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria escape rooms pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Child-friendly rooms with accessible puzzles and gentle stories. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book escape rooms for children online and compare available experiences quickly.',
    ),
  ),
  5 => array(
    'slug' => 'escape-rooms-pentru-grupuri-escape-rooms',
    'id' => 25,
    'name' => array(
      'ro' => 'Escape rooms pentru grupuri',
      'en' => 'Escape rooms for groups',
    ),
    'description' => array(
      'ro' => 'Camere potrivite pentru prieteni, colegi sau grupuri mai mari.',
      'en' => 'Rooms designed for friends, colleagues or larger organized groups.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms pentru grupuri | Bilete și rezervări online',
      'en' => 'Escape rooms for groups | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă escape rooms pentru grupuri pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book escape rooms for groups on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Camere potrivite pentru prieteni, colegi sau grupuri mai mari. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria escape rooms pentru grupuri și compară rapid activitățile disponibile.',
      'en' => 'Rooms designed for friends, colleagues or larger organized groups. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book escape rooms for groups online and compare available experiences quickly.',
    ),
  ),
  6 => array(
    'slug' => 'escape-rooms-corporate-team-building-escape-rooms',
    'id' => 26,
    'name' => array(
      'ro' => 'Escape rooms corporate / team building',
      'en' => 'Corporate / team building escape rooms',
    ),
    'description' => array(
      'ro' => 'Activități de echipă care testează comunicarea, logica și colaborarea.',
      'en' => 'Team activities that test communication, logic and collaboration.',
    ),
    'meta_title' => array(
      'ro' => 'Escape rooms corporate / team building | Bilete online',
      'en' => 'Corporate / team building escape rooms | Online tickets',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă escape rooms corporate / team building pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau…',
      'en' => 'Book corporate / team building escape rooms on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Activități de echipă care testează comunicarea, logica și colaborarea. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria escape rooms corporate / team building și compară rapid activitățile disponibile.',
      'en' => 'Team activities that test communication, logic and collaboration. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book corporate / team building escape rooms online and compare available experiences quickly.',
    ),
  ),
  7 => array(
    'slug' => 'muzee-expozitii',
    'id' => 27,
    'name' => array(
      'ro' => 'Muzee & expoziții',
      'en' => 'Museums & exhibitions',
    ),
    'description' => array(
      'ro' => 'Muzee, galerii și expoziții pentru vizite culturale, educative sau interactive.',
      'en' => 'Museums, galleries and exhibitions for cultural, educational or interactive visits.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee & expoziții | Bilete și rezervări online',
      'en' => 'Museums & exhibitions | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă muzee & expoziții pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover museums & exhibitions on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă muzee, galerii, expoziții, planetarii și experiențe interactive. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover museums, galleries, exhibitions, planetariums and interactive experiences. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  8 => array(
    'slug' => 'muzee-de-arta-muzee-expozitii',
    'id' => 28,
    'name' => array(
      'ro' => 'Muzee de artă',
      'en' => 'Art museums',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee de artă | Bilete și rezervări online',
      'en' => 'Art museums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă muzee de artă pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book art museums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria muzee de artă și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book art museums online and compare available experiences quickly.',
    ),
  ),
  9 => array(
    'slug' => 'muzee-de-istorie-muzee-expozitii',
    'id' => 29,
    'name' => array(
      'ro' => 'Muzee de istorie',
      'en' => 'History museums',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee de istorie | Bilete și rezervări online',
      'en' => 'History museums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă muzee de istorie pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book history museums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria muzee de istorie și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book history museums online and compare available experiences quickly.',
    ),
  ),
  10 => array(
    'slug' => 'muzee-de-stiinta-muzee-expozitii',
    'id' => 30,
    'name' => array(
      'ro' => 'Muzee de știință',
      'en' => 'Science museums',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee de știință | Bilete și rezervări online',
      'en' => 'Science museums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă muzee de știință pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book science museums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria muzee de știință și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book science museums online and compare available experiences quickly.',
    ),
  ),
  11 => array(
    'slug' => 'muzee-interactive-muzee-expozitii',
    'id' => 31,
    'name' => array(
      'ro' => 'Muzee interactive',
      'en' => 'Interactive museums',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee interactive | Bilete și rezervări online',
      'en' => 'Interactive museums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă muzee interactive pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book interactive museums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria muzee interactive și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book interactive museums online and compare available experiences quickly.',
    ),
  ),
  12 => array(
    'slug' => 'muzee-pentru-copii-muzee-expozitii',
    'id' => 32,
    'name' => array(
      'ro' => 'Muzee pentru copii',
      'en' => 'Children’s museums',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Muzee pentru copii | Bilete și rezervări online',
      'en' => 'Children’s museums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă muzee pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book children’s museums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria muzee pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book children’s museums online and compare available experiences quickly.',
    ),
  ),
  13 => array(
    'slug' => 'expozitii-temporare-muzee-expozitii',
    'id' => 33,
    'name' => array(
      'ro' => 'Expoziții temporare',
      'en' => 'Temporary exhibitions',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Expoziții temporare | Bilete și rezervări online',
      'en' => 'Temporary exhibitions | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă expoziții temporare pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book temporary exhibitions on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria expoziții temporare și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book temporary exhibitions online and compare available experiences quickly.',
    ),
  ),
  14 => array(
    'slug' => 'galerii-de-arta-muzee-expozitii',
    'id' => 34,
    'name' => array(
      'ro' => 'Galerii de artă',
      'en' => 'Art galleries',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Galerii de artă | Bilete și rezervări online',
      'en' => 'Art galleries | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă galerii de artă pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book art galleries on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria galerii de artă și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book art galleries online and compare available experiences quickly.',
    ),
  ),
  15 => array(
    'slug' => 'planetarii-muzee-expozitii',
    'id' => 35,
    'name' => array(
      'ro' => 'Planetarii',
      'en' => 'Planetariums',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Planetarii | Bilete și rezervări online',
      'en' => 'Planetariums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă planetarii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book planetariums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria planetarii și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book planetariums online and compare available experiences quickly.',
    ),
  ),
  16 => array(
    'slug' => 'centre-de-stiinta-muzee-expozitii',
    'id' => 36,
    'name' => array(
      'ro' => 'Centre de știință',
      'en' => 'Science centers',
    ),
    'description' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time.',
    ),
    'meta_title' => array(
      'ro' => 'Centre de știință | Bilete și rezervări online',
      'en' => 'Science centers | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă centre de știință pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book science centers on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Vizite culturale și educative pentru curiozitate, descoperire și timp de calitate. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria centre de știință și compară rapid activitățile disponibile.',
      'en' => 'Cultural and educational visits for curiosity, discovery and quality time. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book science centers online and compare available experiences quickly.',
    ),
  ),
  17 => array(
    'slug' => 'experiente-imersive-interactive-muzee-expozitii',
    'id' => 37,
    'name' => array(
      'ro' => 'Experiențe imersive / interactive',
      'en' => 'Immersive / interactive experiences',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Muzee & expoziții, ușor de rezervat online.',
      'en' => 'Relevant experiences in Museums & exhibitions, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe imersive / interactive | Bilete și rezervări online',
      'en' => 'Immersive / interactive experiences | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă experiențe imersive / interactive pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book immersive / interactive experiences on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Muzee & expoziții, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria experiențe imersive / interactive și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Museums & exhibitions, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book immersive / interactive experiences online and compare available experiences quickly.',
    ),
  ),
  18 => array(
    'slug' => 'parcuri-de-distractii',
    'id' => 38,
    'name' => array(
      'ro' => 'Parcuri de distracții',
      'en' => 'Amusement parks',
    ),
    'description' => array(
      'ro' => 'Atracții, carusele și experiențe de divertisment pentru copii și adulți.',
      'en' => 'Rides, carousels and entertainment experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri de distracții | Bilete și rezervări online',
      'en' => 'Amusement parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă parcuri de distracții pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover amusement parks on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă parcuri de distracții, parcuri tematice, carusele și centre de entertainment. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover amusement parks, theme parks, carousels and entertainment centers. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  19 => array(
    'slug' => 'parcuri-de-distractii-clasice-parcuri-de-distractii',
    'id' => 39,
    'name' => array(
      'ro' => 'Parcuri de distracții clasice',
      'en' => 'Classic amusement parks',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri de distracții clasice | Bilete și rezervări online',
      'en' => 'Classic amusement parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri de distracții clasice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book classic amusement parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri de distracții clasice și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book classic amusement parks online and compare available experiences quickly.',
    ),
  ),
  20 => array(
    'slug' => 'parcuri-tematice-parcuri-de-distractii',
    'id' => 40,
    'name' => array(
      'ro' => 'Parcuri tematice',
      'en' => 'Theme parks',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri tematice | Bilete și rezervări online',
      'en' => 'Theme parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri tematice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book theme parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri tematice și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book theme parks online and compare available experiences quickly.',
    ),
  ),
  21 => array(
    'slug' => 'zone-de-joaca-parcuri-de-distractii',
    'id' => 41,
    'name' => array(
      'ro' => 'Zone de joacă',
      'en' => 'Play areas',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Zone de joacă | Bilete și rezervări online',
      'en' => 'Play areas | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă zone de joacă pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book play areas on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria zone de joacă și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book play areas online and compare available experiences quickly.',
    ),
  ),
  22 => array(
    'slug' => 'parcuri-pentru-copii-parcuri-de-distractii',
    'id' => 42,
    'name' => array(
      'ro' => 'Parcuri pentru copii',
      'en' => 'Children’s parks',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri pentru copii | Bilete și rezervări online',
      'en' => 'Children’s parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book children’s parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book children’s parks online and compare available experiences quickly.',
    ),
  ),
  23 => array(
    'slug' => 'parcuri-indoor-parcuri-de-distractii',
    'id' => 43,
    'name' => array(
      'ro' => 'Parcuri indoor',
      'en' => 'Indoor parks',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri indoor | Bilete și rezervări online',
      'en' => 'Indoor parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri indoor pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book indoor parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri indoor și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book indoor parks online and compare available experiences quickly.',
    ),
  ),
  24 => array(
    'slug' => 'parcuri-sezoniere-parcuri-de-distractii',
    'id' => 44,
    'name' => array(
      'ro' => 'Parcuri sezoniere',
      'en' => 'Seasonal parks',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri sezoniere | Bilete și rezervări online',
      'en' => 'Seasonal parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri sezoniere pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book seasonal parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri sezoniere și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book seasonal parks online and compare available experiences quickly.',
    ),
  ),
  25 => array(
    'slug' => 'carusele-atractii-mecanice-parcuri-de-distractii',
    'id' => 45,
    'name' => array(
      'ro' => 'Carusele / atracții mecanice',
      'en' => 'Carousels / mechanical rides',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Carusele / atracții mecanice | Bilete și rezervări online',
      'en' => 'Carousels / mechanical rides | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă carusele / atracții mecanice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book carousels / mechanical rides on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria carusele / atracții mecanice și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book carousels / mechanical rides online and compare available experiences quickly.',
    ),
  ),
  26 => array(
    'slug' => 'experiente-family-entertainment-center-parcuri-de-distractii',
    'id' => 46,
    'name' => array(
      'ro' => 'Experiențe family entertainment center',
      'en' => 'Family entertainment center experiences',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de distracții, ușor de rezervat online.',
      'en' => 'Relevant experiences in Amusement parks, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe family entertainment center | Bilete online',
      'en' => 'Family entertainment center experiences | Online tickets',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă experiențe family entertainment center pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau…',
      'en' => 'Book family entertainment center experiences on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de distracții, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria experiențe family entertainment center și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Amusement parks, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book family entertainment center experiences online and compare available experiences quickly.',
    ),
  ),
  27 => array(
    'slug' => 'parcuri-de-aventura',
    'id' => 47,
    'name' => array(
      'ro' => 'Parcuri de aventură',
      'en' => 'Adventure parks',
    ),
    'description' => array(
      'ro' => 'Trasee, tiroliene și provocări outdoor pentru copii, adolescenți și adulți.',
      'en' => 'Treetop courses, zip lines and outdoor challenges for children, teens and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri de aventură | Bilete și rezervări online',
      'en' => 'Adventure parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă parcuri de aventură pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover adventure parks on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă trasee în copaci, tiroliene, escaladă și activități outdoor. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover treetop courses, zip lines, climbing and outdoor activities. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  28 => array(
    'slug' => 'trasee-in-copaci-parcuri-de-aventura',
    'id' => 48,
    'name' => array(
      'ro' => 'Trasee în copaci',
      'en' => 'Treetop courses',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de aventură, ușor de rezervat online.',
      'en' => 'Relevant experiences in Adventure parks, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Trasee în copaci | Bilete și rezervări online',
      'en' => 'Treetop courses | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă trasee în copaci pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book treetop courses on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de aventură, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria trasee în copaci și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Adventure parks, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book treetop courses online and compare available experiences quickly.',
    ),
  ),
  29 => array(
    'slug' => 'tiroliene-parcuri-de-aventura',
    'id' => 49,
    'name' => array(
      'ro' => 'Tiroliene',
      'en' => 'Zip lines',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de aventură, ușor de rezervat online.',
      'en' => 'Relevant experiences in Adventure parks, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Tiroliene | Bilete și rezervări online',
      'en' => 'Zip lines | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tiroliene pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book zip lines on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de aventură, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tiroliene și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Adventure parks, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book zip lines online and compare available experiences quickly.',
    ),
  ),
  30 => array(
    'slug' => 'panouri-de-escalada-parcuri-de-aventura',
    'id' => 50,
    'name' => array(
      'ro' => 'Panouri de escaladă',
      'en' => 'Climbing walls',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de aventură, ușor de rezervat online.',
      'en' => 'Relevant experiences in Adventure parks, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Panouri de escaladă | Bilete și rezervări online',
      'en' => 'Climbing walls | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă panouri de escaladă pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book climbing walls on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Parcuri de aventură, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria panouri de escaladă și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Adventure parks, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book climbing walls online and compare available experiences quickly.',
    ),
  ),
  31 => array(
    'slug' => 'aventura-pentru-copii-parcuri-de-aventura',
    'id' => 51,
    'name' => array(
      'ro' => 'Aventură pentru copii',
      'en' => 'Adventure for children',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Aventură pentru copii | Bilete și rezervări online',
      'en' => 'Adventure for children | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă aventură pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book adventure for children on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria aventură pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book adventure for children online and compare available experiences quickly.',
    ),
  ),
  32 => array(
    'slug' => 'aventura-pentru-adolescenti-parcuri-de-aventura',
    'id' => 52,
    'name' => array(
      'ro' => 'Aventură pentru adolescenți',
      'en' => 'Adventure for teenagers',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Aventură pentru adolescenți | Bilete și rezervări online',
      'en' => 'Adventure for teenagers | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă aventură pentru adolescenți pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book adventure for teenagers on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria aventură pentru adolescenți și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book adventure for teenagers online and compare available experiences quickly.',
    ),
  ),
  33 => array(
    'slug' => 'aventura-pentru-adulti-parcuri-de-aventura',
    'id' => 53,
    'name' => array(
      'ro' => 'Aventură pentru adulți',
      'en' => 'Adventure for adults',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Aventură pentru adulți | Bilete și rezervări online',
      'en' => 'Adventure for adults | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă aventură pentru adulți pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book adventure for adults on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria aventură pentru adulți și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book adventure for adults online and compare available experiences quickly.',
    ),
  ),
  34 => array(
    'slug' => 'pachete-de-grup-parcuri-de-aventura',
    'id' => 54,
    'name' => array(
      'ro' => 'Pachete de grup',
      'en' => 'Group packages',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Pachete de grup | Bilete și rezervări online',
      'en' => 'Group packages | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă pachete de grup pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book group packages on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria pachete de grup și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book group packages online and compare available experiences quickly.',
    ),
  ),
  35 => array(
    'slug' => 'team-building-outdoor-parcuri-de-aventura',
    'id' => 55,
    'name' => array(
      'ro' => 'Team building outdoor',
      'en' => 'Outdoor team building',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Team building outdoor | Bilete și rezervări online',
      'en' => 'Outdoor team building | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă team building outdoor pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book outdoor team building on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria team building outdoor și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book outdoor team building online and compare available experiences quickly.',
    ),
  ),
  36 => array(
    'slug' => 'natura-outdoor',
    'id' => 56,
    'name' => array(
      'ro' => 'Natură & outdoor',
      'en' => 'Nature & outdoor',
    ),
    'description' => array(
      'ro' => 'Experiențe în natură, trasee, tururi ghidate și activități în aer liber.',
      'en' => 'Nature experiences, trails, guided tours and outdoor activities.',
    ),
    'meta_title' => array(
      'ro' => 'Natură & outdoor | Bilete și rezervări online',
      'en' => 'Nature & outdoor | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă natură & outdoor pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover nature & outdoor on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă rezervații naturale, trasee, peșteri, canioane și experiențe eco. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover nature reserves, trails, caves, canyons and eco experiences. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  37 => array(
    'slug' => 'rezervatii-naturale-natura-outdoor',
    'id' => 57,
    'name' => array(
      'ro' => 'Rezervații naturale',
      'en' => 'Nature reserves',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Rezervații naturale | Bilete și rezervări online',
      'en' => 'Nature reserves | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă rezervații naturale pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book nature reserves on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria rezervații naturale și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book nature reserves online and compare available experiences quickly.',
    ),
  ),
  38 => array(
    'slug' => 'tururi-ghidate-in-natura-natura-outdoor',
    'id' => 58,
    'name' => array(
      'ro' => 'Tururi ghidate în natură',
      'en' => 'Guided nature tours',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi ghidate în natură | Bilete și rezervări online',
      'en' => 'Guided nature tours | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi ghidate în natură pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book guided nature tours on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi ghidate în natură și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book guided nature tours online and compare available experiences quickly.',
    ),
  ),
  39 => array(
    'slug' => 'pesteri-natura-outdoor',
    'id' => 59,
    'name' => array(
      'ro' => 'Peșteri',
      'en' => 'Caves',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Peșteri | Bilete și rezervări online',
      'en' => 'Caves | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă peșteri pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book caves on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria peșteri și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book caves online and compare available experiences quickly.',
    ),
  ),
  40 => array(
    'slug' => 'chei-canioane-trasee-spectaculoase-natura-outdoor',
    'id' => 60,
    'name' => array(
      'ro' => 'Chei / canioane / trasee spectaculoase',
      'en' => 'Gorges / canyons / scenic trails',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Chei / canioane / trasee spectaculoase | Bilete online',
      'en' => 'Gorges / canyons / scenic trails | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă chei / canioane / trasee spectaculoase pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau…',
      'en' => 'Book gorges / canyons / scenic trails on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria chei / canioane / trasee spectaculoase și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book gorges / canyons / scenic trails online and compare available experiences quickly.',
    ),
  ),
  41 => array(
    'slug' => 'observatoare-animale-natura-outdoor',
    'id' => 61,
    'name' => array(
      'ro' => 'Observatoare animale',
      'en' => 'Wildlife observation points',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Natură & outdoor, ușor de rezervat online.',
      'en' => 'Relevant experiences in Nature & outdoor, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Observatoare animale | Bilete și rezervări online',
      'en' => 'Wildlife observation points | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă observatoare animale pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book wildlife observation points on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Natură & outdoor, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria observatoare animale și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Nature & outdoor, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book wildlife observation points online and compare available experiences quickly.',
    ),
  ),
  42 => array(
    'slug' => 'gradini-botanice-natura-outdoor',
    'id' => 62,
    'name' => array(
      'ro' => 'Grădini botanice',
      'en' => 'Botanical gardens',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Grădini botanice | Bilete și rezervări online',
      'en' => 'Botanical gardens | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă grădini botanice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book botanical gardens on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria grădini botanice și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book botanical gardens online and compare available experiences quickly.',
    ),
  ),
  43 => array(
    'slug' => 'parcuri-naturale-natura-outdoor',
    'id' => 63,
    'name' => array(
      'ro' => 'Parcuri naturale',
      'en' => 'Natural parks',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Entertainment, play and fun experiences for children and adults.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri naturale | Bilete și rezervări online',
      'en' => 'Natural parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri naturale pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book natural parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri naturale și compară rapid activitățile disponibile.',
      'en' => 'Entertainment, play and fun experiences for children and adults. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book natural parks online and compare available experiences quickly.',
    ),
  ),
  44 => array(
    'slug' => 'activitati-montane-usoare-natura-outdoor',
    'id' => 64,
    'name' => array(
      'ro' => 'Activități montane ușoare',
      'en' => 'Easy mountain activities',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Activități montane ușoare | Bilete și rezervări online',
      'en' => 'Easy mountain activities | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități montane ușoare pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book easy mountain activities on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități montane ușoare și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book easy mountain activities online and compare available experiences quickly.',
    ),
  ),
  45 => array(
    'slug' => 'experiente-eco-educatie-de-mediu-natura-outdoor',
    'id' => 65,
    'name' => array(
      'ro' => 'Experiențe eco / educație de mediu',
      'en' => 'Eco experiences / environmental education',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe eco / educație de mediu | Bilete și rezervări online',
      'en' => 'Eco experiences / environmental education | Online tickets',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă experiențe eco / educație de mediu pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book eco experiences / environmental education on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria experiențe eco / educație de mediu și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book eco experiences / environmental education online and compare available experiences quickly.',
    ),
  ),
  46 => array(
    'slug' => 'acvarii-zoo-animale',
    'id' => 66,
    'name' => array(
      'ro' => 'Acvarii, zoo & animale',
      'en' => 'Aquariums, zoos & animals',
    ),
    'description' => array(
      'ro' => 'Experiențe cu animale, acvarii, zoo, ferme educative și observatoare.',
      'en' => 'Animal experiences, aquariums, zoos, educational farms and observatories.',
    ),
    'meta_title' => array(
      'ro' => 'Acvarii, zoo & animale | Bilete și rezervări online',
      'en' => 'Aquariums, zoos & animals | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă acvarii, zoo & animale pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover aquariums, zoos & animals on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă acvarii, zoo, ferme educative, sanctuare și experiențe cu animale. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover aquariums, zoos, educational farms, sanctuaries and animal experiences. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  47 => array(
    'slug' => 'acvarii-acvarii-zoo-animale',
    'id' => 67,
    'name' => array(
      'ro' => 'Acvarii',
      'en' => 'Aquariums',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Acvarii | Bilete și rezervări online',
      'en' => 'Aquariums | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă acvarii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book aquariums on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria acvarii și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book aquariums online and compare available experiences quickly.',
    ),
  ),
  48 => array(
    'slug' => 'gradini-zoologice-acvarii-zoo-animale',
    'id' => 68,
    'name' => array(
      'ro' => 'Grădini zoologice',
      'en' => 'Zoos',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Grădini zoologice | Bilete și rezervări online',
      'en' => 'Zoos | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă grădini zoologice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book zoos on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria grădini zoologice și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book zoos online and compare available experiences quickly.',
    ),
  ),
  49 => array(
    'slug' => 'ferme-educative-acvarii-zoo-animale',
    'id' => 69,
    'name' => array(
      'ro' => 'Ferme educative',
      'en' => 'Educational farms',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Ferme educative | Bilete și rezervări online',
      'en' => 'Educational farms | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ferme educative pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book educational farms on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ferme educative și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book educational farms online and compare available experiences quickly.',
    ),
  ),
  50 => array(
    'slug' => 'sanctuare-rezervatii-de-animale-acvarii-zoo-animale',
    'id' => 70,
    'name' => array(
      'ro' => 'Sanctuare / rezervații de animale',
      'en' => 'Animal sanctuaries / reserves',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Sanctuare / rezervații de animale | Bilete și rezervări online',
      'en' => 'Animal sanctuaries / reserves | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă sanctuare / rezervații de animale pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book animal sanctuaries / reserves on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria sanctuare / rezervații de animale și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book animal sanctuaries / reserves online and compare available experiences quickly.',
    ),
  ),
  51 => array(
    'slug' => 'observatoare-fauna-acvarii-zoo-animale',
    'id' => 71,
    'name' => array(
      'ro' => 'Observatoare faună',
      'en' => 'Wildlife observatories',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Observatoare faună | Bilete și rezervări online',
      'en' => 'Wildlife observatories | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă observatoare faună pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book wildlife observatories on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Acvarii, zoo & animale, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria observatoare faună și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Aquariums, zoos & animals, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book wildlife observatories online and compare available experiences quickly.',
    ),
  ),
  52 => array(
    'slug' => 'experiente-cu-animale-pentru-copii-acvarii-zoo-animale',
    'id' => 72,
    'name' => array(
      'ro' => 'Experiențe cu animale pentru copii',
      'en' => 'Animal experiences for children',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe cu animale pentru copii | Bilete și rezervări online',
      'en' => 'Animal experiences for children | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă experiențe cu animale pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book animal experiences for children on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria experiențe cu animale pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book animal experiences for children online and compare available experiences quickly.',
    ),
  ),
  53 => array(
    'slug' => 'ateliere-experiente-creative',
    'id' => 73,
    'name' => array(
      'ro' => 'Ateliere & experiențe creative',
      'en' => 'Workshops & creative experiences',
    ),
    'description' => array(
      'ro' => 'Ateliere practice de artă, craft, știință, ceramică și creație.',
      'en' => 'Hands-on art, craft, science, ceramics and creative workshops.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere & experiențe creative | Bilete și rezervări online',
      'en' => 'Workshops & creative experiences | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă ateliere & experiențe creative pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover workshops & creative experiences on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă ateliere de artă, pictură, ceramică, craft, știință și creație. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover art, painting, ceramics, craft, science and creative workshops. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  54 => array(
    'slug' => 'ateliere-pentru-copii-ateliere-experiente-creative',
    'id' => 74,
    'name' => array(
      'ro' => 'Ateliere pentru copii',
      'en' => 'Workshops for children',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere pentru copii | Bilete și rezervări online',
      'en' => 'Workshops for children | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book workshops for children on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book workshops for children online and compare available experiences quickly.',
    ),
  ),
  55 => array(
    'slug' => 'ateliere-pentru-adulti-ateliere-experiente-creative',
    'id' => 75,
    'name' => array(
      'ro' => 'Ateliere pentru adulți',
      'en' => 'Workshops for adults',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere pentru adulți | Bilete și rezervări online',
      'en' => 'Workshops for adults | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere pentru adulți pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book workshops for adults on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere pentru adulți și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book workshops for adults online and compare available experiences quickly.',
    ),
  ),
  56 => array(
    'slug' => 'ateliere-de-pictura-ateliere-experiente-creative',
    'id' => 76,
    'name' => array(
      'ro' => 'Ateliere de pictură',
      'en' => 'Painting workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere de pictură | Bilete și rezervări online',
      'en' => 'Painting workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere de pictură pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book painting workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere de pictură și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book painting workshops online and compare available experiences quickly.',
    ),
  ),
  57 => array(
    'slug' => 'ateliere-de-ceramica-ateliere-experiente-creative',
    'id' => 77,
    'name' => array(
      'ro' => 'Ateliere de ceramică',
      'en' => 'Ceramics workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere de ceramică | Bilete și rezervări online',
      'en' => 'Ceramics workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere de ceramică pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book ceramics workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere de ceramică și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book ceramics workshops online and compare available experiences quickly.',
    ),
  ),
  58 => array(
    'slug' => 'ateliere-diy-craft-ateliere-experiente-creative',
    'id' => 78,
    'name' => array(
      'ro' => 'Ateliere DIY / craft',
      'en' => 'DIY / craft workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere DIY / craft | Bilete și rezervări online',
      'en' => 'DIY / craft workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere diy / craft pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book diy / craft workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere diy / craft și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book diy / craft workshops online and compare available experiences quickly.',
    ),
  ),
  59 => array(
    'slug' => 'ateliere-educative-ateliere-experiente-creative',
    'id' => 79,
    'name' => array(
      'ro' => 'Ateliere educative',
      'en' => 'Educational workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere educative | Bilete și rezervări online',
      'en' => 'Educational workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere educative pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book educational workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere educative și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book educational workshops online and compare available experiences quickly.',
    ),
  ),
  60 => array(
    'slug' => 'ateliere-de-stiinta-ateliere-experiente-creative',
    'id' => 80,
    'name' => array(
      'ro' => 'Ateliere de știință',
      'en' => 'Science workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere de știință | Bilete și rezervări online',
      'en' => 'Science workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere de știință pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book science workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere de știință și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book science workshops online and compare available experiences quickly.',
    ),
  ),
  61 => array(
    'slug' => 'ateliere-tematice-sezoniere-ateliere-experiente-creative',
    'id' => 81,
    'name' => array(
      'ro' => 'Ateliere tematice sezoniere',
      'en' => 'Seasonal themed workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere tematice sezoniere | Bilete și rezervări online',
      'en' => 'Seasonal themed workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere tematice sezoniere pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book seasonal themed workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere tematice sezoniere și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book seasonal themed workshops online and compare available experiences quickly.',
    ),
  ),
  62 => array(
    'slug' => 'experiente-creative-pentru-grupuri-ateliere-experiente-creative',
    'id' => 82,
    'name' => array(
      'ro' => 'Experiențe creative pentru grupuri',
      'en' => 'Creative experiences for groups',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe creative pentru grupuri | Bilete și rezervări online',
      'en' => 'Creative experiences for groups | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă experiențe creative pentru grupuri pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book creative experiences for groups on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria experiențe creative pentru grupuri și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book creative experiences for groups online and compare available experiences quickly.',
    ),
  ),
  63 => array(
    'slug' => 'tururi-experiente-turistice',
    'id' => 83,
    'name' => array(
      'ro' => 'Tururi & experiențe turistice',
      'en' => 'Tours & tourist experiences',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate, city walks și experiențe locale pentru turiști și grupuri.',
      'en' => 'Guided tours, city walks and local experiences for tourists and groups.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi & experiențe turistice | Bilete și rezervări online',
      'en' => 'Tours & tourist experiences | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă tururi & experiențe turistice pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover tours & tourist experiences on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă tururi urbane, istorice, culturale, gastronomice și city walks. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover urban, historical, cultural and food tours, plus city walks. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  64 => array(
    'slug' => 'tururi-ghidate-urbane-tururi-experiente-turistice',
    'id' => 84,
    'name' => array(
      'ro' => 'Tururi ghidate urbane',
      'en' => 'Urban guided tours',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi ghidate urbane | Bilete și rezervări online',
      'en' => 'Urban guided tours | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi ghidate urbane pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book urban guided tours on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi ghidate urbane și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book urban guided tours online and compare available experiences quickly.',
    ),
  ),
  65 => array(
    'slug' => 'tururi-istorice-tururi-experiente-turistice',
    'id' => 85,
    'name' => array(
      'ro' => 'Tururi istorice',
      'en' => 'Historical tours',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi istorice | Bilete și rezervări online',
      'en' => 'Historical tours | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi istorice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book historical tours on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi istorice și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book historical tours online and compare available experiences quickly.',
    ),
  ),
  66 => array(
    'slug' => 'tururi-culturale-tururi-experiente-turistice',
    'id' => 86,
    'name' => array(
      'ro' => 'Tururi culturale',
      'en' => 'Cultural tours',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi culturale | Bilete și rezervări online',
      'en' => 'Cultural tours | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi culturale pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book cultural tours on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi culturale și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book cultural tours online and compare available experiences quickly.',
    ),
  ),
  67 => array(
    'slug' => 'tururi-in-natura-tururi-experiente-turistice',
    'id' => 87,
    'name' => array(
      'ro' => 'Tururi în natură',
      'en' => 'Nature tours',
    ),
    'description' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi în natură | Bilete și rezervări online',
      'en' => 'Nature tours | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi în natură pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book nature tours on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe în aer liber, cu peisaje, explorare și conexiune cu natura. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi în natură și compară rapid activitățile disponibile.',
      'en' => 'Outdoor experiences with landscapes, exploration and connection to nature. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book nature tours online and compare available experiences quickly.',
    ),
  ),
  68 => array(
    'slug' => 'tururi-gastronomice-tururi-experiente-turistice',
    'id' => 88,
    'name' => array(
      'ro' => 'Tururi gastronomice',
      'en' => 'Food tours',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi gastronomice | Bilete și rezervări online',
      'en' => 'Food tours | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi gastronomice pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book food tours on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi gastronomice și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book food tours online and compare available experiences quickly.',
    ),
  ),
  69 => array(
    'slug' => 'tururi-pentru-turisti-straini-tururi-experiente-turistice',
    'id' => 89,
    'name' => array(
      'ro' => 'Tururi pentru turiști străini',
      'en' => 'Tours for international visitors',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi pentru turiști străini | Bilete și rezervări online',
      'en' => 'Tours for international visitors | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi pentru turiști străini pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book tours for international visitors on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi pentru turiști străini și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book tours for international visitors online and compare available experiences quickly.',
    ),
  ),
  70 => array(
    'slug' => 'city-walks-tururi-experiente-turistice',
    'id' => 90,
    'name' => array(
      'ro' => 'City walks',
      'en' => 'City walks',
    ),
    'description' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale.',
      'en' => 'Guided tours for discovering places, stories and local experiences.',
    ),
    'meta_title' => array(
      'ro' => 'City walks | Bilete și rezervări online',
      'en' => 'City walks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă city walks pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book city walks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Tururi ghidate pentru a descoperi locuri, povești și experiențe locale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria city walks și compară rapid activitățile disponibile.',
      'en' => 'Guided tours for discovering places, stories and local experiences. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book city walks online and compare available experiences quickly.',
    ),
  ),
  71 => array(
    'slug' => 'tururi-pentru-scoli-grupuri-tururi-experiente-turistice',
    'id' => 91,
    'name' => array(
      'ro' => 'Tururi pentru școli / grupuri',
      'en' => 'Tours for schools / groups',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Tururi pentru școli / grupuri | Bilete și rezervări online',
      'en' => 'Tours for schools / groups | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă tururi pentru școli / grupuri pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book tours for schools / groups on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria tururi pentru școli / grupuri și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book tours for schools / groups online and compare available experiences quickly.',
    ),
  ),
  72 => array(
    'slug' => 'educatie-invatare-experientiala',
    'id' => 92,
    'name' => array(
      'ro' => 'Educație & învățare experiențială',
      'en' => 'Education & experiential learning',
    ),
    'description' => array(
      'ro' => 'Activități educative, STEM, lecții interactive și experiențe pentru clase.',
      'en' => 'Educational activities, STEM, interactive lessons and experiences for classes.',
    ),
    'meta_title' => array(
      'ro' => 'Educație & învățare experiențială | Bilete și rezervări online',
      'en' => 'Education & experiential learning | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă educație & învățare experiențială pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover education & experiential learning on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and…',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă activități educative, STEM, lecții interactive și vizite ghidate. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover educational activities, STEM, interactive lessons and guided visits. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  73 => array(
    'slug' => 'activitati-educative-educatie-invatare-experientiala',
    'id' => 93,
    'name' => array(
      'ro' => 'Activități educative',
      'en' => 'Educational activities',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Activități educative | Bilete și rezervări online',
      'en' => 'Educational activities | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități educative pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book educational activities on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități educative și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book educational activities online and compare available experiences quickly.',
    ),
  ),
  74 => array(
    'slug' => 'activitati-stem-educatie-invatare-experientiala',
    'id' => 94,
    'name' => array(
      'ro' => 'Activități STEM',
      'en' => 'STEM activities',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Activități STEM | Bilete și rezervări online',
      'en' => 'STEM activities | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități stem pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book stem activities on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități stem și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book stem activities online and compare available experiences quickly.',
    ),
  ),
  75 => array(
    'slug' => 'activitati-pentru-scoli-educatie-invatare-experientiala',
    'id' => 95,
    'name' => array(
      'ro' => 'Activități pentru școli',
      'en' => 'Activities for schools',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru școli | Bilete și rezervări online',
      'en' => 'Activities for schools | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități pentru școli pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book activities for schools on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități pentru școli și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book activities for schools online and compare available experiences quickly.',
    ),
  ),
  76 => array(
    'slug' => 'activitati-pentru-gradinite-educatie-invatare-experientiala',
    'id' => 96,
    'name' => array(
      'ro' => 'Activități pentru grădinițe',
      'en' => 'Activities for kindergartens',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru grădinițe | Bilete și rezervări online',
      'en' => 'Activities for kindergartens | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități pentru grădinițe pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book activities for kindergartens on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități pentru grădinițe și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book activities for kindergartens online and compare available experiences quickly.',
    ),
  ),
  77 => array(
    'slug' => 'excursii-educative-educatie-invatare-experientiala',
    'id' => 97,
    'name' => array(
      'ro' => 'Excursii educative',
      'en' => 'Educational trips',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Excursii educative | Bilete și rezervări online',
      'en' => 'Educational trips | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă excursii educative pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book educational trips on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria excursii educative și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book educational trips online and compare available experiences quickly.',
    ),
  ),
  78 => array(
    'slug' => 'lectii-interactive-educatie-invatare-experientiala',
    'id' => 98,
    'name' => array(
      'ro' => 'Lecții interactive',
      'en' => 'Interactive lessons',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Lecții interactive | Bilete și rezervări online',
      'en' => 'Interactive lessons | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă lecții interactive pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book interactive lessons on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria lecții interactive și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book interactive lessons online and compare available experiences quickly.',
    ),
  ),
  79 => array(
    'slug' => 'vizite-ghidate-educative-educatie-invatare-experientiala',
    'id' => 99,
    'name' => array(
      'ro' => 'Vizite ghidate educative',
      'en' => 'Educational guided visits',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Vizite ghidate educative | Bilete și rezervări online',
      'en' => 'Educational guided visits | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă vizite ghidate educative pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book educational guided visits on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Educație & învățare experiențială, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria vizite ghidate educative și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Education & experiential learning, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book educational guided visits online and compare available experiences quickly.',
    ),
  ),
  80 => array(
    'slug' => 'ateliere-de-literatie-stiinta-arta-educatie-invatare-experientiala',
    'id' => 100,
    'name' => array(
      'ro' => 'Ateliere de literație / știință / artă',
      'en' => 'Literacy / science / art workshops',
    ),
    'description' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing.',
    ),
    'meta_title' => array(
      'ro' => 'Ateliere de literație / știință / artă | Bilete online',
      'en' => 'Literacy / science / art workshops | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă ateliere de literație / știință / artă pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau…',
      'en' => 'Book literacy / science / art workshops on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Ateliere practice în care creezi, experimentezi și înveți prin activitate directă. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria ateliere de literație / știință / artă și compară rapid activitățile disponibile.',
      'en' => 'Hands-on workshops where you create, experiment and learn by doing. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book literacy / science / art workshops online and compare available experiences quickly.',
    ),
  ),
  81 => array(
    'slug' => 'experiente-pentru-clase-educatie-invatare-experientiala',
    'id' => 101,
    'name' => array(
      'ro' => 'Experiențe pentru clase',
      'en' => 'Experiences for classes',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Experiențe pentru clase | Bilete și rezervări online',
      'en' => 'Experiences for classes | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă experiențe pentru clase pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book experiences for classes on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria experiențe pentru clase și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book experiences for classes online and compare available experiences quickly.',
    ),
  ),
  82 => array(
    'slug' => 'familie-copii',
    'id' => 102,
    'name' => array(
      'ro' => 'Familie & copii',
      'en' => 'Family & children',
    ),
    'description' => array(
      'ro' => 'Activități potrivite pentru copii, părinți și timp petrecut împreună.',
      'en' => 'Activities for children, parents and meaningful family time.',
    ),
    'meta_title' => array(
      'ro' => 'Familie & copii | Bilete și rezervări online',
      'en' => 'Family & children | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă familie & copii pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover family & children on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă activități pentru copii, familie, joacă, spectacole și ateliere. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover children’s activities, family experiences, play, shows and workshops. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  83 => array(
    'slug' => 'activitati-pentru-copii-familie-copii',
    'id' => 103,
    'name' => array(
      'ro' => 'Activități pentru copii',
      'en' => 'Activities for children',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru copii | Bilete și rezervări online',
      'en' => 'Activities for children | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book activities for children on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book activities for children online and compare available experiences quickly.',
    ),
  ),
  84 => array(
    'slug' => 'locuri-de-joaca-familie-copii',
    'id' => 109,
    'name' => array(
      'ro' => 'Locuri de joacă',
      'en' => 'Playgrounds',
    ),
    'description' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Locuri de joacă | Bilete și rezervări online',
      'en' => 'Playgrounds | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă locuri de joacă pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book playgrounds on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe de divertisment, joacă și distracție pentru copii și adulți. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria locuri de joacă și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book playgrounds online and compare available experiences quickly.',
    ),
  ),
  85 => array(
    'slug' => 'parcuri-pentru-copii-familie-copii',
    'id' => 112,
    'name' => array(
      'ro' => 'Parcuri pentru copii',
      'en' => 'Children’s parks',
    ),
    'description' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities.',
    ),
    'meta_title' => array(
      'ro' => 'Parcuri pentru copii | Bilete și rezervări online',
      'en' => 'Children’s parks | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă parcuri pentru copii pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book children’s parks on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe sigure și atractive pentru copii, cu activități adaptate vârstei. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria parcuri pentru copii și compară rapid activitățile disponibile.',
      'en' => 'Safe, engaging experiences for children, with age-appropriate activities. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book children’s parks online and compare available experiences quickly.',
    ),
  ),
  86 => array(
    'slug' => 'corporate-grupuri',
    'id' => 113,
    'name' => array(
      'ro' => 'Corporate & grupuri',
      'en' => 'Corporate & groups',
    ),
    'description' => array(
      'ro' => 'Activități pentru echipe, grupuri, evenimente private și pachete organizate.',
      'en' => 'Activities for teams, groups, private events and organized packages.',
    ),
    'meta_title' => array(
      'ro' => 'Corporate & grupuri | Bilete și rezervări online',
      'en' => 'Corporate & groups | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă corporate & grupuri pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover corporate & groups on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă team building, activități corporate, pachete de grup și evenimente private. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover team building, corporate activities, group packages and private events. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
  87 => array(
    'slug' => 'team-building-corporate-grupuri',
    'id' => 114,
    'name' => array(
      'ro' => 'Team building',
      'en' => 'Team building',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Team building | Bilete și rezervări online',
      'en' => 'Team building | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă team building pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book team building on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria team building și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book team building online and compare available experiences quickly.',
    ),
  ),
  88 => array(
    'slug' => 'activitati-corporate-corporate-grupuri',
    'id' => 115,
    'name' => array(
      'ro' => 'Activități corporate',
      'en' => 'Corporate activities',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Activități corporate | Bilete și rezervări online',
      'en' => 'Corporate activities | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități corporate pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book corporate activities on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități corporate și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book corporate activities online and compare available experiences quickly.',
    ),
  ),
  89 => array(
    'slug' => 'activitati-pentru-grupuri-corporate-grupuri',
    'id' => 116,
    'name' => array(
      'ro' => 'Activități pentru grupuri',
      'en' => 'Activities for groups',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru grupuri | Bilete și rezervări online',
      'en' => 'Activities for groups | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități pentru grupuri pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book activities for groups on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități pentru grupuri și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book activities for groups online and compare available experiences quickly.',
    ),
  ),
  90 => array(
    'slug' => 'private-events-corporate-grupuri',
    'id' => 117,
    'name' => array(
      'ro' => 'Private events',
      'en' => 'Private events',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Private events | Bilete și rezervări online',
      'en' => 'Private events | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă private events pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book private events on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria private events și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book private events online and compare available experiences quickly.',
    ),
  ),
  91 => array(
    'slug' => 'group-bookings-corporate-grupuri',
    'id' => 118,
    'name' => array(
      'ro' => 'Group bookings',
      'en' => 'Group bookings',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Group bookings | Bilete și rezervări online',
      'en' => 'Group bookings | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă group bookings pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book group bookings on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria group bookings și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book group bookings online and compare available experiences quickly.',
    ),
  ),
  92 => array(
    'slug' => 'pachete-corporate-corporate-grupuri',
    'id' => 119,
    'name' => array(
      'ro' => 'Pachete corporate',
      'en' => 'Corporate packages',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Pachete corporate | Bilete și rezervări online',
      'en' => 'Corporate packages | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă pachete corporate pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book corporate packages on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria pachete corporate și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book corporate packages online and compare available experiences quickly.',
    ),
  ),
  93 => array(
    'slug' => 'pachete-aniversare-corporate-grupuri',
    'id' => 120,
    'name' => array(
      'ro' => 'Pachete aniversare',
      'en' => 'Birthday packages',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Pachete aniversare | Bilete și rezervări online',
      'en' => 'Birthday packages | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă pachete aniversare pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book birthday packages on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria pachete aniversare și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book birthday packages online and compare available experiences quickly.',
    ),
  ),
  94 => array(
    'slug' => 'pachete-scoli-clase-corporate-grupuri',
    'id' => 121,
    'name' => array(
      'ro' => 'Pachete școli / clase',
      'en' => 'School / class packages',
    ),
    'description' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale.',
      'en' => 'Organized experiences for groups, teams, classes or special events.',
    ),
    'meta_title' => array(
      'ro' => 'Pachete școli / clase | Bilete și rezervări online',
      'en' => 'School / class packages | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă pachete școli / clase pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book school / class packages on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe organizate pentru grupuri, echipe, clase sau evenimente speciale. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria pachete școli / clase și compară rapid activitățile disponibile.',
      'en' => 'Organized experiences for groups, teams, classes or special events. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book school / class packages online and compare available experiences quickly.',
    ),
  ),
  95 => array(
    'slug' => 'activitati-pentru-comunitati-corporate-grupuri',
    'id' => 122,
    'name' => array(
      'ro' => 'Activități pentru comunități',
      'en' => 'Activities for communities',
    ),
    'description' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online.',
    ),
    'meta_title' => array(
      'ro' => 'Activități pentru comunități | Bilete și rezervări online',
      'en' => 'Activities for communities | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Rezervă activități pentru comunități pe bilete.online. Compară experiențe, activități și pachete potrivite pentru familie, prieteni, școli sau grupuri.',
      'en' => 'Book activities for communities on bilete.online. Compare experiences, activities and packages for families, friends, schools or groups.',
    ),
    'seo_body' => array(
      'ro' => 'Experiențe relevante din categoria Corporate & grupuri, ușor de rezervat online. Găsește opțiuni potrivite pentru weekend, vacanțe, ieșiri cu familia, grupuri de prieteni, școli sau evenimente private. Rezervă online experiențe din categoria activități pentru comunități și compară rapid activitățile disponibile.',
      'en' => 'Relevant experiences in Corporate & groups, easy to book online. Find options for weekends, holidays, family outings, groups of friends, schools or private events. Book activities for communities online and compare available experiences quickly.',
    ),
  ),
  96 => array(
    'slug' => 'cultura-arta',
    'id' => 123,
    'name' => array(
      'ro' => 'Cultură & artă',
      'en' => 'Culture & art',
    ),
    'description' => array(
      'ro' => 'Evenimente, spectacole, expoziții și experiențe culturale pentru toate vârstele.',
      'en' => 'Events, shows, exhibitions and cultural experiences for all ages.',
    ),
    'meta_title' => array(
      'ro' => 'Cultură & artă | Bilete și rezervări online',
      'en' => 'Culture & art | Tickets & online bookings',
    ),
    'meta_description' => array(
      'ro' => 'Descoperă cultură & artă pe bilete.online: activități, experiențe și bilete pentru familie, copii, prieteni, școli, turiști și grupuri.',
      'en' => 'Discover culture & art on bilete.online: activities, experiences and tickets for families, children, friends, schools, tourists and groups.',
    ),
    'seo_body' => array(
      'ro' => 'Descoperă teatru, concerte, spectacole, dans, film, literatură și evenimente culturale. Alege activități potrivite pentru familie, copii, cupluri, prieteni, turiști, școli, echipe sau grupuri, cu rezervare simplă și experiențe ușor de comparat online.',
      'en' => 'Discover theatre, concerts, shows, dance, film, literature and cultural events. Choose activities for families, children, couples, friends, tourists, schools, teams or groups, with simple booking and experiences that are easy to compare online.',
    ),
  ),
);
    }
}
