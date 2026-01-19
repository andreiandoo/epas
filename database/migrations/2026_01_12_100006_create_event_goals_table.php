<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Goal type and metric
            $table->enum('type', ['revenue', 'tickets', 'visitors', 'conversion_rate'])->default('revenue');
            $table->string('name')->nullable(); // Custom goal name

            // Target values
            $table->unsignedBigInteger('target_value'); // For revenue: cents, for others: count
            $table->unsignedBigInteger('current_value')->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);

            // Deadline
            $table->date('deadline')->nullable(); // Optional deadline for goal

            // Alert thresholds (percentages)
            $table->json('alert_thresholds')->nullable(); // [50, 75, 90, 100]
            $table->json('alerts_sent')->nullable(); // Track which alerts have been sent

            // Notification settings
            $table->boolean('email_alerts')->default(true);
            $table->boolean('in_app_alerts')->default(true);
            $table->string('alert_email')->nullable(); // Custom email for alerts

            // Status
            $table->enum('status', ['active', 'achieved', 'missed', 'cancelled'])->default('active');
            $table->timestamp('achieved_at')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'type']);
            $table->index(['event_id', 'status']);
        });

        // Add report settings to events or create separate table
        Schema::create('event_report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->nullable()->constrained()->nullOnDelete();

            // Schedule type
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->tinyInteger('day_of_week')->nullable(); // 0-6 for weekly
            $table->tinyInteger('day_of_month')->nullable(); // 1-31 for monthly
            $table->time('send_at')->default('09:00:00');
            $table->string('timezone')->default('Europe/Bucharest');

            // Recipients
            $table->json('recipients'); // Array of email addresses

            // Report content settings
            $table->json('sections')->nullable(); // ['overview', 'chart', 'traffic', 'milestones', 'goals']
            $table->enum('format', ['email', 'pdf', 'csv'])->default('email');
            $table->boolean('include_comparison')->default(true); // vs previous period

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'next_send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_report_schedules');
        Schema::dropIfExists('event_goals');
    }
};
