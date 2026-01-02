<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_experience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // XP tracking
            $table->bigInteger('total_xp')->default(0);
            $table->integer('current_level')->default(1);
            $table->integer('xp_to_next_level')->default(100);
            $table->integer('xp_in_current_level')->default(0);

            // Level group (e.g., "RockStar")
            $table->string('current_level_group')->nullable();

            // Stats (cached for quick access)
            $table->integer('total_badges_earned')->default(0);
            $table->integer('events_attended')->default(0);
            $table->integer('reviews_submitted')->default(0);
            $table->integer('referrals_converted')->default(0);

            // Timestamps
            $table->timestamp('last_xp_earned_at')->nullable();
            $table->timestamp('last_level_up_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['tenant_id', 'customer_id']);
            $table->unique(['marketplace_client_id', 'customer_id']);
            $table->index(['tenant_id', 'current_level']);
            $table->index(['marketplace_client_id', 'current_level']);
            $table->index(['current_level_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_experience');
    }
};
