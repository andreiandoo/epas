<?php

namespace App\Logging;

use App\Models\SystemError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Single entry point used by the Monolog handler, the global exception
 * reporter (bootstrap/app.php), observers (failed jobs, email logs,
 * webhook logs, order status changes) and the backfill command.
 *
 * Receives a normalized record array, runs classification + enrichment,
 * and inserts a row in system_errors. Insert failures are swallowed so
 * a logging hiccup never blocks the application.
 */
class SystemErrorRecorder
{
    public function __construct(
        protected ErrorClassifier $classifier,
        protected RequestContextEnricher $enricher,
    ) {}

    /**
     * @param array{
     *   level:int,
     *   level_name?:?string,
     *   channel?:?string,
     *   source?:?string,
     *   message:string,
     *   context?:array,
     *   exception_class?:?string,
     *   exception_file?:?string,
     *   exception_line?:?int,
     *   stack_trace?:?string,
     *   datetime?:?Carbon,
     * } $record
     */
    public function record(array $record): void
    {
        try {
            $captureLevel = (int) config('system_errors.capture_level', 300);
            if (((int) $record['level']) < $captureLevel) {
                return;
            }

            $classification = $this->classifier->classify($record);
            $context = $this->sanitizeContext($record['context'] ?? []);
            $request = $this->enricher->capture();
            $message = self::truncate((string) $record['message'], (int) config('system_errors.limits.message', 8000));
            $stack = self::truncate($record['stack_trace'] ?? null, (int) config('system_errors.limits.stack_trace', 65000));

            $tenantId = $request['tenant_id'] ?? ($context['tenant_id'] ?? null);
            $clientId = $request['marketplace_client_id'] ?? ($context['marketplace_client_id'] ?? null);

            // Direct DB insert avoids re-entering Eloquent events / observers
            // when the recorder fires during the boot phase or inside an
            // exception handler.
            DB::table('system_errors')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid7(),
                'level' => (int) $record['level'],
                'level_name' => $record['level_name'] ?? self::levelName((int) $record['level']),
                'channel' => $record['channel'] ?? null,
                'source' => $record['source'] ?? 'log',
                'category' => $classification->category,
                'subcategory' => $classification->subcategory,
                'message' => $message,
                'fingerprint' => Fingerprinter::compute(
                    $message,
                    $record['exception_class'] ?? null,
                    $record['exception_file'] ?? null,
                ),
                'exception_class' => $record['exception_class'] ?? null,
                'exception_file' => self::truncate($record['exception_file'] ?? null, 500),
                'exception_line' => $record['exception_line'] ?? null,
                'stack_trace' => $stack,
                'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'request_url' => $request['request_url'],
                'request_method' => $request['request_method'],
                'request_ip' => $request['request_ip'],
                'request_user_agent' => $request['request_user_agent'],
                'request_user_type' => $request['request_user_type'],
                'request_user_id' => $request['request_user_id'],
                'tenant_id' => $tenantId,
                'marketplace_client_id' => $clientId,
                'acknowledged_at' => null,
                'acknowledged_by' => null,
                'acknowledged_note' => null,
                'created_at' => ($record['datetime'] ?? Carbon::now())->toDateTimeString(),
            ]);
        } catch (Throwable $e) {
            // Swallow — never block application flow because of a logging row.
            // "Table doesn't exist yet" happens during the deploy window
            // between git pull and migrate; not worth spamming error_log.
            $msg = $e->getMessage();
            if (stripos($msg, 'system_errors') !== false
                && (stripos($msg, 'does not exist') !== false
                    || stripos($msg, 'no such table') !== false
                    || stripos($msg, 'undefined table') !== false)) {
                return;
            }
            // Best-effort fallback: write to PHP error_log so we know the
            // recorder itself is broken if we ever investigate.
            @error_log('[SystemErrorRecorder] insert failed: ' . $msg);
        }
    }

    /**
     * Convenience entry point for capturing Throwables (used by the global
     * exception reporter and the order/email observers).
     */
    public function recordThrowable(
        Throwable $exception,
        int $level = 400,
        ?string $channel = null,
        string $source = 'exception',
        array $context = [],
    ): void {
        $this->record([
            'level' => $level,
            'level_name' => self::levelName($level),
            'channel' => $channel,
            'source' => $source,
            'message' => $exception->getMessage() ?: $exception::class,
            'context' => $context,
            'exception_class' => $exception::class,
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'datetime' => Carbon::now(),
        ]);
    }

    private function sanitizeContext(array $context): array
    {
        $redactKeys = array_map('strtolower', (array) config('system_errors.redact_keys', []));
        return $this->walkRedact($context, $redactKeys, 0);
    }

    private function walkRedact(array $value, array $redactKeys, int $depth): array
    {
        $maxKeys = (int) config('system_errors.limits.context_keys', 200);
        if ($depth > 4) {
            return ['<TRUNCATED_DEPTH>'];
        }

        $out = [];
        $i = 0;
        foreach ($value as $k => $v) {
            if ($i++ >= $maxKeys) {
                $out['<TRUNCATED>'] = true;
                break;
            }
            if (is_string($k) && in_array(strtolower($k), $redactKeys, true)) {
                $out[$k] = '[REDACTED]';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->walkRedact($v, $redactKeys, $depth + 1);
            } elseif (is_object($v)) {
                if (method_exists($v, '__toString')) {
                    $out[$k] = (string) $v;
                } else {
                    $out[$k] = '<' . $v::class . '>';
                }
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    public static function levelName(int $level): string
    {
        return match (true) {
            $level >= 600 => 'EMERGENCY',
            $level >= 550 => 'ALERT',
            $level >= 500 => 'CRITICAL',
            $level >= 400 => 'ERROR',
            $level >= 300 => 'WARNING',
            $level >= 250 => 'NOTICE',
            $level >= 200 => 'INFO',
            default => 'DEBUG',
        };
    }
}
