<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leisure capacity refactor (no breaking changes to ticket_type_capacities):
 *
 * Adds "general availability" config on each TicketType so operators don't
 * have to enter 30 daily rows by hand. Concrete daily/hourly rows in
 * ticket_type_capacities become EXCEPTIONS:
 *
 *   - missing row → use ticket_types.default_daily_capacity + schedule
 *   - present row → override that specific date / slot
 *
 * Schedule fields are used to auto-generate hourly slots for time-based
 * rentals at read time — we still don't pre-populate them in the DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_types', 'leisure_default_daily_capacity')) {
                $table->unsignedInteger('leisure_default_daily_capacity')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_schedule_open_time')) {
                $table->time('leisure_schedule_open_time')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_schedule_close_time')) {
                $table->time('leisure_schedule_close_time')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_schedule_days')) {
                $table->json('leisure_schedule_days')->nullable();
                // Stored as int[] ISO weekday (1=Mon..7=Sun); null = all days
            }
            if (! Schema::hasColumn('ticket_types', 'leisure_slot_duration_minutes')) {
                $table->unsignedInteger('leisure_slot_duration_minutes')->nullable();
                // For time-based rentals: how big is each generated slot
                // (e.g. 60 → hourly slots 10:00-11:00, 11:00-12:00, …)
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            foreach ([
                'leisure_default_daily_capacity',
                'leisure_schedule_open_time',
                'leisure_schedule_close_time',
                'leisure_schedule_days',
                'leisure_slot_duration_minutes',
            ] as $c) {
                if (Schema::hasColumn('ticket_types', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
