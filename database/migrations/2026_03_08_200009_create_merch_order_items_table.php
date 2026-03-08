<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merch_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merch_product_id')->constrained()->cascadeOnDelete();

            $table->integer('quantity')->default(1);
            $table->integer('unit_price_cents');
            $table->integer('total_price_cents');
            $table->string('currency', 3)->default('RON');

            // Selected variant (e.g. {"Size":"L","Color":"Black"})
            $table->json('variant')->nullable();

            // Fulfillment
            $table->string('fulfillment_status', 32)->default('pending')
                ->comment('pending|processing|shipped|delivered|cancelled');
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['merch_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_order_items');
    }
};
