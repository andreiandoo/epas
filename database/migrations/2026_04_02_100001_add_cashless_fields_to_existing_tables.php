<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Vendor Employees - add auth fields and proper roles
        Schema::table('vendor_employees', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('name');
            $table->string('password')->nullable()->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('password');
        });

        // 2. Vendor Products - add type, pricing, age restriction, SGR, VAT
        Schema::table('vendor_products', function (Blueprint $table) {
            $table->string('type', 20)->nullable()->after('vendor_product_category_id'); // food, drink, alcohol, etc.
            $table->string('unit_measure', 50)->nullable()->after('description');
            $table->decimal('weight_volume', 10, 2)->nullable()->after('unit_measure');
            $table->unsignedBigInteger('supplier_product_id')->nullable()->after('weight_volume');
            $table->integer('base_price_cents')->nullable()->after('supplier_product_id');
            $table->integer('sale_price_cents')->nullable()->after('base_price_cents');
            $table->boolean('is_age_restricted')->default(false)->after('sale_price_cents');
            $table->integer('min_age')->default(18)->after('is_age_restricted');
            $table->integer('sgr_cents')->default(0)->after('min_age');
            $table->decimal('vat_rate', 5, 2)->default(19.00)->after('sgr_cents');
            $table->boolean('vat_included')->default(true)->after('vat_rate');
            $table->string('sku', 100)->nullable()->after('vat_included');
        });

        // 3. Vendor Sale Items - link to cashless_sales, add tax tracking
        Schema::table('vendor_sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('cashless_sale_id')->nullable()->after('id');
            $table->integer('tax_cents')->default(0)->after('total_cents');
            $table->integer('sgr_cents')->default(0)->after('tax_cents');
            $table->string('product_type', 50)->nullable()->after('sgr_cents');
            $table->string('product_category_name', 100)->nullable()->after('product_type');

            $table->foreign('cashless_sale_id')->references('id')->on('cashless_sales')->nullOnDelete();
            $table->index('cashless_sale_id');
        });

        // 4. Wristband Transactions - add cashless account link, channel, topup/cashout details
        Schema::table('wristband_transactions', function (Blueprint $table) {
            $table->string('channel', 20)->nullable()->after('transaction_type'); // online, physical
            $table->string('topup_method', 20)->nullable()->after('channel'); // card, cash, bank_transfer, voucher
            $table->unsignedBigInteger('topup_location_id')->nullable()->after('topup_method');
            $table->unsignedBigInteger('cashless_account_id')->nullable()->after('topup_location_id');
            $table->integer('balance_snapshot_cents')->nullable()->after('cashless_account_id');
            $table->string('customer_email')->nullable()->after('balance_snapshot_cents');
            $table->string('customer_name')->nullable()->after('customer_email');
            $table->string('cashout_channel', 20)->nullable()->after('customer_name');
            $table->string('cashout_method', 20)->nullable()->after('cashout_channel');
            $table->string('cashout_reference')->nullable()->after('cashout_method');
            $table->timestamp('cashout_processed_at')->nullable()->after('cashout_reference');
            $table->string('cashout_status', 20)->nullable()->after('cashout_processed_at');

            $table->foreign('topup_location_id')->references('id')->on('topup_locations')->nullOnDelete();
            $table->foreign('cashless_account_id')->references('id')->on('cashless_accounts')->nullOnDelete();
            $table->index('cashless_account_id');
        });

        // 5. Customers - add gender, age verification
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender', 20)->nullable();
            $table->string('age_group', 20)->nullable();
            $table->boolean('id_verified')->default(false);
            $table->timestamp('id_verified_at')->nullable();
            $table->string('id_verification_method', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['gender', 'age_group', 'id_verified', 'id_verified_at', 'id_verification_method']);
        });

        Schema::table('wristband_transactions', function (Blueprint $table) {
            $table->dropForeign(['topup_location_id']);
            $table->dropForeign(['cashless_account_id']);
            $table->dropIndex(['cashless_account_id']);
            $table->dropColumn([
                'channel', 'topup_method', 'topup_location_id', 'cashless_account_id',
                'balance_snapshot_cents', 'customer_email', 'customer_name',
                'cashout_channel', 'cashout_method', 'cashout_reference',
                'cashout_processed_at', 'cashout_status',
            ]);
        });

        Schema::table('vendor_sale_items', function (Blueprint $table) {
            $table->dropForeign(['cashless_sale_id']);
            $table->dropIndex(['cashless_sale_id']);
            $table->dropColumn(['cashless_sale_id', 'tax_cents', 'sgr_cents', 'product_type', 'product_category_name']);
        });

        Schema::table('vendor_products', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'unit_measure', 'weight_volume', 'supplier_product_id',
                'base_price_cents', 'sale_price_cents', 'is_age_restricted', 'min_age',
                'sgr_cents', 'vat_rate', 'vat_included', 'sku',
            ]);
        });

        Schema::table('vendor_employees', function (Blueprint $table) {
            $table->dropColumn(['full_name', 'password', 'email_verified_at']);
        });
    }
};
