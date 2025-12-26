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
        Schema::create('marketplace_organizer_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_event_id')->nullable()->constrained()->nullOnDelete();

            // Code details
            $table->string('code', 50)->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            // Discount configuration
            $table->enum('type', ['fixed', 'percentage'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->enum('applies_to', ['all_events', 'specific_event', 'ticket_type'])->default('all_events');
            $table->foreignId('ticket_type_id')->nullable()->constrained('marketplace_ticket_types')->nullOnDelete();

            // Usage limits
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->integer('min_tickets')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_customer')->nullable();
            $table->integer('usage_count')->default(0);

            // Validity period
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'exhausted', 'expired'])->default('active');
            $table->boolean('is_public')->default(false);

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Ensure unique code per marketplace client
            $table->unique(['marketplace_client_id', 'code']);
        });

        // Track promo code usage
        Schema::create('marketplace_promo_code_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('marketplace_organizer_promo_codes')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email');
            $table->decimal('discount_applied', 10, 2);
            $table->decimal('order_total', 10, 2);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // Prevent duplicate usage tracking
            $table->unique(['promo_code_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_promo_code_usage');
        Schema::dropIfExists('marketplace_organizer_promo_codes');
    }
};
