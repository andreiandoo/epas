<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_customer_payment_methods')) {
            return;
        }

        Schema::create('marketplace_customer_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->unsignedBigInteger('marketplace_customer_id');

            $table->foreign('marketplace_client_id', 'mcpm_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->foreign('marketplace_customer_id', 'mcpm_customer_fk')
                ->references('id')->on('marketplace_customers')->onDelete('cascade');

            // Payment method type (stripe, netopia, etc.)
            $table->string('provider', 50); // stripe, netopia

            // Card details (masked)
            $table->string('card_brand', 30)->nullable(); // visa, mastercard, amex
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_exp_month', 2)->nullable();
            $table->string('card_exp_year', 4)->nullable();
            $table->string('cardholder_name', 255)->nullable();

            // Provider-specific identifiers
            $table->string('provider_customer_id', 255)->nullable(); // Stripe customer ID
            $table->string('provider_payment_method_id', 255)->nullable(); // Stripe payment method ID
            $table->string('provider_token', 255)->nullable(); // For other providers

            // Metadata
            $table->string('label', 100)->nullable(); // User-defined label
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index for quick lookup
            $table->index(['marketplace_customer_id', 'is_active'], 'mcpm_customer_active_idx');
            $table->index(['provider_customer_id'], 'mcpm_provider_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_customer_payment_methods');
    }
};
