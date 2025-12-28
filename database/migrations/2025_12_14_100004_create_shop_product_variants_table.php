<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('sku');
            $table->integer('price_cents')->nullable(); // override product price
            $table->integer('sale_price_cents')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->integer('weight_grams')->nullable(); // override
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->index(['product_id', 'is_active']);
        });

        // Variant-AttributeValue pivot (which attribute values define this variant)
        Schema::create('shop_variant_attribute_value', function (Blueprint $table) {
            $table->uuid('variant_id');
            $table->uuid('attribute_value_id');

            $table->foreign('variant_id')->references('id')->on('shop_product_variants')->cascadeOnDelete();
            $table->foreign('attribute_value_id')->references('id')->on('shop_attribute_values')->cascadeOnDelete();
            $table->primary(['variant_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_variant_attribute_value');
        Schema::dropIfExists('shop_product_variants');
    }
};
