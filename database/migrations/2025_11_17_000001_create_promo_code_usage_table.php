<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promo_code_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id');
            $table->foreignId('tenant_id');

            // Order information
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id')->nullable(); // Customer who used the code
            $table->string('customer_email')->nullable();

            // Discount calculation
            $table->decimal('original_amount', 10, 2); // Original order total
            $table->decimal('discount_amount', 10, 2); // Actual discount applied
            $table->decimal('final_amount', 10, 2); // Final amount after discount

            // Details
            $table->json('applied_to')->nullable(); // Which items the discount was applied to
            $table->text('notes')->nullable();

            // Tracking
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('used_at');
            $table->timestamps();

            // Indexes
            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['promo_code_id', 'used_at']);
            $table->index(['tenant_id', 'used_at']);
            $table->index(['customer_id', 'promo_code_id']);
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_code_usage');
    }
};
