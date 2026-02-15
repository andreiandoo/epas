<?php

namespace App\Jobs\AdsCampaign;

use App\Services\AdsCampaign\ReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAdsReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function handle(ReportGenerator $generator): void
    {
        Log::info('Starting scheduled ads report generation');

        try {
            $generator->generateScheduledReports();
            Log::info('Scheduled ads reports generated');
        } catch (\Exception $e) {
            Log::error('Ads report generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
