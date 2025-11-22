<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add Stripe Connect fields to tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_connect_id')->nullable()->after('id');
            $table->boolean('stripe_onboarding_complete')->default(false);
            $table->boolean('stripe_charges_enabled')->default(false);
            $table->boolean('stripe_payouts_enabled')->default(false);
            $table->decimal('platform_fee_percentage', 5, 2)->default(5.00);
            $table->json('stripe_connect_meta')->nullable();

            $table->index('stripe_connect_id');
        });

        // Payment splits tracking
        Schema::create('payment_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('door_sale_id')->nullable()->constrained()->onDelete('set null');
            $table->string('stripe_payment_intent_id');
            $table->string('stripe_transfer_id')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('tenant_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('stripe_fee', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('stripe_payment_intent_id');
        });

        // Stripe Connect onboarding sessions
        Schema::create('stripe_connect_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('account_link_url');
            $table->timestamp('expires_at');
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_sessions');
        Schema::dropIfExists('payment_splits');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_connect_id',
                'stripe_onboarding_complete',
                'stripe_charges_enabled',
                'stripe_payouts_enabled',
                'platform_fee_percentage',
                'stripe_connect_meta',
            ]);
        });
    }
};
