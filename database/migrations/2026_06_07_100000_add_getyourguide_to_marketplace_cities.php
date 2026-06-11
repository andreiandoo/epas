<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-city affiliate widget binding for GetYourGuide.
 *
 * Each city gets one numeric ID identifying it on GetYourGuide's side
 * (e.g. Bucharest = 124688, visible in the GYG URL `...-l124688/`). The
 * affiliate partner ID itself stays at the marketplace level (one per
 * marketplace, set on marketplace_clients.settings.getyourguide.partner_id)
 * because all widgets on the same marketplace share the same affiliate
 * code — they only differ by city.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_cities', function (Blueprint $table) {
            // GetYourGuide's location/city identifier. Theirs is numeric
            // but we store as string to be tolerant of any format change
            // (and to avoid 0 being mistaken for "unset").
            $table->string('getyourguide_city_id', 40)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_cities', function (Blueprint $table) {
            $table->dropColumn('getyourguide_city_id');
        });
    }
};
