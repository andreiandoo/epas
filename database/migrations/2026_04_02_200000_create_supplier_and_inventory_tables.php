<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend merchandise_suppliers with full company details
        Schema::table('merchandise_suppliers', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('reg_com', 50)->nullable()->after('cui');
            $table->text('fiscal_address')->nullable()->after('reg_com');
            $table->string('county', 100)->nullable()->after('fiscal_address');
            $table->string('city', 100)->nullable()->after('county');
            $table->string('country', 2)->default('RO')->after('city');
            $table->boolean('is_vat_payer')->default(false)->after('country');
            $table->string('bank_name')->nullable()->after('is_vat_payer');
            $table->string('iban', 50)->nullable()->after('bank_name');
            $table->string('contract_number', 100)->nullable()->after('iban');
            $table->date('contract_start')->nullable()->after('contract_number');
            $table->date('contract_end')->nullable()->after('contract_start');
            $table->integer('payment_terms_days')->default(30)->after('contract_end');
            $table->string('status', 20)->default('active')->after('payment_terms_days');
            $table->string('logo_url', 500)->nullable()->after('status');
            $table->string('website', 500)->nullable()->after('logo_url');
        });

        // 2. Supplier Brands
        Schema::create('supplier_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchandise_supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('logo_url', 500)->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'merchandise_supplier_id']);
        });

        // 3. Supplier Products
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchandise_supplier_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_brand_id')->nullable();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->string('type', 20)->nullable();
            $table->string('unit_measure', 50)->nullable();
            $table->decimal('weight_volume', 10, 2)->nullable();
            $table->integer('base_price_cents')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(19.00);
            $table->integer('price_with_vat_cents')->default(0);
            $table->string('packaging_type', 100)->nullable();
            $table->integer('packaging_units')->default(1);
            $table->string('barcode', 50)->nullable();
            $table->boolean('is_age_restricted')->default(false);
            $table->integer('min_age')->default(18);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('supplier_brand_id')->references('id')->on('supplier_brands')->nullOnDelete();
            $table->index(['tenant_id', 'festival_edition_id']);
            $table->index(['merchandise_supplier_id', 'sku']);
        });

        // 4. Inventory Stocks
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->decimal('quantity_total', 12, 3)->default(0);
            $table->decimal('quantity_allocated', 12, 3)->default(0);
            $table->decimal('quantity_sold', 12, 3)->default(0);
            $table->decimal('quantity_returned', 12, 3)->default(0);
            $table->decimal('quantity_wasted', 12, 3)->default(0);
            $table->string('unit_measure', 50)->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->unique(['festival_edition_id', 'supplier_product_id', 'vendor_id'], 'inv_stock_edition_product_vendor_unique');
            $table->index(['tenant_id', 'festival_edition_id']);
        });

        // 5. Inventory Movements
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type', 30); // delivery, allocation, sale, return_to_supplier, return_to_festival, waste, correction
            $table->unsignedBigInteger('from_vendor_id')->nullable();
            $table->unsignedBigInteger('to_vendor_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit_measure', 50)->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('performed_by')->nullable();
            $table->timestamp('performed_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('from_vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('to_vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->index(['festival_edition_id', 'supplier_product_id']);
            $table->index('movement_type');
        });

        // 6. Add supplier_product_id FK on vendor_products (if not already added)
        if (! Schema::hasColumn('vendor_products', 'supplier_product_id_fk_added')) {
            Schema::table('vendor_products', function (Blueprint $table) {
                // The column was added in 2026_04_02_100001 but without FK
                // Now add the FK since supplier_products table exists
                $table->foreign('supplier_product_id')->references('id')->on('supplier_products')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_products', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_products', 'supplier_product_id')) {
                $table->dropForeign(['supplier_product_id']);
            }
        });

        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_brands');

        Schema::table('merchandise_suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'company_name', 'reg_com', 'fiscal_address', 'county', 'city', 'country',
                'is_vat_payer', 'bank_name', 'iban', 'contract_number', 'contract_start',
                'contract_end', 'payment_terms_days', 'status', 'logo_url', 'website',
            ]);
        });
    }
};
