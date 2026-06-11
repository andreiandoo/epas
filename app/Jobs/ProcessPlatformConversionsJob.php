<?php

namespace App\Jobs;

use App\Services\Platform\PlatformTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPlatformConversionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PlatformTrackingService $trackingService): void
    {
        Log::info('Starting platform conversions processing');

        try {
            $results = $trackingService->processPendingConversions();

            Log::info('Platform conversions processed', [
                'processed' => $results['processed'],
                'success' => $results['success'],
                'failed' => $results['failed'],
                'by_platform' => $results['by_platform'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process platform conversions', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
