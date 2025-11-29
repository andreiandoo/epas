<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        $this->changeColumnToText('venues', [
            'website_url', 'facebook_url', 'instagram_url', 'tiktok_url',
            'video_url', 'google_maps_url', 'image_url'
        ]);

        // Artists table
        $this->changeColumnToText('artists', [
            'website', 'facebook_url', 'instagram_url', 'tiktok_url',
            'youtube_url', 'spotify_url', 'main_image_url', 'logo_url',
            'portrait_url', 'manager_website', 'agent_website'
        ]);

        // Events table
        $this->changeColumnToText('events', [
            'website_url', 'facebook_url', 'poster_url', 'hero_image_url', 'video_url'
        ]);
    }

    /**
     * Change columns to TEXT type if they exist
     */
    private function changeColumnToText(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TEXT NULL");
            }
        }
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
