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
        Schema::table('settings', function (Blueprint $table) {
            // Change API key columns to TEXT to accommodate encrypted values
            $table->text('youtube_api_key')->nullable()->change();
            $table->text('spotify_client_id')->nullable()->change();
            $table->text('spotify_client_secret')->nullable()->change();
            $table->text('google_maps_api_key')->nullable()->change();
            $table->text('twilio_account_sid')->nullable()->change();
            $table->text('twilio_auth_token')->nullable()->change();
            $table->text('twilio_phone_number')->nullable()->change();
            $table->text('openweather_api_key')->nullable()->change();
            $table->text('facebook_app_id')->nullable()->change();
            $table->text('facebook_app_secret')->nullable()->change();
            $table->text('brevo_api_key')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('youtube_api_key')->nullable()->change();
            $table->string('spotify_client_id')->nullable()->change();
            $table->string('spotify_client_secret')->nullable()->change();
            $table->string('google_maps_api_key')->nullable()->change();
            $table->string('twilio_account_sid')->nullable()->change();
            $table->string('twilio_auth_token')->nullable()->change();
            $table->string('twilio_phone_number')->nullable()->change();
            $table->string('openweather_api_key')->nullable()->change();
            $table->string('facebook_app_id')->nullable()->change();
            $table->string('facebook_app_secret')->nullable()->change();
            $table->string('brevo_api_key')->nullable()->change();
        });
    }
};
