<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_credits', function (Blueprint $table) {
            $table->id();
            $table->morphs('creditable'); // Tenant or MarketplaceClient
            $table->enum('credit_type', ['transactional', 'promotional']);
            $table->unsignedInteger('credits_total');
            $table->unsignedInteger('credits_used')->default(0);
            $table->decimal('price_per_sms', 8, 4);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('amount_paid', 10, 2);
            $table->string('stripe_payment_id')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['creditable_type', 'creditable_id', 'credit_type'], 'sms_credits_owner_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_credits');
    }
};
