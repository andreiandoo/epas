<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add series numbering fields to ticket types for ticket serial numbers.
     */
    public function up(): void
    {
        // Add series fields to marketplace_ticket_types
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_ticket_types', 'series_start')) {
                $table->unsignedInteger('series_start')->nullable()
                    ->comment('Starting number for ticket series (e.g., 1)');
            }
            if (!Schema::hasColumn('marketplace_ticket_types', 'series_end')) {
                $table->unsignedInteger('series_end')->nullable()
                    ->comment('Ending number for ticket series (e.g., 500)');
            }
            if (!Schema::hasColumn('marketplace_ticket_types', 'event_series')) {
                $table->string('event_series', 10)->nullable()
                    ->comment('Event series identifier (e.g., A, B, VIP1)');
            }
        });

        // Add series fields to ticket_types (Core/Tenant)
        Schema::table('ticket_types', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_types', 'series_start')) {
                $table->unsignedInteger('series_start')->nullable()
                    ->comment('Starting number for ticket series (e.g., 1)');
            }
            if (!Schema::hasColumn('ticket_types', 'series_end')) {
                $table->unsignedInteger('series_end')->nullable()
                    ->comment('Ending number for ticket series (e.g., 500)');
            }
            if (!Schema::hasColumn('ticket_types', 'event_series')) {
                $table->string('event_series', 10)->nullable()
                    ->comment('Event series identifier (e.g., A, B, VIP1)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_ticket_types', 'series_start')) {
                $table->dropColumn('series_start');
            }
            if (Schema::hasColumn('marketplace_ticket_types', 'series_end')) {
                $table->dropColumn('series_end');
            }
            if (Schema::hasColumn('marketplace_ticket_types', 'event_series')) {
                $table->dropColumn('event_series');
            }
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'series_start')) {
                $table->dropColumn('series_start');
            }
            if (Schema::hasColumn('ticket_types', 'series_end')) {
                $table->dropColumn('series_end');
            }
            if (Schema::hasColumn('ticket_types', 'event_series')) {
                $table->dropColumn('event_series');
            }
        });
    }
};
