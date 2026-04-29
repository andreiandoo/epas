<?php

namespace App\Console\Commands;

use App\Models\SystemError;
use Illuminate\Console\Command;

/**
 * Daily retention enforcement for the system_errors table. Reads the
 * per-severity retention windows from config('system_errors.retention')
 * and deletes anything older. A null retention keeps that severity
 * forever (escape hatch for compliance-sensitive deployments).
 *
 * Run: php artisan system-errors:prune
 *      php artisan system-errors:prune --dry-run
 */
class PruneSystemErrorsCommand extends Command
{
    protected $signature = 'system-errors:prune {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Delete system_errors rows older than the configured retention window per severity';

    public function handle(): int
    {
        $retention = (array) config('system_errors.retention', []);
        $dryRun = (bool) $this->option('dry-run');

        $bands = [
            'critical' => [500, 600],
            'error' => [400, 499],
            'warning' => [300, 399],
            'notice' => [250, 299],
            'info' => [200, 249],
            'debug' => [0, 199],
        ];

        $totalDeleted = 0;
        foreach ($bands as $name => [$min, $max]) {
            $days = $retention[$name] ?? null;
            if ($days === null) {
                $this->line("  {$name}: keep forever");
                continue;
            }
            $threshold = now()->subDays((int) $days);
            $query = SystemError::query()
                ->whereBetween('level', [$min, $max])
                ->where('created_at', '<', $threshold);

            $count = $query->count();
            if ($count === 0) {
                $this->line("  {$name}: nothing older than {$days}d");
                continue;
            }

            if ($dryRun) {
                $this->line("  {$name}: would delete {$count} rows older than {$days}d");
            } else {
                $deleted = $query->delete();
                $totalDeleted += $deleted;
                $this->info("  {$name}: deleted {$deleted} rows older than {$days}d");
            }
        }

        if ($dryRun) {
            $this->info('Dry-run complete. No rows deleted.');
        } else {
            $this->info("Done. Total deleted: {$totalDeleted}");
        }

        return self::SUCCESS;
    }
}
