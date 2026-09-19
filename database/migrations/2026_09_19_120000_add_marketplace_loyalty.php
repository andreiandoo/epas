<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace loyalty: points that work on their own (bilete.online first).
 *
 * - gamification_configs.auto_rewards_enabled: the switch. Off by default, so no marketplace starts awarding points
 *   (on purchases, birthdays, referrals) or expiring them until an admin turns it on for it.
 * - gamification_configs.referral_min_order / referral_max_per_year: the referral rules.
 * - gamification_configs.points_confirm_days: points from a purchase are credited this many days after the activity.
 * - orders.points_used / points_discount: points paid at checkout. Kept apart from discount_amount on purpose:
 *   discount_amount is a promo code funded by the organizer and comes off the organizer's settlement; points are
 *   funded by the marketplace, so the organizer is paid the full price.
 * - loyalty_pending_earnings: points earned by a paid order, waiting for the activity to happen (and for the order not
 *   to be refunded) before they are credited to the ledger. One row per order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gamification_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('gamification_configs', 'auto_rewards_enabled')) {
                $table->boolean('auto_rewards_enabled')->default(false);
            }
            if (!Schema::hasColumn('gamification_configs', 'referral_min_order')) {
                $table->decimal('referral_min_order', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('gamification_configs', 'referral_max_per_year')) {
                $table->unsignedInteger('referral_max_per_year')->default(10);
            }
            if (!Schema::hasColumn('gamification_configs', 'points_confirm_days')) {
                $table->unsignedSmallInteger('points_confirm_days')->default(2);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'points_used')) {
                $table->unsignedInteger('points_used')->default(0);
            }
            if (!Schema::hasColumn('orders', 'points_discount')) {
                $table->decimal('points_discount', 10, 2)->default(0);
            }
        });

        if (!Schema::hasTable('loyalty_pending_earnings')) {
            Schema::create('loyalty_pending_earnings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketplace_client_id');
                $table->unsignedBigInteger('marketplace_customer_id');
                $table->unsignedBigInteger('order_id')->unique();
                $table->integer('points')->default(0);
                $table->decimal('base_amount', 12, 2)->default(0);
                $table->timestamp('available_at');
                $table->string('status', 20)->default('pending'); // pending, credited, cancelled
                $table->unsignedBigInteger('points_transaction_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'available_at'], 'lpe_status_available_idx');
                $table->index(['marketplace_client_id', 'marketplace_customer_id', 'status'], 'lpe_client_customer_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_pending_earnings');

        Schema::table('orders', function (Blueprint $table) {
            foreach (['points_used', 'points_discount'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('gamification_configs', function (Blueprint $table) {
            foreach (['auto_rewards_enabled', 'referral_min_order', 'referral_max_per_year', 'points_confirm_days'] as $column) {
                if (Schema::hasColumn('gamification_configs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
