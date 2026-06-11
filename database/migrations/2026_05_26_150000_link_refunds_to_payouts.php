<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds → payouts linkage.
 *
 * Until now, refunds (marketplace_refund_requests) lived independently of
 * payouts: the "Bilete rambursate" section on the payout detail page
 * pulled every refund for the event live, regardless of which payout
 * (if any) had already accounted for them. That meant a 120 RON refund
 * processed in May appeared verbatim on every subsequent payout's page,
 * and the operator had no way to say "this refund belongs to payout 2902
 * — its 120 RON should be deducted from 2902's amount".
 *
 * This migration adds:
 *   - marketplace_refund_requests.marketplace_payout_id (nullable FK)
 *     A refund belongs to AT MOST one payout. When set, the refund's
 *     amount is deducted from that payout's net.
 *   - marketplace_payouts.refund_amount (decimal, default 0)
 *     Denormalized sum of linked refund amounts. Avoids a JOIN on every
 *     payout read; recomputed on save when linkage changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_refund_requests') && !Schema::hasColumn('marketplace_refund_requests', 'marketplace_payout_id')) {
            Schema::table('marketplace_refund_requests', function (Blueprint $table) {
                $table->foreignId('marketplace_payout_id')
                    ->nullable()
                    ->after('order_id')
                    ->constrained('marketplace_payouts')
                    ->nullOnDelete();
                $table->index('marketplace_payout_id', 'mrr_payout_idx');
            });
        }

        if (Schema::hasTable('marketplace_payouts') && !Schema::hasColumn('marketplace_payouts', 'refund_amount')) {
            Schema::table('marketplace_payouts', function (Blueprint $table) {
                $table->decimal('refund_amount', 12, 2)
                    ->default(0)
                    ->after('discount_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_refund_requests') && Schema::hasColumn('marketplace_refund_requests', 'marketplace_payout_id')) {
            Schema::table('marketplace_refund_requests', function (Blueprint $table) {
                $table->dropIndex('mrr_payout_idx');
                $table->dropConstrainedForeignId('marketplace_payout_id');
            });
        }

        if (Schema::hasTable('marketplace_payouts') && Schema::hasColumn('marketplace_payouts', 'refund_amount')) {
            Schema::table('marketplace_payouts', function (Blueprint $table) {
                $table->dropColumn('refund_amount');
            });
        }
    }
};
