<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module — A1 / step 2: weekly recurring schedule.
 *
 * One row = one open interval on one day of the week. Multiple rows per
 * (activity_id, day_of_week) are allowed → an activity can be open
 * 10:00-14:00 AND 17:00-22:00 the same day. SlotResolver iterates the
 * rows and generates time slots from `open_time` to `close_time` using
 * the parent activity's `slot_interval_minutes`.
 *
 * Activities with no `activity_schedules` rows are considered closed
 * every day. The public page hides the date picker in that case.
 *
 * Non-breaking: brand new table; cascade-delete from parent activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_schedules')) {
            return;
        }

        Schema::create('activity_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')
                ->constrained('activities')
                ->cascadeOnDelete();

            // ISO-style: 0 = Monday, 6 = Sunday. Chosen to match Carbon's `dayOfWeekIso`
            // so SlotResolver doesn't need a translation table.
            $table->unsignedTinyInteger('day_of_week');

            $table->time('open_time');
            $table->time('close_time');

            // Multiple intervals per day — sort_order picks display + iteration order.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Allow temporarily disabling an interval without deleting the row.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['activity_id', 'day_of_week', 'is_active'], 'act_schedules_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_schedules');
    }
};
