<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_products')) {
            return;
        }

        Schema::create('shop_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('category_id')->nullable();
            $table->json('title'); // translatable
            $table->string('slug');
            $table->json('description')->nullable(); // translatable
            $table->json('short_description')->nullable(); // translatable
            $table->enum('type', ['physical', 'digital'])->default('physical');
            $table->string('sku');

            // Pricing
            $table->integer('price_cents');
            $table->integer('sale_price_cents')->nullable();
            $table->integer('cost_cents')->nullable(); // for profit tracking
            $table->string('currency', 3)->default('RON');

            // Tax settings
            $table->decimal('tax_rate', 5, 2)->nullable(); // null = use store default
            $table->enum('tax_mode', ['included', 'added_on_top'])->nullable(); // null = use store default

            // Inventory
            $table->integer('stock_quantity')->nullable(); // null = unlimited
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('track_inventory')->default(true);

            // Physical product attributes
            $table->integer('weight_grams')->nullable();
            $table->json('dimensions')->nullable(); // {length, width, height, unit}

            // Media
            $table->string('image_url')->nullable();
            $table->json('gallery')->nullable(); // array of image URLs

            // Digital product attributes
            $table->string('digital_file_url')->nullable();
            $table->integer('digital_download_limit')->nullable();
            $table->integer('digital_download_expiry_days')->nullable();

            // Status & visibility
            $table->enum('status', ['draft', 'active', 'out_of_stock', 'discontinued'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_visible')->default(true);

            // Reviews
            $table->boolean('reviews_enabled')->default(true);
            $table->decimal('average_rating', 2, 1)->nullable();
            $table->integer('review_count')->default(0);

            // Related products
            $table->json('related_product_ids')->nullable(); // manual related products

            // Meta & SEO
            $table->json('meta')->nullable();
            $table->json('seo')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('shop_categories')->nullOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'is_featured']);
            $table->index(['tenant_id', 'type']);
        });

        // Product-Attribute pivot (which attributes apply to this product)
        if (Schema::hasTable('shop_product_attribute')) {
            return;
        }

        Schema::create('shop_product_attribute', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('attribute_id');

            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('shop_attributes')->cascadeOnDelete();
            $table->primary(['product_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product_attribute');
        Schema::dropIfExists('shop_products');
    }
};
