<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * event_artist only had unique(event_id, artist_id), so lookups that start from
 * the artist (the partner API's "artists with upcoming events" and its event
 * counts) had no index to use.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_artist', function (Blueprint $table) {
            $table->index(['artist_id', 'event_id'], 'event_artist_artist_id_event_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_artist', function (Blueprint $table) {
            $table->dropIndex('event_artist_artist_id_event_id_idx');
        });
    }
};
