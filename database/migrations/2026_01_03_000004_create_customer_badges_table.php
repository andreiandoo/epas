<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // XP awarded
            $table->integer('xp_awarded')->default(0);
            $table->foreignId('experience_transaction_id')->nullable(); // Will add FK after experience_transactions table exists

            // Points awarded
            $table->integer('points_awarded')->default(0);
            $table->foreignId('points_transaction_id')->nullable()->constrained('points_transactions')->nullOnDelete();

            // Context of earning
            $table->json('earned_context')->nullable(); // Additional data about how/why earned
            $table->string('reference_type')->nullable(); // e.g., App\Models\Event
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamp('earned_at');
            $table->timestamps();

            // Indexes
            $table->unique(['badge_id', 'customer_id']); // Each customer can only earn each badge once
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['marketplace_client_id', 'customer_id']);
            $table->index(['customer_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_badges');
    }
};
