<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('wa_schedules')) {
            return;
        }

        Schema::create('wa_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Message type for this scheduled job
            $table->enum('message_type', ['reminder_d7', 'reminder_d3', 'reminder_d1', 'promo', 'other'])
                ->index();

            // When to run this job
            $table->timestamp('run_at')->index();

            // Payload containing all data needed to send the message
            $table->json('payload')->comment('Contains order_ref, template_name, recipient, variables, etc.');

            // Job status
            $table->enum('status', ['scheduled', 'run', 'skipped', 'failed'])
                ->default('scheduled')
                ->index();

            // Correlation reference (order_ref, campaign_id, etc.)
            $table->string('correlation_ref')->nullable()->index();

            // Result after execution
            $table->json('result')->nullable()->comment('Message ID, error, etc.');

            // Execution timestamp
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            // Indexes for job processing
            $table->index(['status', 'run_at'], 'idx_status_run_at');
            $table->index(['tenant_id', 'message_type', 'status'], 'idx_tenant_type_status');

            // Idempotency: prevent duplicate reminder schedules
            $table->unique(['tenant_id', 'correlation_ref', 'message_type'], 'unique_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_schedules');
    }
};
