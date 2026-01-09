<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persistent carts for abandoned cart recovery
        if (Schema::hasTable('shop_carts')) {
            return;
        }

        Schema::create('shop_carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('email')->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('coupon_code')->nullable();

            // Recovery tracking
            $table->enum('status', ['active', 'converted', 'abandoned', 'expired'])->default('active');
            $table->integer('recovery_emails_sent')->default(0);
            $table->timestamp('last_recovery_email_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->uuid('converted_order_id')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'session_id']);
            $table->index(['tenant_id', 'email']);
        });

        if (Schema::hasTable('shop_cart_items')) {
            return;
        }

        Schema::create('shop_cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cart_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->integer('quantity');
            $table->integer('unit_price_cents');
            $table->json('meta')->nullable(); // store any additional info
            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('shop_carts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('shop_product_variants')->nullOnDelete();
            $table->index('cart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_cart_items');
        Schema::dropIfExists('shop_carts');
    }
};
