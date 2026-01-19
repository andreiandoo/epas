<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Google Maps
            $table->string('google_maps_api_key', 255)->nullable();

            // Twilio for SMS
            $table->string('twilio_account_sid', 255)->nullable();
            $table->string('twilio_auth_token', 255)->nullable();
            $table->string('twilio_phone_number', 50)->nullable();

            // OpenWeather for outdoor events
            $table->string('openweather_api_key', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_maps_api_key',
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_phone_number',
                'openweather_api_key',
            ]);
        });
    }
};
