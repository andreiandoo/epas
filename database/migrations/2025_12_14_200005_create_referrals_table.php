<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referrals')) {
            return;
        }

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Referrer (the customer who shared the code)
            $table->foreignId('referrer_customer_id')->constrained('customers')->cascadeOnDelete();

            // Referred (the new customer)
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('referred_email')->nullable(); // Email before they sign up

            // Referral code used
            $table->string('referral_code', 20);

            // Status tracking
            $table->enum('status', ['pending', 'signed_up', 'converted', 'expired', 'cancelled'])->default('pending');
            // pending = link clicked but not signed up
            // signed_up = customer created account
            // converted = customer made first qualifying order
            // expired = link expired without conversion
            // cancelled = manually cancelled

            // Points awarded
            $table->integer('referrer_points_awarded')->default(0);
            $table->integer('referred_points_awarded')->default(0);
            $table->boolean('points_processed')->default(false);

            // Conversion tracking
            $table->string('reference_type')->nullable(); // Order type that triggered conversion
            $table->unsignedBigInteger('reference_id')->nullable(); // Order ID

            // Attribution data
            $table->string('source')->nullable(); // email, social, direct, etc.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Timestamps
            $table->timestamp('referred_at')->nullable(); // When the link was clicked
            $table->timestamp('signed_up_at')->nullable(); // When they created account
            $table->timestamp('converted_at')->nullable(); // When they made first order
            $table->timestamp('expires_at')->nullable(); // Referral expiration

            $table->timestamps();

            $table->index(['tenant_id', 'referrer_customer_id']);
            $table->index(['tenant_id', 'referred_customer_id']);
            $table->index(['tenant_id', 'referral_code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['referred_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
