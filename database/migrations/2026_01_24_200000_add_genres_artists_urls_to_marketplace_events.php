<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->json('genre_ids')->nullable()->after('marketplace_event_category_id');
            $table->json('artist_ids')->nullable()->after('genre_ids');
            $table->string('website_url')->nullable()->after('venue_city');
            $table->string('facebook_url')->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->dropColumn(['genre_ids', 'artist_ids', 'website_url', 'facebook_url']);
        });
    }
};
