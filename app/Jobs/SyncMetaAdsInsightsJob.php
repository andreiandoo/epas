<?php

namespace App\Jobs;

use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use App\Services\Integrations\FacebookCapi\FacebookMarketingApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncMetaAdsInsightsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $backoff = 300;
    public int $timeout = 600;

    /**
     * @param int $connectionId  facebook_capi_connections.id
     * @param int $lookbackDays  pull insights for the last N days
     */
    public function __construct(
        public int $connectionId,
        public int $lookbackDays = 7
    ) {
    }

    public function handle(FacebookMarketingApiService $svc): void
    {
        $connection = FacebookCapiConnection::find($this->connectionId);
        if (!$connection) return;
        if ($connection->status !== 'active') return;
        if (!$connection->ad_account_id) return;

        $account = $svc->syncAccount($connection);
        if (!$account || $account->last_sync_status === 'failed') return;

        $svc->syncCampaigns($account, $connection);

        $svc->syncInsights(
            $account,
            $connection,
            Carbon::today()->subDays($this->lookbackDays),
            Carbon::today()
        );
    }
}
