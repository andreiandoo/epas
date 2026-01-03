<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds fields to support marketplace partner venues:
     * - is_partner: marks venues as partner venues for a marketplace
     * - partner_notes: internal notes about the partnership
     */
    public function up(): void
    {
        if (Schema::hasTable('venues')) {
            Schema::table('venues', function (Blueprint $table) {
                if (!Schema::hasColumn('venues', 'is_partner')) {
                    $table->boolean('is_partner')->default(false)->after('marketplace_client_id');
                }
                if (!Schema::hasColumn('venues', 'partner_notes')) {
                    $table->text('partner_notes')->nullable()->after('is_partner');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('venues')) {
            Schema::table('venues', function (Blueprint $table) {
                if (Schema::hasColumn('venues', 'is_partner')) {
                    $table->dropColumn('is_partner');
                }
                if (Schema::hasColumn('venues', 'partner_notes')) {
                    $table->dropColumn('partner_notes');
                }
            });
        }
    }
};
