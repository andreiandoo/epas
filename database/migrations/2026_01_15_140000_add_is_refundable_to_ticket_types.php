<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_refundable field to ticket types for refund eligibility.
     */
    public function up(): void
    {
        // Add to marketplace_ticket_types
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_types', 'is_refundable')) {
                $table->boolean('is_refundable')->default(false)->after('is_visible')
                    ->comment('Whether this ticket type can be refunded if event is postponed/cancelled');
            }
        });

        // Add to ticket_types (Core/Tenant)
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'is_refundable')) {
                $table->boolean('is_refundable')->default(false)->after('status')
                    ->comment('Whether this ticket type can be refunded if event is postponed/cancelled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'is_refundable')) {
                $table->dropColumn('is_refundable');
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'is_refundable')) {
                $table->dropColumn('is_refundable');
            }
        });
    }
};
