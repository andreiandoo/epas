<?php

namespace App\Console\Commands;

use App\Models\MarketplaceDashboardSnapshot;
use Illuminate\Console\Command;

class UnfreezeMarketplaceDashboard extends Command
{
    protected $signature = 'dashboard:unfreeze
                            {--marketplace= : Only this marketplace client id}
                            {--month= : Only this month (Y-m)}';

    protected $description = 'Delete the stored values of closed months on the marketplace dashboard, so they are recomputed on the next view (e.g. after a late refund or a data fix).';

    public function handle(): int
    {
        $query = MarketplaceDashboardSnapshot::query();
        if ($id = $this->option('marketplace')) {
            $query->where('marketplace_client_id', $id);
        }
        if ($month = $this->option('month')) {
            $query->where('period', $month);
        }

        $this->info('Deleted ' . $query->delete() . ' stored value(s).');

        return self::SUCCESS;
    }
}
