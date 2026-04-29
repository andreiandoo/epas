<?php

namespace App\Console\Commands;

use App\Logging\SystemErrorRecorder;
use App\Models\SystemError;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-shot importer that seeds system_errors from existing data:
 *   - storage/logs/laravel-*.log (and security-*.log, marketplace-*.log)
 *     parsed line by line into recorder calls
 *   - failed_jobs table
 *   - email_logs / marketplace_email_logs with status='failed' or 'bounced'
 *   - webhook_delivery_logs with success=false
 *
 * Idempotent in the sense that re-running won't dedupe — each run inserts
 * fresh rows. So run once after deploy with --days=30, not on every cron.
 *
 * Usage: php artisan system-errors:backfill --days=30
 */
class BackfillSystemErrorsCommand extends Command
{
    protected $signature = 'system-errors:backfill
        {--days=30 : How many days of history to import}
        {--source=all : log|failed_jobs|email|webhook|all}';

    protected $description = 'Backfill the system_errors table from existing log files and DB tables';

    public function handle(SystemErrorRecorder $recorder): int
    {
        $days = (int) $this->option('days');
        $source = (string) $this->option('source');
        $threshold = now()->subDays($days);

        $this->info("Backfilling last {$days} day(s) of errors (source={$source})…");

        if (in_array($source, ['log', 'all'], true)) {
            $this->ingestLogFiles($recorder, $threshold);
        }
        if (in_array($source, ['failed_jobs', 'all'], true)) {
            $this->ingestFailedJobs($recorder, $threshold);
        }
        if (in_array($source, ['email', 'all'], true)) {
            $this->ingestEmailLogs($recorder, $threshold);
        }
        if (in_array($source, ['webhook', 'all'], true)) {
            $this->ingestWebhookLogs($recorder, $threshold);
        }

        $this->info('Backfill complete.');
        return self::SUCCESS;
    }

