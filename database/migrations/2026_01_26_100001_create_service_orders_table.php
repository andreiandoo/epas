<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique(); // e.g., "SVC-2024-00001"

            // Relationships
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_organizer_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_event_id')->nullable()->constrained()->onDelete('set null');

            // Service type: featuring, email, tracking, campaign
            $table->enum('service_type', ['featuring', 'email', 'tracking', 'campaign']);

            // Service configuration (JSON)
            $table->json('config')->nullable();

            // Pricing
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('RON');

            // Payment
            $table->enum('payment_method', ['card', 'transfer'])->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable(); // Transaction ID from Netopia

            // Order status
            $table->enum('status', [
                'draft',           // Order created but not submitted
                'pending_payment', // Awaiting payment
                'processing',      // Payment received, service being set up
                'active',          // Service is active
                'completed',       // Service period ended
                'cancelled',       // Cancelled by organizer or admin
                'refunded'         // Payment refunded
            ])->default('draft');

            // For email campaigns
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->integer('sent_count')->nullable();
            $table->string('brevo_campaign_id')->nullable();

            // Service period
            $table->date('service_start_date')->nullable();
            $table->date('service_end_date')->nullable();

            // Admin management
            $table->text('admin_notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['marketplace_client_id', 'status']);
            $table->index(['marketplace_organizer_id', 'status']);
            $table->index(['service_type', 'status']);
            $table->index(['payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
