<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Remove old single API key
            if (Schema::hasColumn('settings', 'tiktok_api_key')) {
                $table->dropColumn('tiktok_api_key');
            }

            // Add new client key and secret
            $table->string('tiktok_client_key', 255)->nullable();
            $table->text('tiktok_client_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['tiktok_client_key', 'tiktok_client_secret']);
            $table->text('tiktok_api_key')->nullable();
        });
    }
};
