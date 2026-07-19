<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A concrete flexible-payment agreement attached to an order at checkout.
 * Freezes a full snapshot of the chosen plan and the money breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('installment_agreements')) {
            return;
        }

        Schema::create('installment_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->nullable();
            $table->foreignId('tenant_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('installment_plan_id')->nullable();
            $table->foreignId('marketplace_customer_id')->nullable();

            $table->string('customer_email');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();

            $table->foreignId('event_id')->nullable();
            $table->foreignId('marketplace_event_id')->nullable();
            $table->timestamp('event_start_date')->nullable();

            $table->enum('plan_type', ['installments', 'bnpl_single'])->default('installments');
            $table->string('currency', 3)->default('RON');

            // Money snapshot (all in bani / cents).
            $table->unsignedBigInteger('base_total_cents');       // direct price
            $table->unsignedBigInteger('surcharge_cents')->default(0);
            $table->unsignedBigInteger('customer_total_cents');   // base + surcharge
            $table->unsignedBigInteger('platform_fee_cents')->default(0); // Tixello, collected from marketplace
            $table->decimal('platform_fee_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('down_payment_cents')->default(0);
            $table->unsignedBigInteger('financed_cents')->default(0);

            $table->integer('number_of_installments')->default(1);
            $table->integer('paid_installments_count')->default(0);
            $table->timestamp('next_due_at')->nullable();

            $table->enum('status', [
                'pending', 'active', 'completed', 'defaulted', 'cancelled', 'refunded',
            ])->default('pending');
            $table->string('ticket_issuance_policy')->default('issue_invalid_until_paid');

            // Auto-debit / mandate.
            $table->string('provider')->nullable();
            $table->foreignId('payment_method_id')->nullable();  // marketplace_customer_payment_methods
            $table->string('mandate_reference')->nullable();
            $table->boolean('auto_debit_enabled')->default(false);

            $table->json('plan_snapshot')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('next_due_at');
            $table->index('order_id');
            $table->index(['marketplace_client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_agreements');
    }
};
