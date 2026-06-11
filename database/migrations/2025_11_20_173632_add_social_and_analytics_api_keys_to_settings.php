<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Facebook/Instagram Graph API
            $table->string('facebook_app_id', 255)->nullable();
            $table->string('facebook_app_secret', 255)->nullable();
            $table->text('facebook_access_token')->nullable();

            // Google Analytics
            $table->string('google_analytics_property_id', 255)->nullable();
            $table->text('google_analytics_credentials_json')->nullable();

            // Brevo (formerly Sendinblue)
            $table->string('brevo_api_key', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_app_id',
                'facebook_app_secret',
                'facebook_access_token',
                'google_analytics_property_id',
                'google_analytics_credentials_json',
                'brevo_api_key',
            ]);
        });
    }
};
