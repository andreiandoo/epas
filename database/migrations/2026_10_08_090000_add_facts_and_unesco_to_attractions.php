<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attractions: what an import knows beyond the name, the photo and the description.
 *
 *   facts      JSON, free-form on purpose (an import fills what its source has): other types the place fits,
 *              official website, year built, styles, architects, visitors per year, name in the local language,
 *              opening hours as OpenStreetMap writes them, credits of the gallery photos.
 *   is_unesco  World Heritage Site: a distinction next to the type, and a filter of the list.
 *
 * The index on (client, latitude) serves "what is near this attraction", which reads a band of latitudes.
 * All additive and nullable: marketplaces that never import attractions are not affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            if (! Schema::hasColumn('attractions', 'facts')) {
                $table->json('facts')->nullable();
            }
            if (! Schema::hasColumn('attractions', 'is_unesco')) {
                $table->boolean('is_unesco')->default(false)->index();
            }
        });
        Schema::table('attractions', function (Blueprint $table) {
            $table->index(['marketplace_client_id', 'latitude'], 'attractions_client_latitude_index');
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropIndex('attractions_client_latitude_index');
        });
        Schema::table('attractions', function (Blueprint $table) {
            if (Schema::hasColumn('attractions', 'is_unesco')) {
                $table->dropColumn('is_unesco');
            }
            if (Schema::hasColumn('attractions', 'facts')) {
                $table->dropColumn('facts');
            }
        });
    }
};
