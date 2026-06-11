<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Pivot pentru allowed map: artist_type ↔ artist_genre
        if (! Schema::hasTable('artist_type_allowed_genre')) {
            Schema::create('artist_type_allowed_genre', function (Blueprint $table) {
                $table->id();
                $table->foreignId('artist_type_id')->constrained('artist_types')->cascadeOnDelete();
                $table->foreignId('artist_genre_id')->constrained('artist_genres')->cascadeOnDelete();
                $table->unique(['artist_type_id','artist_genre_id'], 'artist_type_genre_unique');
            });
        }

        // 2) Extinderi artists
        Schema::table('artists', function (Blueprint $table) {
            if (! Schema::hasColumn('artists','county')) {
                $table->string('county', 120)->nullable()->after('country'); // județ
            }

            // followers (separate pe canal)
            foreach ([
                'facebook_followers',
                'instagram_followers',
                'tiktok_followers',
                'spotify_followers',
                'youtube_followers',
            ] as $col) {
                if (! Schema::hasColumn('artists', $col)) {
                    $table->unsignedBigInteger($col)->nullable()->after('agent_website');
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('artist_type_allowed_genre')) {
            Schema::drop('artist_type_allowed_genre');
        }
        Schema::table('artists', function (Blueprint $table) {
            foreach (['county','facebook_followers','instagram_followers','tiktok_followers','spotify_followers','youtube_followers'] as $col) {
                if (Schema::hasColumn('artists', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
