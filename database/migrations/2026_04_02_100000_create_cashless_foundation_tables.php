<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cashless Accounts - digital wallet per customer per edition
        Schema::create('cashless_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wristband_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('festival_pass_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_number', 50)->unique();
            $table->integer('balance_cents')->default(0);
            $table->integer('total_topped_up_cents')->default(0);
            $table->integer('total_spent_cents')->default(0);
            $table->integer('total_cashed_out_cents')->default(0);
            $table->string('currency', 3)->default('RON');
            $table->string('status', 20)->default('active'); // active, frozen, closed
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'festival_edition_id'], 'cashless_acc_customer_edition_unique');
            $table->index(['tenant_id', 'festival_edition_id']);
            $table->index('status');
        });

        // 2. Top-up Locations - physical top-up stands
        Schema::create('topup_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location_code', 50)->unique();
            $table->string('coordinates', 100)->nullable();
            $table->string('zone', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('operating_hours')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'festival_edition_id']);
        });

        // 3. Cashless Sales - groups VendorSaleItems into a single transaction
        Schema::create('cashless_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wristband_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('vendor_employee_id')->nullable();
            $table->unsignedBigInteger('vendor_pos_device_id')->nullable();
            $table->unsignedBigInteger('vendor_shift_id')->nullable();
            $table->string('sale_number', 50)->unique();
            $table->integer('subtotal_cents')->default(0);
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents')->default(0);
            $table->integer('commission_cents')->default(0);
            $table->integer('tip_cents')->default(0);
            $table->string('currency', 3)->default('RON');
            $table->integer('items_count')->default(0);
            $table->string('status', 20)->default('completed'); // completed, refunded, partial_refund, voided
            $table->timestamp('sold_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_employee_id')->references('id')->on('vendor_employees')->nullOnDelete();
            $table->foreign('vendor_pos_device_id')->references('id')->on('vendor_pos_devices')->nullOnDelete();
            $table->foreign('vendor_shift_id')->references('id')->on('vendor_shifts')->nullOnDelete();

            $table->index(['tenant_id', 'festival_edition_id']);
            $table->index(['vendor_id', 'sold_at']);
            $table->index(['cashless_account_id', 'sold_at']);
            $table->index('status');
            $table->index('sold_at');
        });

        // 4. Cashless Settings - per-edition configuration
        Schema::create('cashless_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->unique()->constrained()->cascadeOnDelete();

            // Top-up limits
            $table->integer('min_topup_cents')->default(1000);
            $table->integer('max_topup_cents')->default(100000);
            $table->integer('max_balance_cents')->default(500000);
            $table->integer('daily_topup_limit_cents')->nullable();

            // Cashout settings
            $table->boolean('allow_online_cashout')->default(true);
            $table->boolean('allow_physical_cashout')->default(true);
            $table->integer('min_cashout_cents')->default(100);
            $table->integer('cashout_fee_cents')->default(0);
            $table->decimal('cashout_fee_percentage', 5, 2)->default(0);
            $table->boolean('auto_cashout_after_festival')->default(true);
            $table->integer('auto_cashout_delay_days')->default(7);
            $table->string('auto_cashout_method', 20)->default('bank_transfer');

            // Transfer settings
            $table->boolean('allow_account_transfer')->default(true);
            $table->integer('max_transfer_cents')->nullable();
            $table->integer('transfer_fee_cents')->default(0);

            // POS settings
            $table->integer('require_pin_above_cents')->nullable();
            $table->integer('max_charge_cents')->default(200000);
            $table->integer('charge_cooldown_seconds')->default(10);

            // Age verification
            $table->boolean('enforce_age_verification')->default(true);
            $table->string('age_verification_method', 20)->default('date_of_birth');

            // Currency & display
            $table->string('currency', 3)->default('RON');
            $table->string('currency_symbol', 5)->default('RON');
            $table->integer('display_decimals')->default(2);

            // Notifications
            $table->integer('low_balance_threshold_cents')->default(2000);
            $table->boolean('send_receipt_on_purchase')->default(true);
            $table->boolean('send_daily_summary')->default(false);

            // Tipping
            $table->boolean('allow_tipping')->default(false);
            $table->json('tip_presets')->nullable(); // e.g. [5, 10, 15, 20] percentages
            $table->integer('max_tip_cents')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 5. Cashless Vouchers - promo credits
        Schema::create('cashless_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('voucher_type', 30); // fixed_credit, percentage_bonus, topup_bonus
            $table->integer('amount_cents')->nullable();
            $table->decimal('bonus_percentage', 5, 2)->nullable();
            $table->integer('min_topup_cents')->nullable();
            $table->integer('max_bonus_cents')->nullable();
            $table->string('sponsor_name')->nullable();
            $table->integer('total_budget_cents')->nullable();
            $table->integer('used_budget_cents')->default(0);
            $table->integer('max_redemptions')->nullable();
            $table->integer('current_redemptions')->default(0);
            $table->integer('max_per_customer')->default(1);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'festival_edition_id']);
            $table->index('is_active');
        });

        // 6. Cashless Voucher Redemptions
        Schema::create('cashless_voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashless_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('amount_cents');
            $table->foreignId('wristband_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('redeemed_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['cashless_voucher_id', 'cashless_account_id', 'customer_id'], 'voucher_redemption_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashless_voucher_redemptions');
        Schema::dropIfExists('cashless_vouchers');
        Schema::dropIfExists('cashless_settings');
        Schema::dropIfExists('cashless_sales');
        Schema::dropIfExists('topup_locations');
        Schema::dropIfExists('cashless_accounts');
    }
};
