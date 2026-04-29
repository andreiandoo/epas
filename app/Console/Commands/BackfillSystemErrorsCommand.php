<?php

namespace App\Console\Commands;

use App\Logging\ErrorClassifier;
use App\Logging\Fingerprinter;
use App\Logging\SystemErrorRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One-shot importer that seeds system_errors from existing data:
 *   - storage/logs/laravel-*.log (and security-*.log, marketplace-*.log)
 *   - failed_jobs table
 *   - email_logs / marketplace_email_logs with status='failed' or 'bounced'
 *   - webhook_delivery_logs with success=false
 *
 * Optimized for cold imports: batches 500 rows per INSERT, skips request
 * context (CLI doesn't have a meaningful one), and prints progress every
 * 5000 entries so a multi-million-row log doesn't look hung.
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
        {--source=all : log|failed_jobs|email|webhook|all}
        {--batch=500 : Rows per INSERT}
        {--max-file-size=200 : Skip log files larger than this many MB (single un-rotated logs can be huge)}
        {--include-large : Force-include files above max-file-size}';

    protected $description = 'Backfill the system_errors table from existing log files and DB tables';

    /** @var array<int,array> Buffered rows pending flush */
    protected array $buffer = [];

    protected int $batchSize = 500;
    protected int $totalInserted = 0;
    protected ErrorClassifier $classifier;

    public function handle(SystemErrorRecorder $recorder): int
    {
        $days = (int) $this->option('days');
        $source = (string) $this->option('source');
        $this->batchSize = max(50, (int) $this->option('batch'));
        $threshold = now()->subDays($days);
        $this->classifier = app(ErrorClassifier::class);

        $this->info("Backfilling last {$days} day(s) of errors (source={$source}, batch={$this->batchSize})…");

        if (in_array($source, ['log', 'all'], true)) {
            $this->ingestLogFiles($threshold);
        }
        if (in_array($source, ['failed_jobs', 'all'], true)) {
            $this->ingestFailedJobs($threshold);
        }
        if (in_array($source, ['email', 'all'], true)) {
            $this->ingestEmailLogs($threshold);
        }
        if (in_array($source, ['webhook', 'all'], true)) {
            $this->ingestWebhookLogs($threshold);
        }

        $this->flush();
        $this->info("Backfill complete. Total inserted: {$this->totalInserted}");
        return self::SUCCESS;
    }

    protected function ingestLogFiles(Carbon $threshold): void
    {
        $logsPath = storage_path('logs');
        $files = glob($logsPath . '/{laravel,security,marketplace,security-*,marketplace-*,laravel-*}.log', GLOB_BRACE) ?: [];

        $maxMb = (float) $this->option('max-file-size');
        $force = (bool) $this->option('include-large');

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $sizeMb = round(filesize($file) / 1024 / 1024, 1);
            if (!$force && $maxMb > 0 && $sizeMb > $maxMb) {
                $this->warn("  SKIPPING " . basename($file) . " ({$sizeMb} MB > {$maxMb} MB cap). Use --include-large to force, or rotate the file first.");
                continue;
            }
            $this->line("  parsing " . basename($file) . " ({$sizeMb} MB)");
            $count = $this->parseLogFile($file, $threshold);
            $this->info("    {$count} entries imported");
        }
    }

    /**
     * Stream the log file line by line and buffer parsed entries. Every
     * batchSize entries hits the DB as a single INSERT with N rows.
     */
    protected function parseLogFile(string $file, Carbon $threshold): int
    {
        $imported = 0;
        $handle = @fopen($file, 'r');
        if (!$handle) {
            return 0;
        }

        $current = null;
        $reportEvery = 5000;
        $progress = 0;

        $flush = function () use (&$current, $threshold, &$imported, &$progress, $reportEvery) {
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

            $captureLevel = (int) config('system_errors.capture_level', 300);
            if ($level < $captureLevel) {
                $current = null;
                return;
            }

            $this->bufferRow([
                'level' => $level,
                'level_name' => strtoupper($current['level']),
                'channel' => $current['channel'],
                'source' => 'log',
                'message' => mb_substr((string) $current['message'], 0, 8000),
                'context' => [],
                'exception_class' => null,
                'exception_file' => null,
                'exception_line' => null,
                'stack_trace' => null,
                'datetime' => $datetime,
            ]);
            $imported++;
            $progress++;
            if ($progress % $reportEvery === 0) {
                $this->line("    …{$progress} parsed");
            }
            $current = null;
        };

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\[([\d\-: ]+)\] ([a-zA-Z0-9_\-]+)\.([A-Z]+): (.*)$/', rtrim($line), $m)) {
                $flush();
                $current = [
                    'timestamp' => $m[1],
                    'channel' => $m[2],
                    'level' => $m[3],
                    'message' => $m[4],
                ];
            } elseif ($current) {
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

    protected function ingestFailedJobs(Carbon $threshold): void
    {
        if (!Schema::hasTable('failed_jobs')) {
            $this->line('  failed_jobs: table not present');
            return;
        }

        $count = 0;
        DB::table('failed_jobs')
            ->where('failed_at', '>=', $threshold)
            ->orderBy('failed_at')
            ->select(['id', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->chunkById(1000, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $name = null;
                    $payload = json_decode($row->payload ?? '{}', true);
                    if (is_array($payload) && isset($payload['displayName'])) {
                        $name = $payload['displayName'];
                    }
                    $this->bufferRow([
                        'level' => 400,
                        'level_name' => 'ERROR',
                        'channel' => 'queue',
                        'source' => 'failed_job',
                        'message' => 'Failed job: ' . ($name ?? $row->queue ?? 'unknown'),
                        'context' => [
                            'failed_job_id' => $row->id,
                            'connection' => $row->connection,
                            'queue' => $row->queue,
                            'job_name' => $name,
                        ],
                        'exception_class' => null,
                        'exception_file' => null,
                        'exception_line' => null,
                        'stack_trace' => $row->exception,
                        'datetime' => Carbon::parse($row->failed_at),
                    ]);
                    $count++;
                }
            });

        $this->info("  failed_jobs: {$count} entries imported");
    }

    protected function ingestEmailLogs(Carbon $threshold): void
    {
        if (Schema::hasTable('marketplace_email_logs')) {
            $count = 0;
            DB::table('marketplace_email_logs')
                ->whereIn('status', ['failed', 'bounced'])
                ->where('created_at', '>=', $threshold)
                ->orderBy('created_at')
                ->chunkById(1000, function ($rows) use (&$count) {
                    foreach ($rows as $row) {
                        $this->bufferRow([
                            'level' => 400,
                            'level_name' => 'ERROR',
                            'channel' => 'marketplace',
                            'source' => 'marketplace_email_log',
                            'message' => sprintf(
                                'Marketplace email %s: → %s — %s',
                                $row->status,
                                $row->to_email ?? '?',
                                $row->error_message ?? $row->subject ?? '(no detail)'
                            ),
                            'context' => [
                                'marketplace_email_log_id' => $row->id,
                                'to_email' => $row->to_email,
                                'status' => $row->status,
                                'error_message' => $row->error_message,
                                'template_slug' => $row->template_slug ?? null,
                            ],
                            'exception_class' => null,
                            'exception_file' => null,
                            'exception_line' => null,
                            'stack_trace' => null,
                            'datetime' => Carbon::parse($row->created_at ?? now()),
                        ]);
                        $count++;
                    }
                });
            $this->info("  marketplace_email_logs: {$count} entries imported");
        }

        if (Schema::hasTable('email_logs')) {
            $count = 0;
            DB::table('email_logs')
                ->where('status', 'failed')
                ->where('created_at', '>=', $threshold)
                ->orderBy('created_at')
                ->chunkById(1000, function ($rows) use (&$count) {
                    foreach ($rows as $row) {
                        $this->bufferRow([
                            'level' => 400,
                            'level_name' => 'ERROR',
                            'channel' => 'mail',
                            'source' => 'email_log',
                            'message' => 'Email failed: ' . ($row->error_message ?? '(no detail)'),
                            'context' => [
                                'email_log_id' => $row->id,
                                'status' => $row->status,
                                'error_message' => $row->error_message,
                            ],
                            'exception_class' => null,
                            'exception_file' => null,
                            'exception_line' => null,
                            'stack_trace' => null,
                            'datetime' => Carbon::parse($row->created_at ?? now()),
                        ]);
                        $count++;
                    }
                });
            $this->info("  email_logs: {$count} entries imported");
        }
    }

    protected function ingestWebhookLogs(Carbon $threshold): void
    {
        if (!Schema::hasTable('webhook_delivery_logs')) {
            $this->line('  webhook_delivery_logs: table not present');
            return;
        }

        $count = 0;
        DB::table('webhook_delivery_logs')
            ->where('success', false)
            ->where('created_at', '>=', $threshold)
            ->orderBy('created_at')
            ->chunkById(1000, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $this->bufferRow([
                        'level' => $row->status_code && $row->status_code >= 500 ? 400 : 300,
                        'level_name' => $row->status_code && $row->status_code >= 500 ? 'ERROR' : 'WARNING',
                        'channel' => 'webhooks',
                        'source' => 'webhook_log',
                        'message' => sprintf(
                            'Webhook delivery failed (%s): %s',
                            $row->status_code ?? 'no-response',
                            $row->error_message ?? 'no error message'
                        ),
                        'context' => [
                            'webhook_id' => $row->webhook_id,
                            'event' => $row->event,
                            'status_code' => $row->status_code,
                            'attempt' => $row->attempt,
                        ],
                        'exception_class' => null,
                        'exception_file' => null,
                        'exception_line' => null,
                        'stack_trace' => null,
                        'datetime' => Carbon::parse($row->created_at ?? now()),
                    ]);
                    $count++;
                }
            });

        $this->info("  webhook_delivery_logs: {$count} entries imported");
    }

    /**
     * Push a row into the buffer; flush every batchSize rows. Each row
     * gets classified once on the way in.
     */
    protected function bufferRow(array $record): void
    {
        $classification = $this->classifier->classify($record);

        $this->buffer[] = [
            'id' => (string) Str::uuid7(),
            'level' => (int) $record['level'],
            'level_name' => $record['level_name'] ?? 'WARNING',
            'channel' => $record['channel'] ?? null,
            'source' => $record['source'] ?? 'log',
            'category' => $classification->category,
            'subcategory' => $classification->subcategory,
            'message' => $record['message'],
            'fingerprint' => Fingerprinter::compute(
                $record['message'],
                $record['exception_class'] ?? null,
                $record['exception_file'] ?? null,
            ),
            'exception_class' => $record['exception_class'] ?? null,
            'exception_file' => $record['exception_file'] ?? null,
            'exception_line' => $record['exception_line'] ?? null,
            'stack_trace' => $record['stack_trace'] ?? null,
            'context' => json_encode($record['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            'request_url' => null,
            'request_method' => null,
            'request_ip' => null,
            'request_user_agent' => null,
            'request_user_type' => null,
            'request_user_id' => null,
            'tenant_id' => null,
            'marketplace_client_id' => null,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'acknowledged_note' => null,
            'created_at' => $record['datetime']->toDateTimeString(),
        ];

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    protected function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }
        try {
            DB::table('system_errors')->insert($this->buffer);
            $this->totalInserted += count($this->buffer);
        } catch (\Throwable $e) {
            $this->warn('  flush failed: ' . $e->getMessage());
        }
        $this->buffer = [];
    }
}
