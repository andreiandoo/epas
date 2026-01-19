<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_referrals')) {
            return;
        }

        Schema::create('marketplace_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('referral_code_id')->constrained('marketplace_referral_codes')->onDelete('cascade');
            $table->foreignId('referrer_id')->constrained('marketplace_customers')->onDelete('cascade');
            $table->foreignId('referred_id')->nullable()->constrained('marketplace_customers')->onDelete('set null');

            $table->string('status', 20)->default('pending'); // pending, registered, converted, expired
            $table->string('ip_address', 45)->nullable(); // Track click IP
            $table->string('user_agent')->nullable(); // Track device/browser
            $table->string('source', 50)->nullable(); // Where the link was shared (email, facebook, etc.)

            $table->timestamp('clicked_at')->nullable(); // When they clicked the link
            $table->timestamp('registered_at')->nullable(); // When they registered
            $table->timestamp('converted_at')->nullable(); // When they made first purchase
            $table->timestamp('expires_at')->nullable(); // When referral attribution expires

            // Order/conversion details
            $table->foreignId('order_id')->nullable(); // First qualifying order
            $table->decimal('order_value', 12, 2)->nullable();
            $table->integer('points_awarded')->default(0);
            $table->foreignId('points_transaction_id')->nullable(); // Link to points transaction

            $table->timestamps();

            // Indexes for queries
            $table->index(['referral_code_id', 'status'], 'mr_code_status_idx');
            $table->index(['referrer_id', 'status'], 'mr_referrer_status_idx');
            $table->index(['referred_id'], 'mr_referred_idx');
            $table->index(['marketplace_client_id', 'created_at'], 'mr_client_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_referrals');
    }
};
