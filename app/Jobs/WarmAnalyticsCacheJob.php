<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Platform\AnalyticsCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmAnalyticsCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public ?int $tenantId = null
    ) {}

    public function handle(AnalyticsCacheService $cacheService): void
    {
        Log::info('Starting analytics cache warm-up', [
            'tenant_id' => $this->tenantId,
        ]);

        $startTime = microtime(true);

        try {
            if ($this->tenantId) {
                // Warm cache for specific tenant
                $cacheService->warmUp($this->tenantId);
            } else {
                // Warm cache for platform-wide data
                $cacheService->warmUp(null);

                // Optionally warm cache for all active tenants
                Tenant::where('is_active', true)->each(function ($tenant) use ($cacheService) {
                    $cacheService->warmUp($tenant->id);
                });
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Analytics cache warm-up completed', [
                'tenant_id' => $this->tenantId,
                'duration_seconds' => $duration,
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics cache warm-up failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['analytics', 'cache', 'warm-up'];
    }
}
