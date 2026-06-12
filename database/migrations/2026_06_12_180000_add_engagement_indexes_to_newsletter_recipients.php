<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes that the stale-cohort + actively-unsubscribed
 * filters all hit at recipient-build time. Without them, every live
 * EditNewsletter form rerender on a 100k+ recipients table did a
 * sequential scan — multiplied by 12 concurrent php-fpm workers, it
 * pinned CPU to 100% and starved the public site.
 *
 * Each index targets one specific query pattern:
 *
 *  - (marketplace_customer_id, sent_at) — "did the customer get a sent
 *    newsletter older than the cooldown?" hit by both stale_no_opens
 *    and stale_no_clicks.
 *  - (marketplace_customer_id, opened_at) — "did the customer ever
 *    open anything?" the partial NULL-aware predicate piggybacks the
 *    same index because Postgres can satisfy `IS NOT NULL` from it.
 *  - (marketplace_customer_id, clicked_at) — same for clicks.
 *  - (marketplace_customer_id, status) — actively-unsubscribed lookups
 *    join on customer_id then filter status='unsubscribed'.
 *
 * Postgres ignores `IF NOT EXISTS` for raw indexes via Blueprint, so
 * we wrap with hasIndex-equivalents via the information_schema query.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_newsletter_recipients')) {
            return;
        }

        $existing = collect(\DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'marketplace_newsletter_recipients'"
        ))->pluck('indexname')->all();

        Schema::table('marketplace_newsletter_recipients', function (Blueprint $table) use ($existing) {
            if (!in_array('mkt_nl_recip_cust_sent_idx', $existing, true)) {
                $table->index(['marketplace_customer_id', 'sent_at'], 'mkt_nl_recip_cust_sent_idx');
            }
            if (!in_array('mkt_nl_recip_cust_opened_idx', $existing, true)) {
                $table->index(['marketplace_customer_id', 'opened_at'], 'mkt_nl_recip_cust_opened_idx');
            }
            if (!in_array('mkt_nl_recip_cust_clicked_idx', $existing, true)) {
                $table->index(['marketplace_customer_id', 'clicked_at'], 'mkt_nl_recip_cust_clicked_idx');
            }
            if (!in_array('mkt_nl_recip_cust_status_idx', $existing, true)) {
                $table->index(['marketplace_customer_id', 'status'], 'mkt_nl_recip_cust_status_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_newsletter_recipients')) {
            return;
        }
        Schema::table('marketplace_newsletter_recipients', function (Blueprint $table) {
            foreach ([
                'mkt_nl_recip_cust_sent_idx',
                'mkt_nl_recip_cust_opened_idx',
                'mkt_nl_recip_cust_clicked_idx',
                'mkt_nl_recip_cust_status_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable) {}
            }
        });
    }
};
