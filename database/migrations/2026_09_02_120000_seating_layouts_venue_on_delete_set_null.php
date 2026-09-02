<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change seating_layouts.venue_id from ON DELETE CASCADE to ON DELETE SET NULL
 * (and make it nullable) so a seating map is NEVER destroyed when its venue is
 * deleted — it is orphaned (venue_id = null) and can be re-attached to another
 * venue. Maps are only ever removed by deleting the layout directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
        });

        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->unsignedBigInteger('venue_id')->nullable()->change();
        });

        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->foreign('venue_id')
                ->references('id')->on('venues')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
        });

        // Note: reverting to NOT NULL + cascade would fail if any orphaned
        // (null venue_id) rows exist, so we only restore the cascade FK and
        // leave the column nullable.
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->foreign('venue_id')
                ->references('id')->on('venues')
                ->cascadeOnDelete();
        });
    }
};
