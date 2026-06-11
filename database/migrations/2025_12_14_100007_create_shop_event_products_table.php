<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_event_products')) {
            return;
        }

        Schema::create('shop_event_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->uuid('product_id');
            $table->enum('association_type', ['upsell', 'bundle'])->default('upsell');
            $table->foreignId('ticket_type_id')->nullable()->constrained()->cascadeOnDelete(); // for bundles
            $table->integer('quantity_included')->default(1); // for bundles
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('shop_products')->cascadeOnDelete();
            $table->unique(['event_id', 'product_id', 'association_type', 'ticket_type_id'], 'shop_event_products_unique');
            $table->index(['event_id', 'association_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_event_products');
    }
};
