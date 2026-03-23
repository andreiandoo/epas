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

        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_event_stats');
        $this->info('Materialized views refreshed.');

        return self::SUCCESS;
    }
}
