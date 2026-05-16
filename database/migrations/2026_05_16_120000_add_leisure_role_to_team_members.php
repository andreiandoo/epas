<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adauga `leisure_role` pentru organizatori de tip leisure_venue.
 *
 * Rol secundar (in plus fata de role principal admin/manager/staff). Determina
 * ce ecran vede operatorul in aplicatia mobila + ce feature-uri are activate
 * cand nu are un LeisureShift activ.
 *
 * Valori posibile (nullable):
 *   - check_in                  (gate scanner)
 *   - rental_boats              (operator inchirieri barci)
 *   - rental_pontoon            (operator inchirieri vaporas)
 *   - validation_pontoon        (operator validare bilete vaporas)
 *   - rental_sled               (operator inchirieri sanii)
 *   - validation_tow            (operator validare tractari)
 *   - pos_cashier               (operator casa POS fix)
 *   - admin_mobile              (membru admin — scan + vanzare prin app mobila)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizer_team_members', function (Blueprint $table) {
            $table->string('leisure_role', 32)->nullable()->after('role');
            $table->index('leisure_role');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizer_team_members', function (Blueprint $table) {
            $table->dropIndex(['leisure_role']);
            $table->dropColumn('leisure_role');
        });
    }
};
