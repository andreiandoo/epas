<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activitylog:cleanup
                            {--days=10 : Delete activity logs older than X days}
                            {--log-name=tenant : Only delete logs from this log name (default: tenant)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old activity log records (default: older than 10 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $logName = $this->option('log-name');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up activity logs older than {$days} days...");
        $this->info("Cutoff date: {$cutoffDate->toDateTimeString()}");

        // Build the query
        $query = DB::table('activity_log')
            ->where('created_at', '<', $cutoffDate);

        // Optionally filter by log name
        if ($logName !== 'all') {
            $query->where('log_name', $logName);
        }

        $deletedCount = $query->delete();

        $this->info("Cleanup complete:");
        $this->line("  - Deleted {$deletedCount} activity log record(s)");

        if ($deletedCount > 0) {
            $this->info("Old activity logs have been removed successfully.");
        } else {
            $this->info("No activity logs older than {$days} days found.");
        }

        return Command::SUCCESS;
    }
}
