<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared payment-link primitive for the Flexible Payments microservice.
 *
 * One table powers three flows:
 *   - installment / BNPL: a secure link to pay or 3DS-authenticate a due charge
 *   - delegated_pay: "someone else pays" — buyer reserves, a third party pays
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_links')) {
            return;
        }

        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->enum('purpose', ['installment', 'bnpl', 'delegated_pay']);

            $table->foreignId('marketplace_client_id')->nullable();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('installment_payment_id')->nullable();

            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('RON');

            $table->enum('status', ['active', 'paid', 'expired', 'cancelled'])->default('active');
            $table->timestamp('expires_at')->nullable();

            // Delegated pay: who is being asked to pay + who initiated.
            $table->string('payer_email')->nullable();
            $table->string('payer_name')->nullable();
            $table->foreignId('created_by_customer_id')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('expires_at');
            $table->index('order_id');
            $table->index(['purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
