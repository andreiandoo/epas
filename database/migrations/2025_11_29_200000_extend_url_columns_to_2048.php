<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Extend URL columns to 2048 characters to accommodate longer URLs like embed URLs
     */
    public function up(): void
    {
        // Venues table
        Schema::table('venues', function (Blueprint $table) {
            $table->string('website_url', 2048)->nullable()->change();
            $table->string('facebook_url', 2048)->nullable()->change();
            $table->string('instagram_url', 2048)->nullable()->change();
            $table->string('tiktok_url', 2048)->nullable()->change();
            $table->string('video_url', 2048)->nullable()->change();
            $table->string('google_maps_url', 2048)->nullable()->change();
            $table->string('image_url', 2048)->nullable()->change();
        });

        // Artists table
        Schema::table('artists', function (Blueprint $table) {
            $table->string('website', 2048)->nullable()->change();
            $table->string('facebook_url', 2048)->nullable()->change();
            $table->string('instagram_url', 2048)->nullable()->change();
            $table->string('tiktok_url', 2048)->nullable()->change();
            $table->string('youtube_url', 2048)->nullable()->change();
            $table->string('spotify_url', 2048)->nullable()->change();
            $table->string('image_url', 2048)->nullable()->change();
        });

        // Events table
        Schema::table('events', function (Blueprint $table) {
            $table->string('website_url', 2048)->nullable()->change();
            $table->string('facebook_url', 2048)->nullable()->change();
            $table->string('poster_url', 2048)->nullable()->change();
            $table->string('hero_image_url', 2048)->nullable()->change();
            $table->string('video_url', 2048)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting may cause data truncation if longer URLs exist
        // This is intentionally left without reverting to VARCHAR(255)
    }
};
