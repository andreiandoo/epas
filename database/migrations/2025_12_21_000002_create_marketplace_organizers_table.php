<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the marketplace_organizers table.
     * Organizers are event creators within a marketplace tenant.
     * They have their own dashboard and can manage their events.
     */
    public function up(): void
    {
        Schema::create('marketplace_organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Basic info
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('pending_approval'); // pending_approval, active, suspended, closed
            $table->text('description')->nullable();

            // Company details
            $table->string('company_name')->nullable();
            $table->string('cui')->nullable(); // Tax ID (CUI in Romania)
            $table->string('reg_com')->nullable(); // Trade register number
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('country')->default('RO');
            $table->string('postal_code')->nullable();

            // Contact information
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();

            // Branding
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('website_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();

            // Commission override (null = use marketplace default)
            $table->string('commission_type')->nullable(); // percent, fixed, both (null = inherit from marketplace)
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_fixed', 10, 2)->nullable();

            // Payout settings
            $table->string('payout_method')->default('bank_transfer'); // bank_transfer, paypal, stripe_connect
            $table->json('payout_details')->nullable(); // Bank account details, IBAN, etc.
            $table->string('payout_frequency')->default('monthly'); // weekly, biweekly, monthly
            $table->decimal('minimum_payout', 10, 2)->default(50.00);
            $table->string('payout_currency')->default('RON');

            // Settings & features
            $table->json('settings')->nullable();
            $table->json('allowed_features')->nullable(); // Features this organizer can use

            // Verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();

            // Contract
            $table->string('contract_status')->default('pending'); // pending, sent, signed, expired
            $table->timestamp('contract_signed_at')->nullable();
            $table->string('contract_signature_ip')->nullable();
            $table->text('contract_signature_data')->nullable();

            // Statistics (cached for performance)
            $table->unsignedInteger('total_events')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('pending_payout', 12, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Unique slug per marketplace
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_organizers');
    }
};
