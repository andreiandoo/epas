<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_shipping_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('countries'); // array of country codes
            $table->json('regions')->nullable(); // array of state/region codes
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::create('shop_shipping_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('zone_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('provider')->nullable(); // e.g., "dhl", "fedex", "local", "pickup"
            $table->enum('calculation_type', ['flat', 'weight_based', 'price_based', 'free'])->default('flat');
            $table->integer('cost_cents')->default(0); // for flat rate
            $table->integer('cost_per_kg_cents')->nullable(); // for weight-based
            $table->integer('min_order_cents')->nullable(); // minimum order for free shipping
            $table->integer('max_order_cents')->nullable(); // for price-based tiers
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('shop_shipping_zones')->cascadeOnDelete();
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_shipping_methods');
        Schema::dropIfExists('shop_shipping_zones');
    }
};
