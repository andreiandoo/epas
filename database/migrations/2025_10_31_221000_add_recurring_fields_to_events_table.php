<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('recurring_frequency')->nullable(); // 'weekly' | 'monthly_nth'
            $table->date('recurring_start_date')->nullable();
            $table->time('recurring_start_time')->nullable();
            $table->time('recurring_door_time')->nullable();
            $table->time('recurring_end_time')->nullable();
            $table->unsignedTinyInteger('recurring_weekday')->nullable(); // 1=Mon ... 7=Sun
            $table->tinyInteger('recurring_week_of_month')->nullable(); // 1..4, -1=last
            $table->unsignedSmallInteger('recurring_count')->nullable(); // nr. de ocurențe (opțional)
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'recurring_frequency',
                'recurring_start_date',
                'recurring_start_time',
                'recurring_door_time',
                'recurring_end_time',
                'recurring_weekday',
                'recurring_week_of_month',
                'recurring_count',
            ]);
        });
    }
};
