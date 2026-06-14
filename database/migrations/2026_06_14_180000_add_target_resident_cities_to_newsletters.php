<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds target_resident_cities to marketplace_newsletters so an operator
 * can target customers by WHERE THEY LIVE — distinct from
 * target_city_ids which targets WHERE THE EVENT IS.
 *
 * The recipient resolver matches against:
 *   - settings.detected_location.determined_city_id (auto-detected
 *     from order history by customers:detect-cities)
 *   - marketplace_customers.city (manual address field) — case +
 *     accent insensitive
 *
 * Stored as JSON array of marketplace_cities IDs.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_newsletters', 'target_resident_cities')) {
                $table->json('target_resident_cities')->nullable()->after('target_city_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_newsletters', 'target_resident_cities')) {
                $table->dropColumn('target_resident_cities');
            }
        });
    }
};
