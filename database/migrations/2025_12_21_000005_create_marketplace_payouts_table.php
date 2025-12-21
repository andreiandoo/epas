<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the marketplace_payouts table.
     * Payouts are periodic payments from marketplace to organizers.
     */
    public function up(): void
    {
        Schema::create('marketplace_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('marketplace_organizers')->cascadeOnDelete();

            // Payout identification
            $table->string('reference')->unique(); // PAY-XXXXXXXXXX
            $table->string('external_reference')->nullable(); // External payment system reference

            // Amounts
            $table->decimal('amount', 12, 2); // Net payout to organizer
            $table->string('currency')->default('RON');

            // Status workflow: pending -> processing -> completed/failed
            $table->string('status')->default('pending');

            // Payment method used
            $table->string('method'); // bank_transfer, paypal, stripe_connect
            $table->json('method_details')->nullable(); // Details used for this payout

            // Period this payout covers
            $table->date('period_start');
            $table->date('period_end');

            // Summary statistics
            $table->unsignedInteger('orders_count');
            $table->unsignedInteger('tickets_count')->default(0);
            $table->decimal('gross_revenue', 12, 2); // Total order value
            $table->decimal('tixello_fees', 12, 2); // Tixello's 1%
            $table->decimal('marketplace_fees', 12, 2); // Marketplace commission
            $table->decimal('refunds_total', 12, 2)->default(0); // Any refunds in period

            // Processing info
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable(); // User who processed
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Bank transfer details (if applicable)
            $table->string('bank_reference')->nullable();
            $table->timestamp('bank_confirmed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['organizer_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_payouts');
    }
};
