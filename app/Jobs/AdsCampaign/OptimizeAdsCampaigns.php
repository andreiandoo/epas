<?php

namespace App\Jobs\AdsCampaign;

use App\Services\AdsCampaign\AdsCampaignManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OptimizeAdsCampaigns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 120;
    public int $timeout = 600;

    public function handle(AdsCampaignManager $manager): void
    {
        Log::info('Starting ads campaign optimization cycle');

        try {
            $manager->optimizeActiveCampaigns();
            Log::info('Ads campaign optimization completed');
        } catch (\Exception $e) {
            Log::error('Ads campaign optimization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
