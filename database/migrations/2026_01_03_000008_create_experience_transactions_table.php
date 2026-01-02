<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // XP change
            $table->integer('xp'); // Can be positive or negative
            $table->bigInteger('xp_balance_after');
            $table->integer('level_after');

            // Level up tracking
            $table->boolean('triggered_level_up')->default(false);
            $table->integer('old_level')->nullable();
            $table->integer('new_level')->nullable();
            $table->string('old_level_group')->nullable();
            $table->string('new_level_group')->nullable();

            // Action that triggered this
            $table->string('action_type'); // ticket_purchase, event_checkin, badge_earned, admin_adjustment, etc.

            // Reference to what triggered the XP
            $table->string('reference_type')->nullable(); // e.g., App\Models\Order, App\Models\Badge
            $table->unsignedBigInteger('reference_id')->nullable();

            // Description (translatable)
            $table->json('description')->nullable();

            // Admin tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes (shortened names to avoid MySQL 64-char limit)
            $table->index(['tenant_id', 'customer_id', 'created_at'], 'exp_trans_tenant_cust_created_idx');
            $table->index(['marketplace_client_id', 'customer_id', 'created_at'], 'exp_trans_mp_cust_created_idx');
            $table->index(['customer_id', 'action_type'], 'exp_trans_cust_action_idx');
            $table->index(['triggered_level_up'], 'exp_trans_level_up_idx');
        });

        // Add foreign key to customer_badges for experience_transaction_id
        Schema::table('customer_badges', function (Blueprint $table) {
            $table->foreign('experience_transaction_id')
                ->references('id')
                ->on('experience_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_badges', function (Blueprint $table) {
            $table->dropForeign(['experience_transaction_id']);
        });

        Schema::dropIfExists('experience_transactions');
    }
};
