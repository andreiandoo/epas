<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The schedule rows for an agreement. sequence 0 = down payment,
 * 1..N = installments (for BNPL there is a single sequence-1 row).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('installment_payments')) {
            return;
        }

        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_agreement_id');
            $table->integer('sequence');                 // 0 = down payment
            $table->timestamp('due_date');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('principal_cents')->default(0);
            $table->unsignedBigInteger('fee_cents')->default(0);

            $table->enum('status', [
                'scheduled', 'due', 'processing', 'paid', 'failed',
                'retrying', 'action_required', 'waived', 'refunded', 'cancelled',
            ])->default('scheduled');

            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_amount_cents')->nullable();
            $table->string('payment_reference')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->integer('dunning_stage')->default(0);
            $table->string('pay_link_token')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['installment_agreement_id', 'sequence']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_payments');
    }
};
