<?php

namespace App\Console\Commands;

use App\Jobs\SyncMetaAdsInsightsJob;
use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use Illuminate\Console\Command;

class SyncMetaAdsInsightsCommand extends Command
{
    protected $signature = 'ads:sync-meta-insights {--connection= : Limit to a single facebook_capi_connections.id} {--days=7 : Lookback window in days}';
    protected $description = 'Pull ads accounts, campaigns and daily insights from Meta Marketing API for every active CAPI connection with an ad_account_id';

    public function handle(): int
    {
        $query = FacebookCapiConnection::where('status', 'active')
            ->whereNotNull('ad_account_id');

        if ($id = $this->option('connection')) {
            $query->where('id', (int) $id);
        }

        $connections = $query->get();
        $days = (int) $this->option('days');

        $this->info("Dispatching {$connections->count()} ads insights sync jobs (lookback={$days}d).");

        foreach ($connections as $c) {
            SyncMetaAdsInsightsJob::dispatch($c->id, $days);
        }

        return self::SUCCESS;
    }
}
