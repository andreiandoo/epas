<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            // Google Maps link
            $table->string('google_maps_url', 500)->nullable()->after('lng');

            // Additional phone field
            $table->string('phone2', 50)->nullable()->after('phone');

            // Additional email field
            $table->string('email2', 190)->nullable()->after('email');

            // Video fields
            $table->enum('video_type', ['youtube', 'upload'])->nullable()->after('image_url');
            $table->string('video_url', 500)->nullable()->after('video_type');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn([
                'google_maps_url',
                'phone2',
                'email2',
                'video_type',
                'video_url',
            ]);
        });
    }
};
