<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time retrofit: every legacy purchase tracking event stored the
 * order id in content_id but never in order_id. ROAS attribution joins
 * core_customer_events.order_id to orders.id, so without this backfill
 * the entire historical funnel is unattributed even though the data is
 * literally sitting there in a sibling column.
 *
 *   php artisan capi:backfill-event-order-ids        # do it
 *   php artisan capi:backfill-event-order-ids --dry  # just count
 *
 * Idempotent — only updates rows where order_id IS NULL.
 */
class BackfillCoreEventOrderIdsCommand extends Command
{
    protected $signature = 'capi:backfill-event-order-ids {--dry : Only report the count, do not write} {--chunk=5000 : Batch size}';
    protected $description = 'Populate core_customer_events.order_id from content_id for purchase events';

    public function handle(): int
    {
        $where = "event_type = 'purchase'
            AND order_id IS NULL
            AND content_id IS NOT NULL
            AND content_id ~ '^[0-9]+\$'";

        $pending = (int) DB::scalar("SELECT COUNT(*) FROM core_customer_events WHERE $where");
        $this->info("Pending purchase events with content_id but null order_id: {$pending}");

        if ($pending === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $this->warn('Dry run — no rows written.');
            return self::SUCCESS;
        }

        $chunk = (int) $this->option('chunk');
        $totalUpdated = 0;

        do {
            $affected = DB::affectingStatement("
                UPDATE core_customer_events
                SET order_id = content_id::bigint
                WHERE id IN (
                    SELECT id FROM core_customer_events
                    WHERE $where
                    LIMIT $chunk
                    FOR UPDATE SKIP LOCKED
                )
            ");
            $totalUpdated += $affected;
            $this->line("  …updated batch: {$affected} rows (total {$totalUpdated})");
        } while ($affected > 0);

        $this->info("Done. Backfilled order_id on {$totalUpdated} core_customer_events rows.");
        return self::SUCCESS;
    }
}
