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
        Schema::create('tenant_payment_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->enum('processor', ['stripe', 'netopia', 'euplatesc', 'payu']);
            $table->enum('mode', ['test', 'live'])->default('test');

            // Stripe
            $table->text('stripe_publishable_key')->nullable();
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();

            // Netopia
            $table->text('netopia_api_key')->nullable();
            $table->text('netopia_signature')->nullable();
            $table->text('netopia_public_key')->nullable();

            // Euplatesc
            $table->text('euplatesc_merchant_id')->nullable();
            $table->text('euplatesc_secret_key')->nullable();

            // PayU
            $table->text('payu_merchant_id')->nullable();
            $table->text('payu_secret_key')->nullable();

            $table->boolean('is_active')->default(false);
            $table->json('additional_config')->nullable(); // For extra settings
            $table->timestamps();

            $table->unique(['tenant_id', 'processor']);
            $table->index('processor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_configs');
    }
};
