<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Transaction type
            $table->enum('type', ['earned', 'spent', 'expired', 'adjusted', 'refunded'])->default('earned');

            // Points details
            $table->integer('points'); // Positive for earned, negative for spent/expired
            $table->integer('balance_after'); // Balance after this transaction

            // Action/Source tracking
            $table->string('action_type', 50)->nullable(); // order, birthday, referral, signup, review, redemption, etc.
            $table->string('reference_type')->nullable(); // App\Models\Order, App\Models\Shop\ShopOrder, etc.
            $table->unsignedBigInteger('reference_id')->nullable();

            // Description (for display)
            $table->json('description'); // Translatable description
            $table->text('admin_note')->nullable(); // Internal note for adjustments

            // Metadata
            $table->json('metadata')->nullable(); // Additional data (order number, etc.)

            // Expiration (for earned points)
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            // For reversals/refunds
            $table->foreignId('reversed_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'action_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['expires_at', 'is_expired']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_transactions');
    }
};
