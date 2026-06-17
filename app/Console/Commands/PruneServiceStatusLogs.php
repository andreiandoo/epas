<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneServiceStatusLogs extends Command
{
    /**
     * Delete service_status_logs rows older than --days (default 90).
     *
     * Runs nightly to keep the table from growing unbounded — the schema
     * accumulates ~10k rows/day (services:check-status fires every 5 min,
     * ~13 services per cycle). Without pruning the table reaches 1M+
     * rows in 90 days, slowing the /status page chart and inflating
     * indexes. The application code only reads the last 30 days
     * (ServiceStatusLog::scopeLast30Days), so 90 days is a generous
     * audit buffer.
     *
     * Uses batched DELETE (10k rows/batch) so the operation never holds
     * a long transaction and never builds excessive WAL pressure.
     */
    protected $signature = 'services:prune-status-logs
                            {--days=90 : Retain rows newer than this many days}
                            {--batch=10000 : Rows deleted per transaction}
                            {--sleep=0.2 : Seconds to wait between batches}';

    protected $description = 'Prune old service_status_logs rows (default: keep last 90 days)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $batch = max(100, (int) $this->option('batch'));
        $sleep = max(0, (float) $this->option('sleep'));

        $cutoff = now()->subDays($days);
        $this->info("Pruning service_status_logs older than {$cutoff->toDateTimeString()} ({$days} days)…");

        $totalDeleted = 0;
        $batchNum = 0;
        $startedAt = microtime(true);

        do {
            $deleted = DB::table('service_status_logs')
                ->whereIn('id', function ($q) use ($cutoff, $batch) {
                    $q->select('id')
                        ->from('service_status_logs')
                        ->where('checked_at', '<', $cutoff)
                        ->limit($batch);
                })
                ->delete();

            if ($deleted > 0) {
                $batchNum++;
                $totalDeleted += $deleted;
                $this->line(sprintf('  batch %d: %d rows (total %d)', $batchNum, $deleted, $totalDeleted));

                if ($sleep > 0) {
                    usleep((int) ($sleep * 1_000_000));
                }
            }
        } while ($deleted > 0);

        $elapsed = round(microtime(true) - $startedAt, 2);
        $msg = "Done. Deleted {$totalDeleted} rows in {$batchNum} batches ({$elapsed}s).";

        $this->info($msg);
        Log::info('PruneServiceStatusLogs: ' . $msg, [
            'days_retained' => $days,
            'rows_deleted' => $totalDeleted,
            'batches' => $batchNum,
            'elapsed_seconds' => $elapsed,
        ]);

        return self::SUCCESS;
    }
}
