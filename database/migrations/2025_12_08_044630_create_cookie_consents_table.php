<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create cookie consents table for GDPR compliance.
     *
     * This table stores customer cookie consent preferences with full audit trail
     * to demonstrate GDPR compliance.
     */
    public function up(): void
    {
        Schema::create('cookie_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Customer identification (can be anonymous or authenticated)
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->string('visitor_id', 64)->nullable()->index(); // For anonymous visitors
            $table->string('session_id', 64)->nullable(); // Session at time of consent

            // Consent categories (GDPR standard categories)
            $table->boolean('necessary')->default(true); // Always required
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->boolean('preferences')->default(false);

            // Granular consent details (JSON for flexibility)
            $table->json('consent_details')->nullable(); // e.g., specific providers consented to

            // Consent action
            $table->enum('action', ['accept_all', 'reject_all', 'customize', 'update'])->default('customize');

            // Consent metadata for GDPR proof
            $table->string('consent_version', 20)->default('1.0'); // Version of consent banner/policy
            $table->string('ip_address', 45); // IPv4 or IPv6
            $table->string('ip_country', 2)->nullable(); // Country code from IP
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable(); // mobile, desktop, tablet
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();

            // Source tracking
            $table->string('consent_source', 50)->default('banner'); // banner, settings, api
            $table->string('page_url')->nullable(); // Page where consent was given
            $table->string('referrer_url')->nullable();

            // Legal basis
            $table->string('legal_basis', 50)->default('consent'); // consent, legitimate_interest
            $table->text('privacy_policy_url')->nullable(); // URL of policy at time of consent

            // Timestamps
            $table->timestamp('consented_at');
            $table->timestamp('expires_at')->nullable(); // When consent expires (typically 12 months)
            $table->timestamp('withdrawn_at')->nullable(); // When consent was withdrawn
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'visitor_id']);
            $table->index(['tenant_id', 'consented_at']);
            $table->index('expires_at');
        });

        // Consent history table for audit trail
        Schema::create('cookie_consent_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookie_consent_id')->constrained()->onDelete('cascade');

            // Previous state
            $table->boolean('previous_analytics')->nullable();
            $table->boolean('previous_marketing')->nullable();
            $table->boolean('previous_preferences')->nullable();

            // New state
            $table->boolean('new_analytics');
            $table->boolean('new_marketing');
            $table->boolean('new_preferences');

            // Change metadata
            $table->enum('change_type', ['initial', 'update', 'withdrawal', 'renewal']);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('change_source', 50); // banner, settings, api, automated

            $table->timestamp('changed_at');

            $table->index(['cookie_consent_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cookie_consent_history');
        Schema::dropIfExists('cookie_consents');
    }
};
