<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Adăugăm coloanele doar dacă lipsesc (protejăm instalările deja existente)
        if (! Schema::hasColumn('events', 'starts_at')) {
            Schema::table('events', function (Blueprint $table) {
                // Postgres: folosește timestamptz pentru timezone
                $table->timestampTz('starts_at')->nullable()->after('city');
            });

            // backfill: dacă nu avem altă sursă, punem created_at ca fallback
            DB::statement("UPDATE events SET starts_at = COALESCE(starts_at, created_at)");
        }

        if (! Schema::hasColumn('events', 'ends_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->timestampTz('ends_at')->nullable()->after('starts_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'ends_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('ends_at');
            });
        }
        if (Schema::hasColumn('events', 'starts_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('starts_at');
            });
        }
    }
};
