<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Gift Cards table
        Schema::create('marketplace_gift_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');

            // Card identification
            $table->string('code', 20)->unique(); // e.g., GC-XXXX-XXXX-XXXX
            $table->string('pin', 6)->nullable(); // Optional PIN for extra security

            // Financial
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->string('currency', 3)->default('RON');

            // Purchaser info
            $table->unsignedBigInteger('purchaser_id')->nullable();
            $table->string('purchaser_email');
            $table->string('purchaser_name')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();

            // Recipient info
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->text('personal_message')->nullable();
            $table->string('occasion')->nullable(); // birthday, thank_you, congratulations, etc.

            // Delivery
            $table->string('delivery_method')->default('email'); // email, print
            $table->timestamp('scheduled_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->boolean('is_delivered')->default(false);

            // Status and validity
            $table->string('status')->default('pending'); // pending, active, used, expired, cancelled, revoked
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('first_used_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // Recipient account link (when redeemed/claimed)
            $table->unsignedBigInteger('recipient_customer_id')->nullable();
            $table->timestamp('claimed_at')->nullable();

            // Template/design
            $table->string('design_template')->default('default');
            $table->json('design_options')->nullable();

            // Tracking
            $table->integer('usage_count')->default(0);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys with short names
            $table->foreign('marketplace_client_id', 'mkt_gc_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->foreign('purchaser_id', 'mkt_gc_purchaser_fk')
                ->references('id')->on('marketplace_customers')->nullOnDelete();
            $table->foreign('purchase_order_id', 'mkt_gc_order_fk')
                ->references('id')->on('orders')->nullOnDelete();
            $table->foreign('recipient_customer_id', 'mkt_gc_recipient_fk')
                ->references('id')->on('marketplace_customers')->nullOnDelete();

            // Indexes
            $table->index(['marketplace_client_id', 'status'], 'mkt_gc_status_idx');
            $table->index('code');
            $table->index('recipient_email');
            $table->index('purchaser_email');
            $table->index('expires_at');
        });

        // Gift Card Transactions (usage history)
        Schema::create('marketplace_gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_gift_card_id');
            $table->unsignedBigInteger('marketplace_client_id');

            // Transaction type
            $table->string('type'); // purchase, redemption, refund, adjustment, expiry

            // Amount (positive for credits, negative for debits)
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('currency', 3)->default('RON');

            // Related order (for redemption)
            $table->unsignedBigInteger('order_id')->nullable();

            // Who performed the transaction
            $table->unsignedBigInteger('performed_by_customer_id')->nullable();
            $table->unsignedBigInteger('performed_by_admin_id')->nullable();

            // Details
            $table->string('description')->nullable();
            $table->string('reference')->nullable(); // External reference
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Foreign keys with short names
            $table->foreign('marketplace_gift_card_id', 'mkt_gc_tx_card_fk')
                ->references('id')->on('marketplace_gift_cards')->onDelete('cascade');
            $table->foreign('marketplace_client_id', 'mkt_gc_tx_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->foreign('order_id', 'mkt_gc_tx_order_fk')
                ->references('id')->on('orders')->nullOnDelete();
            $table->foreign('performed_by_customer_id', 'mkt_gc_tx_cust_fk')
                ->references('id')->on('marketplace_customers')->nullOnDelete();
            $table->foreign('performed_by_admin_id', 'mkt_gc_tx_admin_fk')
                ->references('id')->on('marketplace_admins')->nullOnDelete();

            // Indexes
            $table->index(['marketplace_gift_card_id', 'created_at'], 'mkt_gc_tx_created_idx');
            $table->index('order_id');
        });

        // Gift Card Designs/Templates
        Schema::create('marketplace_gift_card_designs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable(); // birthday, anniversary, thank_you, christmas, etc.

            // Design assets
            $table->string('preview_image')->nullable();
            $table->string('email_template_path')->nullable();
            $table->string('pdf_template_path')->nullable();
            $table->json('colors')->nullable(); // Primary, secondary, accent colors
            $table->json('options')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys with short names
            $table->foreign('marketplace_client_id', 'mkt_gc_design_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');

            $table->index(['marketplace_client_id', 'is_active'], 'mkt_gc_design_active_idx');
        });

        // Add gift card settings to marketplace_clients
        if (!Schema::hasColumn('marketplace_clients', 'gift_card_settings')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->json('gift_card_settings')->nullable()->after('email_settings');
            });
        }

        // Add gift card columns to orders table
        if (!Schema::hasColumn('orders', 'gift_card_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('gift_card_amount', 10, 2)->nullable()->after('total');
                $table->json('gift_card_codes')->nullable()->after('gift_card_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'gift_card_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['gift_card_amount', 'gift_card_codes']);
            });
        }

        if (Schema::hasColumn('marketplace_clients', 'gift_card_settings')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->dropColumn('gift_card_settings');
            });
        }

        Schema::dropIfExists('marketplace_gift_card_designs');
        Schema::dropIfExists('marketplace_gift_card_transactions');
        Schema::dropIfExists('marketplace_gift_cards');
    }
};
