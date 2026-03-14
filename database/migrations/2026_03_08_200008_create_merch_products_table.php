<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merch_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->json('name')->comment('Translatable product name');
            $table->string('slug')->index();
            $table->json('description')->nullable()->comment('Translatable description');
            $table->string('sku')->nullable();

            // Pricing
            $table->integer('price_cents');
            $table->string('currency', 3)->default('RON');
            $table->integer('compare_at_price_cents')->nullable()->comment('Original price for showing discount');

            // Stock
            $table->integer('stock_quantity')->default(0);
            $table->string('stock_status', 32)->default('in_stock')->comment('in_stock|out_of_stock|preorder');
            $table->boolean('track_stock')->default(true);

            // Categorization
            $table->string('category')->nullable()->comment('t-shirts|vinyl|posters|cds|accessories|digital|other');

            // Variants (sizes, colors, etc.)
            $table->json('variants')->nullable()->comment('[{name:"Size",options:["S","M","L","XL"]},{name:"Color",options:["Black","White"]}]');
            $table->json('variant_stock')->nullable()->comment('{"S-Black":10,"M-Black":5,...}');

            // Media
            $table->json('images')->nullable()->comment('Array of image URLs');

            // Shipping
            $table->integer('weight_grams')->nullable();
            $table->boolean('is_digital')->default(false);
            $table->string('digital_file_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_products');
    }
};
