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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Code details
            $table->string('code', 50)->unique(); // The actual promo code
            $table->string('name')->nullable(); // Internal name/description
            $table->text('description')->nullable(); // Public description shown to customers

            // Discount configuration
            $table->enum('type', ['fixed', 'percentage']); // Fixed amount or percentage
            $table->decimal('value', 10, 2); // The discount value (amount or percentage)

            // Application scope
            $table->enum('applies_to', ['cart', 'event', 'ticket_type'])->default('cart');
            $table->uuid('event_id')->nullable(); // If applies_to = 'event'
            $table->uuid('ticket_type_id')->nullable(); // If applies_to = 'ticket_type'

            // Conditions
            $table->decimal('min_purchase_amount', 10, 2)->nullable(); // Minimum cart value required
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // Maximum discount cap (for percentage)
            $table->integer('min_tickets')->nullable(); // Minimum number of tickets required

            // Usage limits
            $table->integer('usage_limit')->nullable(); // Total times code can be used (null = unlimited)
            $table->integer('usage_limit_per_customer')->nullable(); // Per customer limit
            $table->integer('usage_count')->default(0); // Current usage count

            // Validity period
            $table->timestamp('starts_at')->nullable(); // When code becomes active
            $table->timestamp('expires_at')->nullable(); // When code expires

            // Status
            $table->enum('status', ['active', 'inactive', 'expired', 'depleted'])->default('active');

            // Metadata
            $table->boolean('is_public')->default(true); // Public vs private codes
            $table->json('metadata')->nullable(); // Additional custom data

            // Tracking
            $table->uuid('created_by')->nullable(); // User who created the code
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['code', 'status']);
            $table->index(['tenant_id', 'applies_to']);
            $table->index(['expires_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
