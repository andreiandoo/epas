<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock alerts - notify when product is back in stock
        Schema::create('shop_stock_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('email');
            $table->enum('status', ['pending', 'notified', 'cancelled'])->default('pending');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('shop_product_variants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['product_id', 'variant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_stock_alerts');
    }
};
