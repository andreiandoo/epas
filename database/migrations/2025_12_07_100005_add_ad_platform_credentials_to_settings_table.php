<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Google Ads Integration
            $table->string('google_ads_client_id')->nullable();
            $table->text('google_ads_client_secret')->nullable();
            $table->text('google_ads_developer_token')->nullable()->comment('Required for API access');

            // TikTok Ads Integration
            $table->string('tiktok_ads_app_id')->nullable();
            $table->text('tiktok_ads_app_secret')->nullable();

            // LinkedIn Ads Integration
            $table->string('linkedin_ads_client_id')->nullable();
            $table->text('linkedin_ads_client_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_ads_client_id', 'google_ads_client_secret', 'google_ads_developer_token',
                'tiktok_ads_app_id', 'tiktok_ads_app_secret',
                'linkedin_ads_client_id', 'linkedin_ads_client_secret',
            ]);
        });
    }
};
