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
        // Add gamification and feature settings to marketplace_organizers
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->boolean('gamification_enabled')->default(false)->after('settings');
            $table->boolean('invitations_enabled')->default(true)->after('gamification_enabled');
            $table->json('tax_settings')->nullable()->after('invitations_enabled');
        });

        // Add gamification settings to marketplace_events
        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->boolean('gamification_enabled')->default(false)->after('is_featured');
            $table->decimal('points_per_purchase', 10, 2)->nullable()->after('gamification_enabled');
            $table->decimal('max_points_discount_percent', 5, 2)->nullable()->after('points_per_purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['gamification_enabled', 'invitations_enabled', 'tax_settings']);
        });

        Schema::table('marketplace_events', function (Blueprint $table) {
            $table->dropColumn(['gamification_enabled', 'points_per_purchase', 'max_points_discount_percent']);
        });
    }
};
