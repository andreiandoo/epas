<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a cover photo came from, and under what terms.
 *
 * Most of the catalogue's photos are our own, and for those this stays null. The monuments
 * imported from Wikidata bring their pictures from Wikimedia Commons, which are almost all
 * CC BY-SA: free to publish, but only next to the photographer's name, the licence and a link
 * back. That is a per-photo obligation, so it has to travel with the photo rather than sit in a
 * page footer.
 *
 * Shape: {"author": "Itineris55", "license": "CC BY-SA 4.0",
 *         "license_url": "https://creativecommons.org/licenses/by-sa/4.0/",
 *         "source": "https://commons.wikimedia.org/wiki/File:..."}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $t) {
            $t->json('cover_image_credit')->nullable()->after('cover_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $t) {
            $t->dropColumn('cover_image_credit');
        });
    }
};
