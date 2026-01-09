<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_wishlists')) {
            return;
        }

        Schema::create('shop_wishlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('name')->default('My Wishlist');
            $table->string('share_token', 64)->nullable()->unique(); // for sharing
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });

        if (Schema::hasTable('shop_wishlist_items')) {
            return;
        }

        Schema::create('shop_wishlist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wishlist_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('wishlist_id')->references('id')->on('shop_wishlists')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('shop_product_variants')->nullOnDelete();
            $table->unique(['wishlist_id', 'product_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_wishlist_items');
        Schema::dropIfExists('shop_wishlists');
    }
};
