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
        if (Schema::hasTable('tenant_webhooks')) {
            return;
        }

        Schema::create('tenant_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('name'); // Human-readable name
            $table->string('url'); // Webhook endpoint URL
            $table->json('events'); // Array of events to trigger on (e.g., ['order.created', 'payment.captured'])
            $table->string('secret'); // Webhook signing secret
            $table->enum('status', ['active', 'paused', 'disabled'])->default('active');
            $table->json('headers')->nullable(); // Optional custom headers
            $table->integer('timeout')->default(30); // Timeout in seconds
            $table->integer('retry_limit')->default(3); // Number of retries
            $table->boolean('verify_ssl')->default(true); // SSL verification
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        if (Schema::hasTable('tenant_webhook_deliveries')) {
            return;
        }

        Schema::create('tenant_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('tenant_webhooks')->onDelete('cascade');
            $table->string('tenant_id')->index();
            $table->string('event_type'); // e.g., 'order.created'
            $table->json('payload'); // Event payload
            $table->integer('attempt')->default(1);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->integer('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
            $table->index(['tenant_id', 'event_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_webhook_deliveries');
        Schema::dropIfExists('tenant_webhooks');
    }
};
