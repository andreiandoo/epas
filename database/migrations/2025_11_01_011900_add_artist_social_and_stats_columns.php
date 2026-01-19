<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            // contact & location
            if (! Schema::hasColumn('artists', 'email'))   $table->string('email')->nullable()->index();
            if (! Schema::hasColumn('artists', 'phone'))   $table->string('phone', 60)->nullable();
            if (! Schema::hasColumn('artists', 'website')) $table->string('website')->nullable();

            if (! Schema::hasColumn('artists', 'country')) $table->string('country', 120)->nullable()->index();
            if (! Schema::hasColumn('artists', 'state'))   $table->string('state', 120)->nullable()->index();
            if (! Schema::hasColumn('artists', 'county'))  $table->string('county', 120)->nullable()->index();
            if (! Schema::hasColumn('artists', 'city'))    $table->string('city', 120)->nullable()->index();

            // social urls
            if (! Schema::hasColumn('artists', 'facebook_url'))  $table->string('facebook_url')->nullable();
            if (! Schema::hasColumn('artists', 'instagram_url')) $table->string('instagram_url')->nullable();
            if (! Schema::hasColumn('artists', 'tiktok_url'))    $table->string('tiktok_url')->nullable();
            if (! Schema::hasColumn('artists', 'youtube_url'))   $table->string('youtube_url')->nullable();
            if (! Schema::hasColumn('artists', 'spotify_url'))   $table->string('spotify_url')->nullable();

            // IDs externe
            if (! Schema::hasColumn('artists', 'youtube_id')) $table->string('youtube_id', 120)->nullable()->index();
            if (! Schema::hasColumn('artists', 'spotify_id')) $table->string('spotify_id', 120)->nullable()->index();

            // followers / stats per canal
            if (! Schema::hasColumn('artists', 'followers_facebook'))  $table->bigInteger('followers_facebook')->nullable();
            if (! Schema::hasColumn('artists', 'followers_instagram')) $table->bigInteger('followers_instagram')->nullable();
            if (! Schema::hasColumn('artists', 'followers_tiktok'))    $table->bigInteger('followers_tiktok')->nullable();
            if (! Schema::hasColumn('artists', 'followers_youtube'))   $table->bigInteger('followers_youtube')->nullable();
            if (! Schema::hasColumn('artists', 'spotify_monthly_listeners')) $table->bigInteger('spotify_monthly_listeners')->nullable();

            // media
            if (! Schema::hasColumn('artists', 'portrait_url'))   $table->string('portrait_url')->nullable();
            if (! Schema::hasColumn('artists', 'hero_image_url')) $table->string('hero_image_url')->nullable();
            if (! Schema::hasColumn('artists', 'logo_url'))       $table->string('logo_url')->nullable();

            // content
            if (! Schema::hasColumn('artists', 'bio_html')) $table->json('bio_html')->nullable(); // acceptăm JSON pt i18n HTML
            if (! Schema::hasColumn('artists', 'youtube_videos')) $table->json('youtube_videos')->nullable();

            // flag
            if (! Schema::hasColumn('artists', 'is_active')) $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            // nu ștergem pentru a nu pierde date; dacă chiar vrei, adaugă drops aici
        });
    }
};
