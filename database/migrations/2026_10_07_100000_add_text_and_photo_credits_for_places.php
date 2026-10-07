<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credits for content that is not ours to give away, for marketplaces that import places (Viaqui).
 *
 *   attractions.description_credit    who wrote the description and under which licence, when it comes from
 *                                     Wikipedia (CC BY-SA requires the source and the licence next to the text).
 *   marketplace_cities.image_credit   author and licence of a city photo taken from Wikimedia Commons.
 *
 * Both nullable JSON: nothing changes for content the marketplace owns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attractions', 'description_credit')) {
            Schema::table('attractions', function (Blueprint $table) {
                $table->json('description_credit')->nullable();
            });
        }
        if (! Schema::hasColumn('marketplace_cities', 'image_credit')) {
            Schema::table('marketplace_cities', function (Blueprint $table) {
                $table->json('image_credit')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attractions', 'description_credit')) {
            Schema::table('attractions', function (Blueprint $table) {
                $table->dropColumn('description_credit');
            });
        }
        if (Schema::hasColumn('marketplace_cities', 'image_credit')) {
            Schema::table('marketplace_cities', function (Blueprint $table) {
                $table->dropColumn('image_credit');
            });
        }
    }
};
