<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the marketplace_payout_items table.
     * Each item represents an order included in a payout.
     */
    public function up(): void
    {
        Schema::create('marketplace_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained('marketplace_payouts')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Amount breakdown for this order
            $table->decimal('order_total', 10, 2);
            $table->decimal('tixello_fee', 10, 2);
            $table->decimal('marketplace_fee', 10, 2);
            $table->decimal('organizer_amount', 10, 2);

            // Refund info (if any)
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->boolean('is_refunded')->default(false);

            $table->timestamps();

            // Indexes
            $table->index('payout_id');
            $table->unique('order_id'); // Each order can only be in one payout
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_payout_items');
    }
};
