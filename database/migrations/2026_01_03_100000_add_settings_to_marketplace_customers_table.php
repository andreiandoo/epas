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
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('accepts_marketing')
                ->comment('User preferences: notification_reminders, notification_favorites, privacy_history, privacy_marketing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_customers', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
