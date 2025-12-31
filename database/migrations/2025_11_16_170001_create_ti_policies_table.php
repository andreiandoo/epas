<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ticket Insurance - Policies
     * Stores issued insurance policies
     */
    public function up(): void
    {
        Schema::create('ti_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Order and ticket references
            $table->string('order_ref')->index()->comment('Reference to order');
            $table->string('ticket_ref')->nullable()->index()->comment('Reference to ticket (null if per_order)');

            // Insurance provider
            $table->string('insurer')->comment('Provider adapter key');

            // Premium information
            $table->decimal('premium_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('tax_amount', 10, 2)->nullable()->comment('Tax on premium');

            // Policy status
            $table->enum('status', [
                'pending',      // Awaiting issuance
                'issued',       // Policy issued successfully
                'voided',       // Policy voided/cancelled
                'refunded',     // Policy refunded
                'error'         // Issuance failed
            ])->default('pending')->index();

            // Policy details from provider
            $table->string('policy_number')->nullable()->unique()->comment('Provider policy number');
            $table->text('policy_doc_url')->nullable()->comment('URL to policy document');
            $table->string('policy_doc_path')->nullable()->comment('Stored PDF path');

            // Provider communication
            $table->json('provider_payload')->nullable()->comment('Full provider response');
            $table->text('error_message')->nullable()->comment('Error details if status=error');

            // Refund tracking
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable()->comment('Additional data: user info, event details, etc.');

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['order_ref', 'ticket_ref']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ti_policies');
    }
};
