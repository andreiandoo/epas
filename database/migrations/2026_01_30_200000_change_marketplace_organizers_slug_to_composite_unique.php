<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change slug unique constraint from global unique to composite unique
     * (marketplace_client_id + slug) to allow same slug in different marketplaces
     * and to properly handle soft-deleted records.
     */
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            // Drop the existing global unique constraint on slug
            $table->dropUnique('marketplace_organizers_slug_unique');

            // Add composite unique constraint (marketplace_client_id + slug)
            // This allows the same slug to exist in different marketplaces
            $table->unique(['marketplace_client_id', 'slug'], 'mp_organizers_client_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('mp_organizers_client_slug_unique');

            // Restore the original global unique constraint
            $table->unique('slug', 'marketplace_organizers_slug_unique');
        });
    }
};
