<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice — per-operator weekly working hours.
 *
 * One row per (operator, weekday, interval). Multiple intervals per day are
 * allowed (e.g. 09:00-13:00 and 14:00-18:00). day_of_week: 0=Sunday..6=Saturday
 * (Carbon convention). The marketplace-wide default schedule and holidays live
 * in the microservice settings + chat_holidays.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_operator_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_admin_id')
                ->constrained('marketplace_admins')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week'); // 0=Sun .. 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['marketplace_admin_id', 'day_of_week'], 'chat_op_sched_admin_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_operator_schedules');
    }
};
