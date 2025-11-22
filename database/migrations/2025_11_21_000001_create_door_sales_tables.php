<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Door sales transactions
        Schema::create('door_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Staff member
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null'); // Link to order
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('payment_processing_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('payment_method'); // card_tap, apple_pay, google_pay
            $table->string('payment_gateway')->default('stripe');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('gateway_payment_intent_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->text('failure_reason')->nullable();
            $table->string('device_id')->nullable(); // Device identifier
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        // Door sale line items
        Schema::create('door_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('door_sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_type_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->index('door_sale_id');
        });

        // Platform fee tracking for revenue
        Schema::create('door_sale_platform_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('door_sale_id')->constrained()->onDelete('cascade');
            $table->decimal('transaction_amount', 10, 2);
            $table->decimal('fee_percentage', 5, 2);
            $table->decimal('fee_amount', 10, 2);
            $table->boolean('settled')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'settled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('door_sale_platform_fees');
        Schema::dropIfExists('door_sale_items');
        Schema::dropIfExists('door_sales');
    }
};
