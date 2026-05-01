<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshMaterializedViews extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'views:refresh';

    /**
     * The console command description.
     */
    protected $description = 'Refresh PostgreSQL materialized views';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Materialized views are only supported on PostgreSQL.');

            return self::SUCCESS;
        }

        // Skip silently if the view doesn't exist (fresh install, dev DB, or
        // mid-migration) instead of crashing the cron with exit 1.
        $exists = (int) DB::selectOne(
            "SELECT COUNT(*) AS c FROM pg_matviews WHERE matviewname = 'mv_event_stats'"
        )->c > 0;

        if (!$exists) {
            $this->info('Materialized view mv_event_stats not found; skipping.');
            return self::SUCCESS;
        }

        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_event_stats');
        $this->info('Materialized views refreshed.');

        return self::SUCCESS;
    }
}
