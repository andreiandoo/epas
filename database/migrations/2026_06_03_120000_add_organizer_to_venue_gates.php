<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adauga `marketplace_organizer_id` pe `venue_gates` pentru a separa portile
 * intre organizatori care folosesc acelasi venue fizic.
 *
 * Inainte de aceasta migratie, toate portile unui venue erau vizibile pentru
 * orice organizer cu access la acel venue (bug semnalat de utilizatorul Sf. Ana).
 *
 * Strategy:
 *   - NULL = poarta legacy (creata inainte de aceasta separare) → ramane vizibila
 *     pentru toți organizatorii cu access (backward compat).
 *   - NOT NULL = poarta dedicata unui organizer → vizibila DOAR pentru el.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('venue_gates', 'marketplace_organizer_id')) {
            Schema::table('venue_gates', function (Blueprint $table) {
                $table->foreignId('marketplace_organizer_id')
                    ->nullable()
                    ->after('venue_id')
                    ->constrained('marketplace_organizers')
                    ->nullOnDelete();
                $table->index(['venue_id', 'marketplace_organizer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('venue_gates', function (Blueprint $table) {
            $table->dropIndex(['venue_id', 'marketplace_organizer_id']);
            $table->dropConstrainedForeignId('marketplace_organizer_id');
        });
    }
};
