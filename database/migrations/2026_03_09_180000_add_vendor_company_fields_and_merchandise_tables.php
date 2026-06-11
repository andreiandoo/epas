<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend vendors with full Romanian company data
        if (!Schema::hasColumn('vendors', 'reg_com')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('reg_com')->nullable()->after('cui');           // J12/345/2020
                $table->string('cod_caen')->nullable()->after('reg_com');      // CAEN code
                $table->string('fiscal_name')->nullable()->after('cod_caen'); // Official name from ANAF
                $table->text('fiscal_address')->nullable()->after('fiscal_name');
                $table->string('county')->nullable()->after('fiscal_address');
                $table->string('city')->nullable()->after('county');
                $table->boolean('is_vat_payer')->default(false)->after('city'); // platitor TVA
                $table->date('vat_since')->nullable()->after('is_vat_payer');  // TVA de cand
                $table->boolean('is_active_fiscal')->default(true)->after('vat_since'); // activ fiscal
                $table->boolean('is_split_vat')->default(false)->after('is_active_fiscal'); // TVA la incasare
                $table->string('bank_name')->nullable()->after('is_split_vat');
                $table->string('iban')->nullable()->after('bank_name');
                $table->timestamp('anaf_verified_at')->nullable()->after('iban');
                $table->json('anaf_data')->nullable()->after('anaf_verified_at'); // raw ANAF response
            });
        }

        // Merchandise suppliers — where festival buys from
        if (!Schema::hasTable('merchandise_suppliers')) {
            Schema::create('merchandise_suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('cui')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        // Merchandise items — goods imported by the festival
        if (!Schema::hasTable('merchandise_items')) {
            Schema::create('merchandise_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('merchandise_supplier_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');                                // "Pahar personalizat 500ml"
                $table->string('type')->default('consumable');         // consumable|equipment|packaging|ingredient|other
                $table->string('unit')->default('buc');                // buc|kg|l|set
                $table->decimal('quantity', 12, 3)->default(0);        // total quantity imported
                $table->unsignedInteger('acquisition_price_cents');     // per unit, excl. TVA
                $table->string('currency', 3)->default('RON');
                $table->decimal('vat_rate', 5, 2)->default(19);        // TVA %
                $table->string('invoice_number')->nullable();
                $table->date('invoice_date')->nullable();
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'festival_edition_id']);
            });
        }

        // Merchandise allocations — goods given to vendors
        if (!Schema::hasTable('merchandise_allocations')) {
            Schema::create('merchandise_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('merchandise_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity_allocated', 12, 3)->default(0);
                $table->decimal('quantity_returned', 12, 3)->default(0);
                $table->timestamp('allocated_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->string('status')->default('allocated');        // allocated|partial_return|returned
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['festival_edition_id', 'vendor_id']);
                $table->index(['merchandise_item_id', 'vendor_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_allocations');
        Schema::dropIfExists('merchandise_items');
        Schema::dropIfExists('merchandise_suppliers');

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'reg_com', 'cod_caen', 'fiscal_name', 'fiscal_address',
                'county', 'city', 'is_vat_payer', 'vat_since',
                'is_active_fiscal', 'is_split_vat', 'bank_name', 'iban',
                'anaf_verified_at', 'anaf_data',
            ]);
        });
    }
};
