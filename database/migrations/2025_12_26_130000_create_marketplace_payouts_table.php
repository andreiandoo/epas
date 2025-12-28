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
        // Marketplace Payouts - Organizer withdrawal requests
        Schema::create('marketplace_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->constrained()->cascadeOnDelete();

            // Payout details
            $table->string('reference')->unique(); // PAY-XXXXX
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('RON');

            // Period covered
            $table->date('period_start');
            $table->date('period_end');

            // Breakdown
            $table->decimal('gross_amount', 12, 2); // Total from sales
            $table->decimal('commission_amount', 12, 2); // Marketplace commission
            $table->decimal('fees_amount', 12, 2)->default(0); // Any additional fees
            $table->decimal('adjustments_amount', 12, 2)->default(0); // Refunds, chargebacks, etc.
            $table->text('adjustments_note')->nullable();

            // Status workflow: pending -> approved -> processing -> completed
            //                  pending -> rejected
            $table->string('status')->default('pending');
            // pending = requested by organizer
            // approved = approved by marketplace admin
            // processing = payment being processed
            // completed = payment sent
            // rejected = request rejected
            // cancelled = cancelled by organizer

            // Payout method details (from organizer's payout_details at time of request)
            $table->json('payout_method')->nullable();

            // Admin actions
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Rejection
            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            // Payment confirmation
            $table->string('payment_reference')->nullable(); // Bank transfer reference
            $table->string('payment_method')->nullable(); // bank_transfer, paypal, etc.
            $table->text('payment_notes')->nullable();

            // Internal notes
            $table->text('admin_notes')->nullable();
            $table->text('organizer_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_organizer_id', 'status']);
            $table->index(['marketplace_client_id', 'status']);
            $table->index('reference');
        });

        // Marketplace Transactions - Ledger of all financial movements
        Schema::create('marketplace_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->constrained()->cascadeOnDelete();

            // Transaction type
            $table->string('type');
            // Types:
            // sale = ticket sale (credit)
            // commission = commission deducted (debit)
            // refund = order refund (debit)
            // chargeback = payment chargeback (debit)
            // adjustment = manual adjustment (credit/debit)
            // payout = payout to organizer (debit)
            // payout_reversal = payout failed/reversed (credit)

            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('RON');

            // Running balance after this transaction
            $table->decimal('balance_after', 12, 2);

            // Related entities
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketplace_payout_id')->nullable()->constrained()->nullOnDelete();

            $table->string('description');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['marketplace_organizer_id', 'created_at'], 'mkt_txn_org_created_idx');
            $table->index('type');
        });

        // Add balance fields to organizers
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->decimal('available_balance', 12, 2)->default(0)->after('total_revenue');
            $table->decimal('pending_balance', 12, 2)->default(0)->after('available_balance');
            $table->decimal('total_paid_out', 12, 2)->default(0)->after('pending_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'pending_balance', 'total_paid_out']);
        });

        Schema::dropIfExists('marketplace_transactions');
        Schema::dropIfExists('marketplace_payouts');
    }
};
