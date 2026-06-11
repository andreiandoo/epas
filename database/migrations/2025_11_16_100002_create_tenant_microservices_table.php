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
        if (Schema::hasTable('tenant_microservices')) {
            return;
        }

        Schema::create('tenant_microservices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('microservice_id')->constrained('microservices')->onDelete('cascade');

            // Activation status
            $table->enum('status', ['active', 'suspended', 'cancelled', 'trial'])->default('active')->index();

            // Activation and expiration dates
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // Microservice-specific settings (override defaults)
            $table->json('settings')->nullable();

            // Usage tracking (for usage-based pricing)
            $table->json('usage_stats')->nullable()->comment('Messages sent, invoices processed, etc.');

            // Billing
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->timestamp('last_billed_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unique constraint: one activation per tenant per microservice
            $table->unique(['tenant_id', 'microservice_id'], 'unique_tenant_microservice');

            // Indexes for common queries
            $table->index(['tenant_id', 'status'], 'idx_tenant_status');
            $table->index(['status', 'next_billing_at'], 'idx_billing_queue');
            $table->index(['status', 'expires_at'], 'idx_expiration_queue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_microservices');
    }
};
