<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldPromoCodeUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:cleanup
                            {--days=365 : Delete usage records older than X days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old promo code usage records and expired codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        // Delete old usage records
        $deletedUsage = DB::table('promo_code_usage')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        // Delete old metrics
        $deletedMetrics = DB::table('promo_code_metrics')
            ->where('date', '<', $cutoffDate->toDateString())
            ->delete();

        // Permanently delete soft-deleted promo codes older than threshold
        $deletedCodes = DB::table('promo_codes')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoffDate)
            ->delete();

        $this->info("Cleanup complete:");
        $this->line("  - Deleted {$deletedUsage} old usage record(s)");
        $this->line("  - Deleted {$deletedMetrics} old metric record(s)");
        $this->line("  - Permanently deleted {$deletedCodes} promo code(s)");

        return Command::SUCCESS;
    }
}
