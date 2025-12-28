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
        Schema::create('affiliate_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
            $table->string('order_ref')->nullable(); // Reference to order (flexible, not FK)
            $table->decimal('amount', 10, 2)->default(0); // Order amount
            $table->decimal('commission_value', 10, 2)->default(0); // Calculated commission
            $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
            $table->enum('status', ['pending', 'approved', 'reversed'])->default('pending');
            $table->enum('attributed_by', ['link', 'coupon'])->nullable(); // How it was attributed
            $table->string('click_ref')->nullable(); // Reference to click record
            $table->json('meta')->nullable(); // Additional data
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('affiliate_id');
            $table->index('order_ref');
            $table->unique(['tenant_id', 'order_ref']); // Dedup: one conversion per order
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_conversions');
    }
};
