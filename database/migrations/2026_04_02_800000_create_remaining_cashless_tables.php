<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §24 - Disputes
        Schema::create('cashless_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wristband_transaction_id')->nullable();
            $table->unsignedBigInteger('cashless_sale_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('dispute_type', 30);
            $table->string('status', 30)->default('open');
            $table->integer('amount_disputed_cents');
            $table->integer('amount_refunded_cents')->default(0);
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('wristband_transaction_id')->references('id')->on('wristband_transactions')->nullOnDelete();
            $table->foreign('cashless_sale_id')->references('id')->on('cashless_sales')->nullOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['festival_edition_id', 'status']);
        });

        // §25 - Webhooks
        Schema::create('cashless_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('festival_edition_id')->nullable();
            $table->string('url', 500);
            $table->string('secret');
            $table->string('description')->nullable();
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('festival_edition_id')->references('id')->on('festival_editions')->nullOnDelete();
        });

        Schema::create('cashless_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashless_webhook_endpoint_id')->constrained('cashless_webhook_endpoints')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('attempted_at');
            $table->boolean('succeeded')->default(false);
            $table->integer('attempt_number')->default(1);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['cashless_webhook_endpoint_id', 'succeeded']);
        });

        // §26 - Exchange Rates
        Schema::create('cashless_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 12, 6);
            $table->decimal('markup_rate', 12, 6);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->string('source', 50)->default('manual');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['festival_edition_id', 'from_currency', 'to_currency', 'valid_from'], 'exchange_rate_unique');
        });

        // §26 - Multi-currency fields on CashlessSettings
        Schema::table('cashless_settings', function (Blueprint $table) {
            $table->json('supported_currencies')->nullable()->after('ntag_ndef_url_prefix');
            $table->string('base_currency', 3)->default('RON')->after('supported_currencies');
            $table->string('exchange_rate_source', 20)->default('manual')->after('base_currency');
            $table->integer('exchange_rate_refresh_minutes')->default(60)->after('exchange_rate_source');
            $table->decimal('exchange_markup_percentage', 5, 2)->default(2.00)->after('exchange_rate_refresh_minutes');
        });

        // §28 - Spending Limits
        Schema::create('cashless_spending_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_account_id');
            $table->unsignedBigInteger('child_account_id');
            $table->integer('daily_limit_cents')->nullable();
            $table->integer('total_limit_cents')->nullable();
            $table->integer('per_transaction_limit_cents')->nullable();
            $table->integer('daily_spent_cents')->default(0);
            $table->integer('total_spent_cents')->default(0);
            $table->json('blocked_categories')->nullable();
            $table->integer('require_approval_above_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_account_id')->references('id')->on('cashless_accounts')->cascadeOnDelete();
            $table->foreign('child_account_id')->references('id')->on('cashless_accounts')->cascadeOnDelete();
            $table->unique(['parent_account_id', 'child_account_id'], 'spending_limit_parent_child');
        });

        // §40 - Vendor Stands
        Schema::create('vendor_stands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->string('name');
            $table->string('slug');
            $table->string('location')->nullable();
            $table->string('location_coordinates', 100)->nullable();
            $table->string('zone', 100)->nullable();
            $table->string('fiscal_device_id', 100)->nullable();
            $table->string('status', 20)->default('setup');
            $table->json('operating_hours')->nullable();
            $table->integer('capacity')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->unique(['festival_edition_id', 'slug'], 'vendor_stand_edition_slug');
        });

        Schema::create('vendor_stand_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_stand_id');
            $table->unsignedBigInteger('vendor_product_id');
            $table->boolean('is_available')->default(true);
            $table->integer('override_price_cents')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('vendor_stand_id')->references('id')->on('vendor_stands')->cascadeOnDelete();
            $table->foreign('vendor_product_id')->references('id')->on('vendor_products')->cascadeOnDelete();
            $table->unique(['vendor_stand_id', 'vendor_product_id'], 'stand_product_unique');
        });

        // §40 - Add vendor_stand_id to existing tables
        Schema::table('cashless_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_stand_id')->nullable()->after('vendor_shift_id');
            $table->foreign('vendor_stand_id')->references('id')->on('vendor_stands')->nullOnDelete();
        });

        Schema::table('vendor_sale_items', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_stand_id')->nullable()->after('vendor_shift_id');
            $table->foreign('vendor_stand_id')->references('id')->on('vendor_stands')->nullOnDelete();
        });

        Schema::table('vendor_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_stand_id')->nullable()->after('vendor_pos_device_id');
            $table->foreign('vendor_stand_id')->references('id')->on('vendor_stands')->nullOnDelete();
        });

        Schema::table('vendor_pos_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_stand_id')->nullable()->after('festival_edition_id');
            $table->foreign('vendor_stand_id')->references('id')->on('vendor_stands')->nullOnDelete();
        });

        // §40 - Inventory Transfer Requests
        Schema::create('inventory_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('unit_measure', 50)->nullable();
            $table->string('from_type', 20); // festival, vendor, stand
            $table->unsignedBigInteger('from_vendor_id')->nullable();
            $table->unsignedBigInteger('from_stand_id')->nullable();
            $table->string('to_type', 20); // vendor, stand
            $table->unsignedBigInteger('to_vendor_id')->nullable();
            $table->unsignedBigInteger('to_stand_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('requested_by')->nullable();
            $table->timestamp('requested_at');
            $table->string('accepted_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('from_vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('from_stand_id')->references('id')->on('vendor_stands')->nullOnDelete();
            $table->foreign('to_vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('to_stand_id')->references('id')->on('vendor_stands')->nullOnDelete();
            $table->foreign('inventory_movement_id')->references('id')->on('inventory_movements')->nullOnDelete();
        });

        // §42 - Credit Allocations (staff/artist/sponsor)
        Schema::create('cashless_credit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('allocated_by');
            $table->string('allocation_type', 20); // one_time, daily, per_period
            $table->integer('amount_cents');
            $table->integer('total_allocated_cents')->default(0);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('allocated_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // §45 - Vendor onboarding status on VendorEdition
        Schema::table('vendor_edition', function (Blueprint $table) {
            $table->string('onboarding_status', 30)->default('onboarding')->after('meta');
            $table->timestamp('approved_at')->nullable()->after('onboarding_status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->timestamp('go_live_at')->nullable()->after('approved_by');

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        // §45 - Festival Closure Checklists
        Schema::create('festival_closure_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->json('steps')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('started_by')->references('id')->on('users')->nullOnDelete();
        });

        // §32 - Lost & Found cashless integration
        if (Schema::hasTable('lost_and_found')) {
            Schema::table('lost_and_found', function (Blueprint $table) {
                $table->unsignedBigInteger('festival_edition_id')->nullable();
                $table->unsignedBigInteger('wristband_id')->nullable();
                $table->unsignedBigInteger('cashless_account_id')->nullable();
                $table->string('wristband_uid', 100)->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->unsignedBigInteger('topup_location_id')->nullable();
                $table->string('zone', 100)->nullable();
                $table->string('urgency', 10)->default('medium');
                $table->boolean('notification_sent')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lost_and_found') && Schema::hasColumn('lost_and_found', 'festival_edition_id')) {
            Schema::table('lost_and_found', function (Blueprint $table) {
                $table->dropColumn([
                    'festival_edition_id', 'wristband_id', 'cashless_account_id',
                    'wristband_uid', 'vendor_id', 'topup_location_id', 'zone',
                    'urgency', 'notification_sent',
                ]);
            });
        }

        Schema::dropIfExists('festival_closure_checklists');
        Schema::table('vendor_edition', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['onboarding_status', 'approved_at', 'approved_by', 'go_live_at']);
        });
        Schema::dropIfExists('cashless_credit_allocations');
        Schema::dropIfExists('inventory_transfer_requests');
        Schema::table('vendor_pos_devices', function (Blueprint $table) { $table->dropForeign(['vendor_stand_id']); $table->dropColumn('vendor_stand_id'); });
        Schema::table('vendor_shifts', function (Blueprint $table) { $table->dropForeign(['vendor_stand_id']); $table->dropColumn('vendor_stand_id'); });
        Schema::table('vendor_sale_items', function (Blueprint $table) { $table->dropForeign(['vendor_stand_id']); $table->dropColumn('vendor_stand_id'); });
        Schema::table('cashless_sales', function (Blueprint $table) { $table->dropForeign(['vendor_stand_id']); $table->dropColumn('vendor_stand_id'); });
        Schema::dropIfExists('vendor_stand_products');
        Schema::dropIfExists('vendor_stands');
        Schema::dropIfExists('cashless_spending_limits');
        Schema::table('cashless_settings', function (Blueprint $table) {
            $table->dropColumn(['supported_currencies', 'base_currency', 'exchange_rate_source', 'exchange_rate_refresh_minutes', 'exchange_markup_percentage']);
        });
        Schema::dropIfExists('cashless_exchange_rates');
        Schema::dropIfExists('cashless_webhook_deliveries');
        Schema::dropIfExists('cashless_webhook_endpoints');
        Schema::dropIfExists('cashless_disputes');
    }
};
