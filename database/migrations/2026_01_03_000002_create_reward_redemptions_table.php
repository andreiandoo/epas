<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reward_redemptions')) {
            return;
        }

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Points spent
            $table->integer('points_spent');
            $table->foreignId('points_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();

            // Snapshot of reward at time of redemption
            $table->json('reward_snapshot');

            // Voucher details (for voucher_code type rewards)
            $table->string('voucher_code')->nullable()->unique();
            $table->timestamp('voucher_expires_at')->nullable();
            $table->boolean('voucher_used')->default(false);
            $table->timestamp('voucher_used_at')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'active', 'used', 'expired', 'cancelled'])->default('active');

            // Usage reference (which order used this redemption)
            $table->string('reference_type')->nullable(); // e.g., App\Models\Order
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('discount_applied', 10, 2)->nullable(); // Actual discount applied

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['marketplace_client_id', 'customer_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['voucher_code']);
            $table->index(['status', 'voucher_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};
