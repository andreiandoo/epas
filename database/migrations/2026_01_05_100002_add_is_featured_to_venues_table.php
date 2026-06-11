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
        Schema::table('venues', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_partner');

            // Index for quick featured venue lookups
            $table->index(['marketplace_client_id', 'is_featured'], 'venues_mp_featured_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropIndex('venues_mp_featured_idx');
            $table->dropColumn('is_featured');
        });
    }
};
