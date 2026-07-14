<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in flag pentru biletul auto-provizionat "Test POS".
 *
 * De ce: pana acum Event::ensureTestTicketType() adauga un ticket type "Test
 * POS" (10 lei, meta.is_test=true) pe FIECARE eveniment non-leisure, ceea ce
 * polua interfata publica ("Pret de la 10 lei"), cardurile de dashboard si
 * numaratoarea de bilete. Acum devine opt-in per organizator.
 *
 * Default false = organizatorul NU primeste bilete Test POS. Doar cei bifati
 * obtin provizionarea automata (pentru smoke-test-ul aplicatiei mobile POS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'test_pos_enabled')) {
                $table->boolean('test_pos_enabled')
                    ->default(false)
                    ->after('commission_use_floor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_organizers', 'test_pos_enabled')) {
                $table->dropColumn('test_pos_enabled');
            }
        });
    }
};
