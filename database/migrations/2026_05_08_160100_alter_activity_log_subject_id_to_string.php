<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie activity_log default schema folosește unsignedBigInteger pentru
 * subject_id. Modelele cu chei UUID (HasUuids) — ex. CouponCode — nu pot
 * fi logate atâta timp cât coloana e bigint:
 *   "invalid input syntax for type bigint: 019e075f-...".
 *
 * ALTER TABLE la varchar(36) acceptă atât bigint vechi (cast text) cât și
 * UUID-urile noi. Indexul existent pe (subject_type, subject_id) rămâne
 * valid sub PostgreSQL după type change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_log')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE varchar(36) USING subject_id::text');
        } else {
            // MySQL / SQLite fallback prin Doctrine DBAL
            Schema::table('activity_log', function ($table) {
                $table->string('subject_id', 36)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_log')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            // Trebuie să existe doar valori numerice, altfel cast-ul eșuează.
            DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE bigint USING subject_id::bigint');
        } else {
            Schema::table('activity_log', function ($table) {
                $table->unsignedBigInteger('subject_id')->nullable()->change();
            });
        }
    }
};
