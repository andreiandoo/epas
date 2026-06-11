<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill heuristic: completeaza marketplace_organizer_id pe portile cu valoare NULL,
 * folosind organizer-ul evenimentelor de pe acel venue.
 *
 * Pentru fiecare venue:
 *   - Daca exista UN SINGUR marketplace_organizer_id distinct in evenimentele
 *     asociate venue-ului, atribuim toate portile NULL acelui organizer.
 *   - Daca venue-ul e folosit de mai multi organizatori (caz rar — partajare),
 *     portile NULL raman NULL → controllerul le filtreaza out (fără a împărtăși
 *     porți între organizatori diferiți).
 *
 * Asta corecteaza bug-ul "vad portile altor organizatori" semnalat de Sf. Ana,
 * unde Lacul Sf. Ana avea porti create inainte de migratia 2026_06_03_120000.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Identifica venue-urile cu UN SINGUR organizer in events. Foloseste subquery
        // care intoarce (venue_id, organizer_id) doar pentru venue-urile cu un
        // singur organizer distinct.
        $singleOwners = DB::table('events')
            ->select('venue_id', DB::raw('MIN(marketplace_organizer_id) AS organizer_id'))
            ->whereNotNull('venue_id')
            ->whereNotNull('marketplace_organizer_id')
            ->groupBy('venue_id')
            ->havingRaw('COUNT(DISTINCT marketplace_organizer_id) = 1')
            ->get();

        foreach ($singleOwners as $row) {
            DB::table('venue_gates')
                ->where('venue_id', $row->venue_id)
                ->whereNull('marketplace_organizer_id')
                ->update(['marketplace_organizer_id' => $row->organizer_id]);
        }
    }

    public function down(): void
    {
        // Nu rollback — datele sunt mostenite, nu generate.
    }
};
