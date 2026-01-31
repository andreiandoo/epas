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
        if (Schema::hasTable('marketplace_refund_requests')) {
            return;
        }

        Schema::create('marketplace_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // REF-XXXXXX
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_organizer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_event_id')->nullable()->constrained()->nullOnDelete();

            // Request details
            $table->string('type'); // full_refund, partial_refund, ticket_cancellation
            $table->text('reason');
            $table->string('reason_category')->nullable(); // event_cancelled, personal_reason, duplicate_purchase, etc.
            $table->json('ticket_ids')->nullable(); // For partial/ticket cancellation
            $table->decimal('requested_amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('RON');

            // Status tracking
            $table->string('status')->default('pending'); // pending, approved, rejected, processing, completed, failed
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Processing details
            $table->string('refund_method')->nullable(); // original_payment, bank_transfer, store_credit
            $table->string('payment_processor')->nullable(); // stripe, netopia, etc.
            $table->string('payment_refund_id')->nullable(); // ID from payment processor
            $table->json('payment_response')->nullable(); // Response from payment processor
            $table->boolean('is_automatic')->default(false); // Was it processed automatically?

            // Financial impact
            $table->decimal('organizer_deduction', 10, 2)->nullable(); // Amount deducted from organizer
            $table->decimal('commission_refund', 10, 2)->nullable(); // Commission returned to organizer
            $table->decimal('fees_refund', 10, 2)->nullable(); // Processing fees

            // Timestamps
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('marketplace_admins')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('marketplace_admins')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_client_id', 'status']);
            $table->index('marketplace_organizer_id');
            $table->index('marketplace_customer_id');
            $table->index('order_id');
        });

        // Add refund-related columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('refund_status')->nullable()->after('status'); // none, partial, full
            $table->decimal('refunded_amount', 10, 2)->nullable()->after('refund_status');
            $table->timestamp('refunded_at')->nullable()->after('refunded_amount');
        });

        // Add cancellation status to tickets
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(false)->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->foreignId('refund_request_id')->nullable()->after('cancellation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['is_cancelled', 'cancelled_at', 'cancellation_reason', 'refund_request_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refunded_amount', 'refunded_at']);
        });

        Schema::dropIfExists('marketplace_refund_requests');
    }
};
