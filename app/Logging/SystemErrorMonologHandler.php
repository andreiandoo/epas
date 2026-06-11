<?php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that mirrors records into the system_errors table.
 *
 * Wired up via Log::extend() in AppServiceProvider so Laravel's existing
 * Monolog channels (daily, security, marketplace) get this as a parallel
 * sink without disturbing their file output.
 */
class SystemErrorMonologHandler extends AbstractProcessingHandler
{
    public function __construct(?int $level = null, bool $bubble = true)
    {
        // Default capture level read at construct-time. The recorder
        // re-checks per-record using the latest config value, so dynamic
        // changes still take effect without restarting.
        $level = $level ?? Level::Warning->value;
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            // Resolve recorder lazily — service container may not be ready
            // when boot-time logs fire (e.g., config cache rebuild errors).
            if (!app()->bound(SystemErrorRecorder::class)) {
                return;
            }
            /** @var SystemErrorRecorder $recorder */
            $recorder = app(SystemErrorRecorder::class);

            $context = $record->context;
            $exception = $context['exception'] ?? null;

            $payload = [
                'level' => $record->level->value,
                'level_name' => $record->level->getName(),
                'channel' => $record->channel,
                'source' => 'log',
                'message' => $record->message,
                'context' => $this->scrubExceptionFromContext($context),
                'exception_class' => $exception ? $exception::class : null,
                'exception_file' => $exception?->getFile(),
                'exception_line' => $exception?->getLine(),
                'stack_trace' => $exception?->getTraceAsString(),
                'datetime' => \Carbon\Carbon::instance($record->datetime),
            ];

            $recorder->record($payload);
        } catch (\Throwable $e) {
            // Never throw out of a Monolog handler — Laravel would treat it
            // as a logging failure and recurse. Best effort: write to stderr.
            @error_log('[SystemErrorMonologHandler] write failed: ' . $e->getMessage());
        }
    }

    /**
     * Strip the Throwable from the context array (we already extract its
     * useful fields into top-level columns), but keep everything else.
     */
    private function scrubExceptionFromContext(array $context): array
    {
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            unset($context['exception']);
        }
        return $context;
    }
}
