<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('google_place_id', 255)->nullable()->after('google_maps_url');
            $table->decimal('google_rating', 2, 1)->nullable()->after('google_place_id');
            $table->unsignedInteger('google_reviews_count')->nullable()->after('google_rating');
            $table->json('google_reviews')->nullable()->after('google_reviews_count');
            $table->timestamp('google_reviews_updated_at')->nullable()->after('google_reviews');

            $table->index('google_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex(['google_place_id']);
            $table->dropColumn([
                'google_place_id',
                'google_rating',
                'google_reviews_count',
                'google_reviews',
                'google_reviews_updated_at',
            ]);
        });
    }
};
