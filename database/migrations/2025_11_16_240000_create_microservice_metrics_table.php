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
        Schema::create('microservice_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('microservice_slug')->index();
            $table->string('action'); // e.g., 'send_message', 'submit_invoice', 'create_policy'
            $table->enum('status', ['success', 'failure'])->default('success');
            $table->decimal('cost', 10, 4)->default(0); // Cost in EUR
            $table->integer('quantity')->default(1); // Number of items (messages, invoices, etc.)
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'microservice_slug', 'created_at'], 'idx_metrics_tenant_ms_time');
            $table->index(['microservice_slug', 'created_at'], 'idx_metrics_ms_time');
        });

        Schema::create('microservice_usage_summary', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('microservice_slug')->index();
            $table->date('date');
            $table->integer('total_calls')->default(0);
            $table->integer('successful_calls')->default(0);
            $table->integer('failed_calls')->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->integer('total_quantity')->default(0);
            $table->json('breakdown')->nullable(); // Breakdown by action
            $table->timestamps();

            $table->unique(['tenant_id', 'microservice_slug', 'date'], 'uniq_usage_tenant_ms_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('microservice_usage_summary');
        Schema::dropIfExists('microservice_metrics');
    }
};
