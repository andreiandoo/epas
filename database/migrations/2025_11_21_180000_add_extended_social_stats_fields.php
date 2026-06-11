<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add TikTok API key to settings
        Schema::table('settings', function (Blueprint $table) {
            $table->text('tiktok_api_key')->nullable()->after('brevo_api_key');
        });

        // Add extended social stats to artists
        Schema::table('artists', function (Blueprint $table) {
            // YouTube extended stats
            $table->bigInteger('youtube_total_views')->nullable()->after('followers_youtube');
            $table->bigInteger('youtube_total_likes')->nullable()->after('youtube_total_views');

            // Spotify extended stats
            $table->integer('spotify_popularity')->nullable()->after('spotify_monthly_listeners');

            // Timestamp for last stats update
            $table->timestamp('social_stats_updated_at')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('tiktok_api_key');
        });

        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_total_views',
                'youtube_total_likes',
                'spotify_popularity',
                'social_stats_updated_at',
            ]);
        });
    }
};
