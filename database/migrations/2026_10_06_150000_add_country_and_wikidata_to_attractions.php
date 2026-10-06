<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attractions for a marketplace that spans several countries (Viaqui).
 *
 *   country      ISO code of the country the attraction is in. Many attractions have no city (national parks,
 *                lakes, caves in the hills), so the country cannot be read from the city.
 *   wikidata_id  the Wikidata item an imported attraction came from, for re-imports and for later enrichment.
 *   popularity   number of Wikipedia language editions with an article about it: a rough measure of how well
 *                known a place is, used to order lists.
 *
 * All nullable: nothing changes for the marketplaces that already have attractions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            if (! Schema::hasColumn('attractions', 'country')) {
                $table->string('country', 2)->nullable();
                $table->index(['marketplace_client_id', 'country', 'is_visible'], 'attractions_mp_country_idx');
            }
            if (! Schema::hasColumn('attractions', 'wikidata_id')) {
                $table->string('wikidata_id', 16)->nullable()->index();
            }
            if (! Schema::hasColumn('attractions', 'popularity')) {
                $table->unsignedInteger('popularity')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            if (Schema::hasColumn('attractions', 'country')) {
                $table->dropIndex('attractions_mp_country_idx');
                $table->dropColumn('country');
            }
            if (Schema::hasColumn('attractions', 'wikidata_id')) {
                $table->dropColumn('wikidata_id');
            }
            if (Schema::hasColumn('attractions', 'popularity')) {
                $table->dropColumn('popularity');
            }
        });
    }
};
