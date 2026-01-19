<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_orders')) {
            return;
        }

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('order_number'); // e.g., "SH-2024-00001"
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            // Order status
            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'delivered',
                'completed',
                'cancelled',
                'refunded'
            ])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('fulfillment_status', ['unfulfilled', 'partial', 'fulfilled'])->default('unfulfilled');

            // Amounts
            $table->integer('subtotal_cents');
            $table->integer('discount_cents')->default(0);
            $table->integer('shipping_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents');
            $table->string('currency', 3)->default('RON');

            // Coupon
            $table->string('coupon_code')->nullable();
            $table->integer('coupon_discount_cents')->nullable();

            // Addresses
            $table->json('billing_address');
            $table->json('shipping_address')->nullable();

            // Shipping
            $table->string('shipping_method')->nullable();
            $table->string('shipping_provider')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();

            // Notes
            $table->text('notes')->nullable(); // customer notes
            $table->text('internal_notes')->nullable(); // admin notes

            // Event association (if purchased during event checkout)
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_order_id')->nullable(); // link to main ticket order

            // Payment
            $table->string('payment_method')->nullable();
            $table->string('payment_transaction_id')->nullable();

            // Meta
            $table->json('meta')->nullable();

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event_id']);
        });

        if (Schema::hasTable('shop_order_items')) {
            return;
        }

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->integer('quantity');
            $table->integer('unit_price_cents');
            $table->integer('total_cents');
            $table->json('product_snapshot'); // store product details at time of purchase
            $table->json('variant_snapshot')->nullable();

            // Bundle info
            $table->boolean('is_bundled')->default(false); // part of ticket bundle
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('shop_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('shop_products')->restrictOnDelete();
            $table->foreign('variant_id')->references('id')->on('shop_product_variants')->nullOnDelete();
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
    }
};
