<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add scheduling fields to ticket types for automatic activation
     * and target_price to marketplace events.
     */
    public function up(): void
    {
        // Add scheduling fields to ticket_types (Core/Tenant)
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('sales_end_at')
                    ->comment('When this ticket type should automatically become active');
            }
            if (!Schema::hasColumn('ticket_types', 'autostart_when_previous_sold_out')) {
                $table->boolean('autostart_when_previous_sold_out')->default(false)->after('scheduled_at')
                    ->comment('If true, activate when previous ticket types are sold out');
            }
        });

        // Add scheduling fields to marketplace_ticket_types
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_types', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('sale_ends_at')
                    ->comment('When this ticket type should automatically become active');
            }
            if (!Schema::hasColumn('marketplace_ticket_types', 'autostart_when_previous_sold_out')) {
                $table->boolean('autostart_when_previous_sold_out')->default(false)->after('scheduled_at')
                    ->comment('If true, activate when previous ticket types are sold out');
            }
        });

        // Add target_price to marketplace_events (Marketplace Admin only)
        Schema::table('marketplace_events', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_events', 'target_price')) {
                $table->decimal('target_price', 10, 2)->nullable()->after('max_tickets_per_order')
                    ->comment('Target price for the event (Marketplace Admin reference)');
            }
        });

        // Add target_price to events table as well (in case it's used there)
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'target_price')) {
                $table->decimal('target_price', 10, 2)->nullable()->after('max_tickets_per_order')
                    ->comment('Target price for the event (Marketplace Admin reference)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
            if (Schema::hasColumn('ticket_types', 'autostart_when_previous_sold_out')) {
                $table->dropColumn('autostart_when_previous_sold_out');
            }
        });

        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
            if (Schema::hasColumn('marketplace_ticket_types', 'autostart_when_previous_sold_out')) {
                $table->dropColumn('autostart_when_previous_sold_out');
            }
        });

        Schema::table('marketplace_events', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_events', 'target_price')) {
                $table->dropColumn('target_price');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'target_price')) {
                $table->dropColumn('target_price');
            }
        });
    }
};
