<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_points')) {
            return;
        }

        Schema::create('customer_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Point balances
            $table->integer('total_earned')->default(0); // Lifetime earned
            $table->integer('total_spent')->default(0); // Lifetime spent
            $table->integer('total_expired')->default(0); // Lifetime expired
            $table->integer('current_balance')->default(0); // Available balance
            $table->integer('pending_points')->default(0); // Points waiting to be credited (e.g., after order completion)

            // Tier tracking
            $table->string('current_tier')->nullable();
            $table->integer('tier_points')->default(0); // Points accumulated for tier calculation
            $table->timestamp('tier_updated_at')->nullable();

            // Activity tracking
            $table->timestamp('last_earned_at')->nullable();
            $table->timestamp('last_spent_at')->nullable();
            $table->timestamp('points_expire_at')->nullable(); // Next expiration date

            // Referral tracking
            $table->string('referral_code', 20)->nullable()->unique();
            $table->integer('referral_count')->default(0);
            $table->integer('referral_points_earned')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'current_balance']);
            $table->index(['tenant_id', 'current_tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_points');
    }
};
