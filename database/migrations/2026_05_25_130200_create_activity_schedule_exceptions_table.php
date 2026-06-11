<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module — A1 / step 3: schedule exceptions.
 *
 * Overrides the weekly schedule for a specific calendar date. Two modes:
 *   1. `is_closed = true` → activity is closed that day regardless of
 *      `activity_schedules` (holidays, private maintenance days).
 *   2. `is_closed = false` + `open_time`/`close_time` populated → custom
 *      hours that day (e.g. shorter Christmas Eve schedule). Multiple
 *      rows per date are NOT supported — single override interval per
 *      exceptional day. Enforced by UNIQUE(activity_id, exception_date).
 *
 * SlotResolver checks this table first; if any matching row exists for
 * the requested date, it uses the override instead of the weekly schedule.
 *
 * Non-breaking: brand new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_schedule_exceptions')) {
            return;
        }

        Schema::create('activity_schedule_exceptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')
                ->constrained('activities')
                ->cascadeOnDelete();

            $table->date('exception_date');

            // Closed flag wins over the time fields — if true, open/close are ignored.
            $table->boolean('is_closed')->default(true);

            // Override hours when is_closed = false. Nullable so a "closed day" row
            // doesn't need to invent dummy times.
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();

            $table->text('reason')->nullable(); // ex: "Sărbătoare legală", "Mentenanță"

            $table->timestamps();

            // One override row per (activity, date). If the organizer needs split hours
            // on a holiday (rare), they delete + recreate; we don't support multi-row.
            $table->unique(['activity_id', 'exception_date'], 'act_sched_exc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_schedule_exceptions');
    }
};
