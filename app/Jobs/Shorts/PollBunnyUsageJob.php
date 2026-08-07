<?php

namespace App\Jobs\Shorts;

use App\Services\Shorts\CostGuardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads month-to-date bandwidth from Bunny and feeds the cost guard (D8).
 *
 * Without this the first signal that a feed went viral is the invoice.
 */
class PollBunnyUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function handle(CostGuardService $guard): void
    {
        $apiKey = (string) config('services.bunny.stream_api_key', '');

        if ($apiKey === '' || $guard->capGb() <= 0) {
            // No credentials or no cap configured — nothing to guard.
            return;
        }

        try {
            $response = Http::withHeaders(['AccessKey' => $apiKey, 'accept' => 'application/json'])
                ->timeout(20)
                ->get('https://api.bunny.net/statistics', [
                    'dateFrom' => now()->startOfMonth()->toDateString(),
                    'dateTo' => now()->toDateString(),
                ]);

            if (! $response->successful()) {
                return;
            }

            // Bunny reports bandwidth in bytes.
            $bytes = (float) ($response->json('TotalBandwidthUsed') ?? 0);
            $guard->recordUsage($bytes / 1_073_741_824);

            $guard->logStatus();
        } catch (\Throwable $e) {
            Log::warning('PollBunnyUsageJob: could not read usage', ['error' => $e->getMessage()]);
        }
    }
}
