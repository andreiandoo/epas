<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('youtube_api_key')->nullable()->after('vat_rate');
            $table->string('spotify_client_id')->nullable()->after('youtube_api_key');
            $table->string('spotify_client_secret')->nullable()->after('spotify_client_id');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['youtube_api_key', 'spotify_client_id', 'spotify_client_secret']);
        });
    }
};
