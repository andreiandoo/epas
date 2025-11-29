<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change URL columns to TEXT to accommodate longer URLs like embed URLs
     * Using TEXT instead of VARCHAR(2048) to avoid MySQL row size limits
     */
    public function up(): void
    {
        // Venues table
        Schema::table('venues', function (Blueprint $table) {
            $table->text('website_url')->nullable()->change();
            $table->text('facebook_url')->nullable()->change();
            $table->text('instagram_url')->nullable()->change();
            $table->text('tiktok_url')->nullable()->change();
            $table->text('video_url')->nullable()->change();
            $table->text('google_maps_url')->nullable()->change();
            $table->text('image_url')->nullable()->change();
        });

        // Artists table
        Schema::table('artists', function (Blueprint $table) {
            $table->text('website')->nullable()->change();
            $table->text('facebook_url')->nullable()->change();
            $table->text('instagram_url')->nullable()->change();
            $table->text('tiktok_url')->nullable()->change();
            $table->text('youtube_url')->nullable()->change();
            $table->text('spotify_url')->nullable()->change();
            $table->text('image_url')->nullable()->change();
        });

        // Events table
        Schema::table('events', function (Blueprint $table) {
            $table->text('website_url')->nullable()->change();
            $table->text('facebook_url')->nullable()->change();
            $table->text('poster_url')->nullable()->change();
            $table->text('hero_image_url')->nullable()->change();
            $table->text('video_url')->nullable()->change();
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
