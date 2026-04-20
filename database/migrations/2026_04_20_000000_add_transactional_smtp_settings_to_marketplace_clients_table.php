<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add transactional_smtp_settings column for the secondary mail provider
     * dedicated to transactional emails (ticket purchase, password reset, etc.).
     * Falls back to smtp_settings (the primary provider) when null.
     */
    public function up(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->json('transactional_smtp_settings')->nullable()->after('smtp_settings');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_clients', function (Blueprint $table) {
            $table->dropColumn('transactional_smtp_settings');
        });
    }
};
