<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Processing Fees — F1 foundation.
 *
 * Adds three sets of columns so that we can:
 *   - Configure per-marketplace which payment provider gets which rate
 *     (percent + fixed combined), and whether the fee is passed to the
 *     customer or absorbed by the marketplace commission.
 *   - Override per organizer (rare case — most inherit the marketplace
 *     default). Activities live under their organizer's setting; no
 *     per-activity override at this point.
 *   - Snapshot the fee at order creation time so reports and refunds
 *     stay deterministic if marketplace rates change later.
 *
 * NON-IMPACT GUARANTEE for Ambilet / Tics:
 *   - All new columns default to NULL or 0.
 *   - The runtime ProcessingFeeCalculator short-circuits when
 *     marketplace_clients.payment_fees IS NULL. So for marketplaces that
 *     don't opt in, nothing changes anywhere — checkout, totals,
 *     payouts, reports remain identical.
 *
 * Migration is intentionally additive only — no behavior changes,
 * no data backfill, no constraint additions on existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. marketplace_clients.payment_fees (JSONB)
        //
        // Shape:
        //   {
        //     "pass_to_customer": bool,
        //     "providers": {
        //       "stripe":  {"percent_rate": 1.5, "fixed_cents": 100, "label": "Stripe"},
        //       "netopia": {...},
        //       "ropay":   {...}
        //     }
        //   }
        //
        // NULL = "not configured" → calculator returns zero, status quo.
        // ============================================================
        if (Schema::hasTable('marketplace_clients') && ! Schema::hasColumn('marketplace_clients', 'payment_fees')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->jsonb('payment_fees')->nullable()
                    ->comment('Per-provider processing fee config + pass_to_customer flag. NULL = feature disabled for this marketplace.');
            });
        }

        // ============================================================
        // 2. marketplace_organizers.payment_fee_mode (string nullable)
        //
        // Values:
        //   NULL                       → inherit marketplace default
        //   'pass_to_customer'         → force pass to customer
        //   'absorbed_by_commission'   → force absorb (marketplace eats fee)
        // ============================================================
        if (Schema::hasTable('marketplace_organizers') && ! Schema::hasColumn('marketplace_organizers', 'payment_fee_mode')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                $table->string('payment_fee_mode', 32)->nullable()
                    ->comment('Override for marketplace.payment_fees.pass_to_customer. NULL = inherit.');
            });
        }

        // ============================================================
        // 3. orders.processing_fee_* (snapshot at order creation)
        //
        // - processing_fee_cents:        the fee amount we charged / tracked
        // - processing_fee_passed:       was it added to customer's total
        //                                or absorbed in commission
        // - processing_fee_provider:     'stripe' / 'netopia' / 'ropay'
        // - processing_fee_percent_rate: rate at order time (audit)
        // - processing_fee_fixed_cents:  fixed component at order time (audit)
        //
        // Defaults: 0 / false / NULL — all backward-compatible. Existing
        // orders read as "no fee" by the new code paths, no backfill needed.
        // ============================================================
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'processing_fee_cents')) {
                    $table->integer('processing_fee_cents')->default(0)
                        ->comment('Snapshot of payment processing fee at order time (Stripe/RoPay/etc). 0 for marketplaces without payment_fees config.');
                }
                if (! Schema::hasColumn('orders', 'processing_fee_passed')) {
                    $table->boolean('processing_fee_passed')->default(false)
                        ->comment('Was the fee added to customer total (true) or absorbed in marketplace commission (false).');
                }
                if (! Schema::hasColumn('orders', 'processing_fee_provider')) {
                    $table->string('processing_fee_provider', 32)->nullable()
                        ->comment('Which provider charged: stripe, netopia, ropay, etc.');
                }
                if (! Schema::hasColumn('orders', 'processing_fee_percent_rate')) {
                    $table->decimal('processing_fee_percent_rate', 5, 2)->nullable()
                        ->comment('Percent rate at the moment of the order, for audit even if marketplace later changes config.');
                }
                if (! Schema::hasColumn('orders', 'processing_fee_fixed_cents')) {
                    $table->integer('processing_fee_fixed_cents')->nullable()
                        ->comment('Fixed component at the moment of the order, audit-only.');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_clients') && Schema::hasColumn('marketplace_clients', 'payment_fees')) {
            Schema::table('marketplace_clients', function (Blueprint $table) {
                $table->dropColumn('payment_fees');
            });
        }

        if (Schema::hasTable('marketplace_organizers') && Schema::hasColumn('marketplace_organizers', 'payment_fee_mode')) {
            Schema::table('marketplace_organizers', function (Blueprint $table) {
                $table->dropColumn('payment_fee_mode');
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $cols = [
                    'processing_fee_cents',
                    'processing_fee_passed',
                    'processing_fee_provider',
                    'processing_fee_percent_rate',
                    'processing_fee_fixed_cents',
                ];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('orders', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
