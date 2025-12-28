<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('product_id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->uuid('order_item_id')->nullable(); // verified purchase
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->text('admin_response')->nullable();
            $table->timestamp('admin_responded_at')->nullable();
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->foreign('order_item_id')->references('id')->on('shop_order_items')->nullOnDelete();
            $table->unique(['product_id', 'customer_id']); // one review per customer per product
            $table->index(['tenant_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_reviews');
    }
};
