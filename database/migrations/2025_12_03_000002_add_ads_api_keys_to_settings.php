<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Google Ads API
            $table->string('google_ads_customer_id', 50)->nullable();
            $table->string('google_ads_developer_token', 255)->nullable();
            $table->text('google_ads_credentials_json')->nullable();

            // TikTok Ads API
            $table->string('tiktok_ads_advertiser_id', 50)->nullable();
            $table->text('tiktok_ads_access_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_ads_customer_id',
                'google_ads_developer_token',
                'google_ads_credentials_json',
                'tiktok_ads_advertiser_id',
                'tiktok_ads_access_token',
            ]);
        });
    }
};
