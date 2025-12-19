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
        if (Schema::hasTable('points_transactions')) {
            return; // Table already exists, skip
        }

        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points'); // Can be positive (earned) or negative (spent)
            $table->string('type', 30); // earned, spent, bonus, adjustment, referral, expired
            $table->string('description')->nullable();
            $table->unsignedInteger('balance_after'); // Balance after this transaction
            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_transactions');
    }
};
