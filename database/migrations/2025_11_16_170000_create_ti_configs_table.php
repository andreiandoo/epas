<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ticket Insurance - Configuration
     * Stores insurance configuration per tenant/event/ticket type
     */
    public function up(): void
    {
        Schema::create('ti_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Scope: determines what this config applies to
            $table->enum('scope', ['tenant', 'event', 'ticket_type'])->index();
            $table->string('scope_ref')->nullable()->comment('Reference ID for event or ticket_type scope');

            // Pricing configuration
            $table->enum('pricing_mode', ['fixed', 'percent'])->default('percent');
            $table->decimal('value_decimal', 10, 2)->comment('Fixed amount or percentage value');
            $table->decimal('min_decimal', 10, 2)->nullable()->comment('Minimum premium amount');
            $table->decimal('max_decimal', 10, 2)->nullable()->comment('Maximum premium amount');

            // Tax policy (JSON)
            $table->json('tax_policy')->nullable()->comment('Tax configuration: {rate, inclusive, category}');
            // Example: {"rate": 19, "inclusive": false, "category": "insurance"}

            // Scope level: per ticket or per entire order
            $table->enum('scope_level', ['per_ticket', 'per_order'])->default('per_ticket');

            // Eligibility rules (JSON)
            $table->json('eligibility')->nullable()->comment('Eligibility predicates');
            // Example: {
            //   "countries": ["RO", "IT", "FR"],
            //   "exclude_ticket_types": ["free", "staff"],
            //   "exclude_events": ["event-123"],
            //   "min_ticket_price": 10.00,
            //   "max_ticket_price": 1000.00
            // }

            // Terms and configuration (JSON)
            $table->json('terms')->nullable()->comment('Terms URLs, copy, consent text');
            // Example: {
            //   "terms_url": "https://example.com/insurance-terms",
            //   "description": "Protect your ticket with insurance",
            //   "consent_text": "I agree to the insurance terms",
            //   "default_opt_in": false,
            //   "cancellation_policy": "no_refund|proportional|full_if_unused"
            // }

            // Provider configuration
            $table->string('insurer_provider')->default('mock')->comment('Provider adapter key');
            $table->json('provider_config')->nullable()->comment('Provider-specific configuration');

            // Status
            $table->boolean('enabled')->default(true)->index();

            // Priority (lower number = higher priority)
            // ticket_type config overrides event config overrides tenant config
            $table->integer('priority')->default(100);

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'enabled']);
            $table->index(['scope', 'scope_ref']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ti_configs');
    }
};
