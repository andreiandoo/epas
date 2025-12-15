<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();

            // Request details
            $table->string('reference')->unique(); // WD-XXXXXXXX
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('RON');

            // Status workflow: pending -> processing -> completed/rejected
            $table->enum('status', ['pending', 'processing', 'completed', 'rejected', 'cancelled'])->default('pending');
            $table->text('rejection_reason')->nullable();

            // Payment details (snapshot at time of request)
            $table->string('payment_method');
            $table->json('payment_details');

            // Processing info
            $table->string('transaction_id')->nullable(); // External payment reference
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();

            // Audit
            $table->ipAddress('requested_ip')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['affiliate_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_withdrawals');
    }
};
