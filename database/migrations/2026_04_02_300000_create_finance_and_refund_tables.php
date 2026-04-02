<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Finance Fee Rules
        Schema::create('finance_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('name');
            $table->string('fee_type', 30); // fixed_daily, fixed_period, percentage_sales, fixed_per_transaction, percentage_per_category
            $table->integer('amount_cents')->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->json('category_filter')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('apply_on', 20)->default('gross_sales'); // gross_sales, net_sales
            $table->string('billing_frequency', 20)->default('end_of_festival'); // daily, weekly, end_of_festival, custom
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->index(['tenant_id', 'festival_edition_id']);
        });

        // 2. Pricing Rules
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_product_id')->nullable();
            $table->unsignedBigInteger('supplier_brand_id')->nullable();
            $table->string('product_category', 100)->nullable();
            $table->string('name');
            $table->boolean('is_mandatory')->default(true);
            $table->integer('final_price_cents');
            $table->string('currency', 3)->default('RON');
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('supplier_product_id')->references('id')->on('supplier_products')->nullOnDelete();
            $table->foreign('supplier_brand_id')->references('id')->on('supplier_brands')->nullOnDelete();
            $table->index(['tenant_id', 'festival_edition_id']);
        });

        // 3. Pricing Rule Components
        Schema::create('pricing_rule_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_rule_id')->constrained()->cascadeOnDelete();
            $table->string('component_type', 30); // base_price, markup_fixed, markup_percentage, vat, sgr, eco_tax, service_fee, custom
            $table->string('label');
            $table->integer('amount_cents')->nullable();
            $table->decimal('percentage', 8, 4)->nullable();
            $table->string('applies_on', 20)->default('base_price'); // base_price, subtotal, custom
            $table->integer('sort_order')->default(0);
            $table->boolean('is_included_in_final')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 4. Vendor Finance Summaries (daily aggregates)
        Schema::create('vendor_finance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->date('period_date');
            $table->integer('gross_sales_cents')->default(0);
            $table->integer('net_sales_cents')->default(0);
            $table->integer('commission_cents')->default(0);
            $table->integer('fees_cents')->default(0);
            $table->integer('tax_collected_cents')->default(0);
            $table->integer('sgr_collected_cents')->default(0);
            $table->integer('tips_cents')->default(0);
            $table->integer('vendor_payout_cents')->default(0);
            $table->integer('transactions_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->unique(['festival_edition_id', 'vendor_id', 'period_date'], 'vendor_fin_summary_unique');
        });

        // 5. Cashless Refunds (with approval flow)
        Schema::create('cashless_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->string('refund_type', 20); // full, partial, auto, compensation
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, processed, cancelled
            $table->unsignedBigInteger('requested_by_employee_id')->nullable();
            $table->unsignedBigInteger('approved_by_employee_id')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->integer('total_refund_cents');
            $table->string('currency', 3)->default('RON');
            $table->unsignedBigInteger('wristband_transaction_id')->nullable();
            $table->text('reason');
            $table->json('items')->nullable(); // [{vendor_sale_item_id, quantity, amount_cents}]
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('requested_by_employee_id')->references('id')->on('vendor_employees')->nullOnDelete();
            $table->foreign('approved_by_employee_id')->references('id')->on('vendor_employees')->nullOnDelete();
            $table->foreign('wristband_transaction_id')->references('id')->on('wristband_transactions')->nullOnDelete();
            $table->index(['festival_edition_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashless_refunds');
        Schema::dropIfExists('vendor_finance_summaries');
        Schema::dropIfExists('pricing_rule_components');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('finance_fee_rules');
    }
};
