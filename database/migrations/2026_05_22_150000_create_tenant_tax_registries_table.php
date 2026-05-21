<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E5 — Tenant-side multi-society. A leisure tenant can have multiple legal
 * entities (CUI/CIF) to invoice from. Mirror of marketplace_tax_registries
 * but for direct tenants.
 *
 * Each TicketType row gets `tenant_tax_registry_id` (added in companion
 * migration below) — the issuer for that product. At order completion the
 * InvoiceSplitter groups items by issuer and emits one invoice per issuer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_tax_registries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('company_name');
            $table->string('cui')->index();
            $table->string('reg_com')->nullable();
            $table->boolean('vat_payer')->default(false);
            $table->string('vat_number')->nullable();

            $table->string('country', 2)->default('RO');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();

            $table->string('invoice_series')->nullable();
            $table->unsignedInteger('invoice_next_number')->default(1);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active'], 'ttr_tenant_active_idx');
            $table->unique(['tenant_id', 'cui'], 'ttr_tenant_cui_unique');
        });

        // Add issuer foreign key on ticket_types (additive, nullable).
        Schema::table('ticket_types', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_types', 'tenant_tax_registry_id')) {
                $table->foreignId('tenant_tax_registry_id')
                    ->nullable()
                    ->after('issuing_tax_registry_id')
                    ->constrained('tenant_tax_registries')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_types', 'tenant_tax_registry_id')) {
                $table->dropConstrainedForeignId('tenant_tax_registry_id');
            }
        });
        Schema::dropIfExists('tenant_tax_registries');
    }
};
