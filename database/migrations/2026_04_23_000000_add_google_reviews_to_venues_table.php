<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('google_place_id', 255)->nullable()->after('google_maps_url');
            $table->json('google_reviews_cached')->nullable()->after('google_place_id');
            $table->timestamp('google_reviews_updated_at')->nullable()->after('google_reviews_cached');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['google_place_id', 'google_reviews_cached', 'google_reviews_updated_at']);
        });
    }
};
