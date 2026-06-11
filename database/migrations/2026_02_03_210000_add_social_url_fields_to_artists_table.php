<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('twitter_url', 255)->nullable()->after('spotify_id');
            $table->string('wiki_url', 255)->nullable()->after('twitter_url');
            $table->string('lastfm_url', 255)->nullable()->after('wiki_url');
            $table->string('itunes_url', 255)->nullable()->after('lastfm_url');
            $table->string('musicbrainz_url', 255)->nullable()->after('itunes_url');
            $table->unsignedBigInteger('twitter_followers')->nullable()->after('musicbrainz_url');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn([
                'twitter_url',
                'wiki_url',
                'lastfm_url',
                'itunes_url',
                'musicbrainz_url',
                'twitter_followers',
            ]);
        });
    }
};
