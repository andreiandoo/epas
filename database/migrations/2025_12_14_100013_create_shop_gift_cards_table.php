<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_gift_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->integer('initial_balance_cents');
            $table->integer('current_balance_cents');
            $table->string('currency', 3)->default('RON');
            $table->enum('status', ['active', 'depleted', 'expired', 'disabled'])->default('active');

            // Purchaser info
            $table->foreignId('purchaser_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->uuid('purchase_order_id')->nullable();
            $table->string('purchaser_email')->nullable();

            // Recipient info
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();

            // Validity
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('shop_gift_card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gift_card_id');
            $table->enum('type', ['credit', 'debit', 'refund']);
            $table->integer('amount_cents');
            $table->integer('balance_after_cents');
            $table->uuid('order_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('gift_card_id')->references('id')->on('shop_gift_cards')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('shop_orders')->nullOnDelete();
            $table->index('gift_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_gift_card_transactions');
        Schema::dropIfExists('shop_gift_cards');
    }
};
