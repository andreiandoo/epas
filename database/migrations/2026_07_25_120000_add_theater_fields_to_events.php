<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theater-specific event details (shown only for tenants with tenant_type=theater):
 * Regia, rol principal, durata, distribuție, echipă creativă.
 * All nullable — non-breaking for existing events / other verticals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'theater_director')) {
                $table->string('theater_director', 190)->nullable()->after('multi_slots');
            }
            if (!Schema::hasColumn('events', 'theater_lead')) {
                $table->string('theater_lead', 190)->nullable()->after('theater_director');
            }
            if (!Schema::hasColumn('events', 'theater_duration')) {
                $table->string('theater_duration', 60)->nullable()->after('theater_lead');
            }
            if (!Schema::hasColumn('events', 'theater_cast')) {
                $table->json('theater_cast')->nullable()->after('theater_duration');
            }
            if (!Schema::hasColumn('events', 'theater_creative')) {
                $table->json('theater_creative')->nullable()->after('theater_cast');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['theater_director', 'theater_lead', 'theater_duration', 'theater_cast', 'theater_creative']);
        });
    }
};
