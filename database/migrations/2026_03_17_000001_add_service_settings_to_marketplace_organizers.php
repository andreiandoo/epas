<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->json('service_settings')->nullable()->after('invitations_enabled');
        });

        // Set default service_settings for all existing organizers:
        // featuring (id 1) and tracking (id 3) enabled, email and campaign disabled
        DB::table('marketplace_organizers')->whereNull('service_settings')->update([
            'service_settings' => json_encode([
                'featuring_enabled' => true,
                'email_enabled' => false,
                'tracking_enabled' => true,
                'tracking_pricing_model' => 'monthly',
                'campaign_enabled' => false,
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn('service_settings');
        });
    }
};
