<?php

namespace App\Jobs;

use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\DataRetentionPolicy;
use App\Models\Platform\PlatformConversion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataRetentionCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    protected int $batchSize = 1000;

    public function handle(): void
    {
        Log::info('Starting data retention cleanup');

        $results = [
            'sessions' => $this->cleanupSessions(),
            'events' => $this->cleanupEvents(),
            'conversions' => $this->cleanupConversions(),
            'pageviews' => $this->cleanupPageViews(),
        ];

        Log::info('Completed data retention cleanup', $results);
    }

    protected function cleanupSessions(): array
    {
        $policy = DataRetentionPolicy::getOrCreateDefault(DataRetentionPolicy::DATA_TYPE_SESSIONS);

        if (!$policy->is_active) {
            return ['skipped' => true, 'reason' => 'Policy inactive'];
        }

        $cutoffDate = $policy->getCutoffDate();
        $totalDeleted = 0;

        do {
            $deleted = match ($policy->archive_strategy) {
                DataRetentionPolicy::STRATEGY_DELETE => $this->deleteSessions($cutoffDate),
                DataRetentionPolicy::STRATEGY_ANONYMIZE => $this->anonymizeSessions($cutoffDate),
                default => 0,
            };

            $totalDeleted += $deleted;
        } while ($deleted >= $this->batchSize);

        $policy->recordCleanup($totalDeleted);

        return [
            'deleted' => $totalDeleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'strategy' => $policy->archive_strategy,
        ];
    }

    protected function cleanupEvents(): array
    {
        $policy = DataRetentionPolicy::getOrCreateDefault(DataRetentionPolicy::DATA_TYPE_EVENTS);

        if (!$policy->is_active) {
            return ['skipped' => true, 'reason' => 'Policy inactive'];
        }

        $cutoffDate = $policy->getCutoffDate();
        $totalDeleted = 0;

        do {
            $deleted = match ($policy->archive_strategy) {
                DataRetentionPolicy::STRATEGY_DELETE => $this->deleteEvents($cutoffDate),
                DataRetentionPolicy::STRATEGY_ANONYMIZE => $this->anonymizeEvents($cutoffDate),
                default => 0,
            };

            $totalDeleted += $deleted;
        } while ($deleted >= $this->batchSize);

        $policy->recordCleanup($totalDeleted);

        return [
            'deleted' => $totalDeleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'strategy' => $policy->archive_strategy,
        ];
    }

    protected function cleanupConversions(): array
    {
        $policy = DataRetentionPolicy::getOrCreateDefault(DataRetentionPolicy::DATA_TYPE_CONVERSIONS);

        if (!$policy->is_active) {
            return ['skipped' => true, 'reason' => 'Policy inactive'];
        }

        $cutoffDate = $policy->getCutoffDate();

        // Only delete failed/abandoned conversions older than cutoff
        // Keep successful conversions longer for reporting
        $deleted = PlatformConversion::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['failed', 'abandoned', 'skipped'])
            ->limit($this->batchSize * 10)
            ->delete();

        $policy->recordCleanup($deleted);

        return [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'note' => 'Only failed/abandoned conversions',
        ];
    }

    protected function cleanupPageViews(): array
    {
        $policy = DataRetentionPolicy::getOrCreateDefault(DataRetentionPolicy::DATA_TYPE_PAGEVIEWS);

        if (!$policy->is_active) {
            return ['skipped' => true, 'reason' => 'Policy inactive'];
        }

        $cutoffDate = $policy->getCutoffDate();

        // Delete old page view events specifically
        $deleted = CoreCustomerEvent::where('event_type', 'page_view')
            ->where('created_at', '<', $cutoffDate)
            ->where('is_converted', false) // Keep events that led to conversion
            ->limit($this->batchSize * 10)
            ->delete();

        $policy->recordCleanup($deleted);

        return [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->toDateString(),
            'note' => 'Non-converting page views only',
        ];
    }

    protected function deleteSessions(\DateTime $cutoffDate): int
    {
        return CoreSession::where('started_at', '<', $cutoffDate)
            ->where('is_converted', false) // Keep sessions that converted
            ->limit($this->batchSize)
            ->delete();
    }

    protected function anonymizeSessions(\DateTime $cutoffDate): int
    {
        $affected = CoreSession::where('started_at', '<', $cutoffDate)
            ->whereNotNull('ip_address')
            ->limit($this->batchSize)
            ->update([
                'ip_address' => null,
                'visitor_id' => DB::raw("CONCAT('anon_', id)"),
            ]);

        return $affected;
    }

    protected function deleteEvents(\DateTime $cutoffDate): int
    {
        return CoreCustomerEvent::where('created_at', '<', $cutoffDate)
            ->where('is_converted', false)
            ->whereNotIn('event_type', ['purchase', 'sign_up']) // Keep important events
            ->limit($this->batchSize)
            ->delete();
    }

    protected function anonymizeEvents(\DateTime $cutoffDate): int
    {
        $affected = CoreCustomerEvent::where('created_at', '<', $cutoffDate)
            ->whereNotNull('ip_address')
            ->limit($this->batchSize)
            ->update([
                'ip_address' => null,
                'event_data' => null,
            ]);

        return $affected;
    }
}
