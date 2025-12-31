<?php

namespace App\Jobs\Tracking;

use App\Services\Tracking\PersonTaggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ApplyAutoTaggingRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        protected ?int $tenantId = null
    ) {
        $this->onQueue('tracking-low');
    }

    public function handle(): void
    {
        Log::info('ApplyAutoTaggingRulesJob: Starting', ['tenant_id' => $this->tenantId ?? 'all']);

        $service = new PersonTaggingService();

        // Process expired tags first
        $expiredCount = $service->processExpiredTags();
        Log::info("ApplyAutoTaggingRulesJob: Processed {$expiredCount} expired tags");

        // Run auto-tagging rules
        $results = $service->runAutoTaggingRules($this->tenantId);

        $totalAssigned = 0;
        $successCount = 0;
        $errorCount = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $totalAssigned += $result['count'];
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        Log::info('ApplyAutoTaggingRulesJob: Completed', [
            'tenant_id' => $this->tenantId ?? 'all',
            'rules_processed' => count($results),
            'rules_successful' => $successCount,
            'rules_failed' => $errorCount,
            'tags_assigned' => $totalAssigned,
        ]);
    }
}
