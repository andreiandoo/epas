<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in flag pentru semantica "floor" a comisionului fix.
 *
 * De ce: campul `fixed_commission_default` era ambiguu — pentru unii org-uri
 * era interpretat ca floor (min per bilet), pentru altii ca additive (rate +
 * fix), pentru altii ca standalone. Codul actual (POS on_top) aplica DEJA
 * floor cand mode='added_on_top'. Adaugarea automata a floor-ului si in
 * customer checkout + admin display + raport ar afecta org-urile cu alta
 * intentie.
 *
 * Solutie: flag opt-in explicit `commission_use_floor`. Default false =
 * comportament vechi. Doar organizatorii care bifeaza obtin comportamentul:
 *   comision per bilet = max(rate% * pret, fixed_commission_default)
 * Aplicat consistent in: POS, customer checkout, SalesBreakdownService, raport.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'commission_use_floor')) {
                $table->boolean('commission_use_floor')
                    ->default(false)
                    ->after('default_commission_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_organizers', 'commission_use_floor')) {
                $table->dropColumn('commission_use_floor');
            }
        });
    }
};
