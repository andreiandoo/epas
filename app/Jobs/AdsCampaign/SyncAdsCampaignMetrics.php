<?php

namespace App\Jobs\AdsCampaign;

use App\Services\AdsCampaign\MetricsAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAdsCampaignMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function handle(MetricsAggregator $aggregator): void
    {
        Log::info('Starting ads campaign metrics sync');

        try {
            $aggregator->syncAllActiveCampaigns();
            Log::info('Ads campaign metrics sync completed');
        } catch (\Exception $e) {
            Log::error('Ads campaign metrics sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
