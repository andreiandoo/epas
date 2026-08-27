<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix-up migration: the original individual-information migration was
 * modified in place (split individual_id_series + individual_id_number
 * merged into a single individual_id_series_number). Prod already ran
 * the pre-merge version, so this reconciles the schema idempotently:
 *
 *   - if individual_id_series_number is missing → add it
 *   - if individual_id_series exists → drop it (data was never entered
 *     since the field pair broke saves on first attempt)
 *   - if individual_id_number exists → drop it (same reason)
 *
 * Safe to re-run; matches the intended final shape either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'individual_id_series_number')) {
                $table->string('individual_id_series_number', 40)->nullable()->after('individual_cnp');
            }
        });

        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_organizers', 'individual_id_series')) {
                $table->dropColumn('individual_id_series');
            }
        });

        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_organizers', 'individual_id_number')) {
                $table->dropColumn('individual_id_number');
            }
        });
    }

    public function down(): void
    {
        // No rollback — the split columns were never populated; keeping
        // the merged column is the desired steady state.
    }
};