    protected function ingestLogFiles(SystemErrorRecorder $recorder, Carbon $threshold): void
    {
        $logsPath = storage_path('logs');
        $files = glob($logsPath . '/{laravel,security,marketplace,security-*,marketplace-*,laravel-*}.log', GLOB_BRACE) ?: [];

        $count = 0;
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $this->line("  parsing " . basename($file));
            $count += $this->parseLogFile($file, $recorder, $threshold);
        }
        $this->info("  log files: {$count} entries imported");
    }

    /**
     * Parse a Monolog "single"/"daily" formatted log file. Format:
     *   [2026-04-28 13:01:02] channel.LEVEL: Message {context} {"exception":"..."}
     */
    protected function parseLogFile(string $file, SystemErrorRecorder $recorder, Carbon $threshold): int
    {
        $imported = 0;
        $handle = @fopen($file, 'r');
        if (!$handle) {
            return 0;
        }

        $current = null;
        $flush = function () use (&$current, $recorder, $threshold, &$imported) {
            if ($current === null) {
                return;
            }
            try {
                $datetime = Carbon::parse($current['timestamp']);
            } catch (\Throwable $e) {
                $current = null;
                return;
            }
            if ($datetime->lt($threshold)) {
                $current = null;
                return;
            }
            $level = match (strtolower($current['level'])) {
                'emergency' => 600,
                'alert' => 550,
                'critical' => 500,
                'error' => 400,
                'warning' => 300,
                'notice' => 250,
                'info' => 200,
                'debug' => 100,
                default => 0,
            };
            $recorder->record([
                'level' => $level,
                'level_name' => strtoupper($current['level']),
                'channel' => $current['channel'],
                'source' => 'log',
                'message' => $current['message'],
                'context' => [],
                'datetime' => $datetime,
            ]);
            $imported++;
            $current = null;
        };

        while (($line = fgets($handle)) !== false) {
            // Match "[YYYY-MM-DD HH:MM:SS] channel.LEVEL: rest..."
            if (preg_match('/^\[([\d\-: ]+)\] ([a-zA-Z0-9_\-]+)\.([A-Z]+): (.*)$/', rtrim($line), $m)) {
                $flush();
                $current = [
                    'timestamp' => $m[1],
                    'channel' => $m[2],
                    'level' => $m[3],
                    'message' => $m[4],
                ];
            } elseif ($current) {
                // continuation line — append (truncate to limit)
                $current['message'] .= "\n" . rtrim($line);
                if (strlen($current['message']) > 8000) {
                    $current['message'] = mb_substr($current['message'], 0, 8000);
                }
            }
        }
        $flush();
        fclose($handle);

        return $imported;
    }

    protected function ingestFailedJobs(SystemErrorRecorder $recorder, Carbon $threshold): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
            $this->line('  failed_jobs: table not present');
            return;
        }

        $rows = DB::table('failed_jobs')
            ->where('failed_at', '>=', $threshold)
            ->orderBy('failed_at')
            ->get(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        foreach ($rows as $row) {
            $name = null;
            $payload = json_decode($row->payload ?? '{}', true);
            if (is_array($payload) && isset($payload['displayName'])) {
                $name = $payload['displayName'];
            }
            $recorder->record([
                'level' => 400,
                'channel' => 'queue',
                'source' => 'failed_job',
                'message' => 'Failed job: ' . ($name ?? $row->queue ?? 'unknown'),
                'context' => [
                    'failed_job_id' => $row->id,
                    'connection' => $row->connection,
                    'queue' => $row->queue,
                    'job_name' => $name,
                    'exception_excerpt' => mb_substr((string) $row->exception, 0, 1000),
                ],
                'stack_trace' => $row->exception,
                'datetime' => Carbon::parse($row->failed_at),
            ]);
        }
        $this->info("  failed_jobs: {$rows->count()} entries imported");
    }

    protected function ingestEmailLogs(SystemErrorRecorder $recorder, Carbon $threshold): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('marketplace_email_logs')) {
            $rows = DB::table('marketplace_email_logs')
                ->whereIn('status', ['failed', 'bounced'])
                ->where('created_at', '>=', $threshold)
                ->get();
            foreach ($rows as $row) {
                $recorder->record([
                    'level' => 400,
                    'channel' => 'marketplace',
                    'source' => 'marketplace_email_log',
                    'message' => sprintf('Marketplace email %s: → %s — %s',
                        $row->status,
                        $row->to_email ?? '?',
                        $row->error_message ?? $row->subject ?? '(no detail)'
                    ),
                    'context' => (array) $row,
                    'datetime' => Carbon::parse($row->created_at ?? $row->failed_at ?? now()),
                ]);
            }
            $this->info("  marketplace_email_logs: {$rows->count()} entries imported");
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('email_logs')) {
            $rows = DB::table('email_logs')
                ->where('status', 'failed')
                ->where('created_at', '>=', $threshold)
                ->get();
            foreach ($rows as $row) {
                $recorder->record([
                    'level' => 400,
                    'channel' => 'mail',
                    'source' => 'email_log',
                    'message' => 'Email failed: ' . ($row->error_message ?? '(no detail)'),
                    'context' => (array) $row,
                    'datetime' => Carbon::parse($row->created_at ?? now()),
                ]);
            }
            $this->info("  email_logs: {$rows->count()} entries imported");
        }
    }

    protected function ingestWebhookLogs(SystemErrorRecorder $recorder, Carbon $threshold): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('webhook_delivery_logs')) {
            $this->line('  webhook_delivery_logs: table not present');
            return;
        }

        $rows = DB::table('webhook_delivery_logs')
            ->where('success', false)
            ->where('created_at', '>=', $threshold)
            ->get();

        foreach ($rows as $row) {
            $recorder->record([
                'level' => $row->status_code && $row->status_code >= 500 ? 400 : 300,
                'channel' => 'webhooks',
                'source' => 'webhook_log',
                'message' => sprintf('Webhook delivery failed (%s): %s',
                    $row->status_code ?? 'no-response',
                    $row->error_message ?? 'no error message'
                ),
                'context' => (array) $row,
                'datetime' => Carbon::parse($row->created_at ?? now()),
            ]);
        }
        $this->info("  webhook_delivery_logs: {$rows->count()} entries imported");
    }
}
