<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetricsService
{
    /**
     * Track a microservice action
     */
    public function track(
        string $tenantId,
        string $microserviceSlug,
        string $action,
        bool $success = true,
        float $cost = 0,
        int $quantity = 1,
        array $metadata = []
    ): void {
        if (!config('microservices.metrics.enabled')) {
            return;
        }

        try {
            DB::table('microservice_metrics')->insert([
                'tenant_id' => $tenantId,
                'microservice_slug' => $microserviceSlug,
                'action' => $action,
                'status' => $success ? 'success' : 'failure',
                'cost' => $cost,
                'quantity' => $quantity,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                'created_at' => now(),
            ]);

            // Update daily summary asynchronously
            $this->updateDailySummary($tenantId, $microserviceSlug, now()->toDateString());

        } catch (\Exception $e) {
            Log::error('Failed to track metric', [
                'tenant_id' => $tenantId,
                'microservice' => $microserviceSlug,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update daily summary
     */
    protected function updateDailySummary(string $tenantId, string $microserviceSlug, string $date): void
    {
        $stats = DB::table('microservice_metrics')
            ->where('tenant_id', $tenantId)
            ->where('microservice_slug', $microserviceSlug)
            ->whereDate('created_at', $date)
            ->selectRaw('
                COUNT(*) as total_calls,
                SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as successful_calls,
                SUM(CASE WHEN status = "failure" THEN 1 ELSE 0 END) as failed_calls,
                SUM(cost) as total_cost,
                SUM(quantity) as total_quantity
            ')
            ->first();

        // Get breakdown by action
        $breakdown = DB::table('microservice_metrics')
            ->where('tenant_id', $tenantId)
            ->where('microservice_slug', $microserviceSlug)
            ->whereDate('created_at', $date)
            ->select('action', DB::raw('COUNT(*) as count'), DB::raw('SUM(cost) as cost'))
            ->groupBy('action')
            ->get()
            ->keyBy('action')
            ->map(fn ($item) => ['count' => $item->count, 'cost' => $item->cost])
            ->toArray();

        DB::table('microservice_usage_summary')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'microservice_slug' => $microserviceSlug,
                'date' => $date,
            ],
            [
                'total_calls' => $stats->total_calls ?? 0,
                'successful_calls' => $stats->successful_calls ?? 0,
                'failed_calls' => $stats->failed_calls ?? 0,
                'total_cost' => $stats->total_cost ?? 0,
                'total_quantity' => $stats->total_quantity ?? 0,
                'breakdown' => json_encode($breakdown),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Get usage statistics for a tenant
     */
    public function getUsageStats(string $tenantId, string $microserviceSlug, string $startDate, string $endDate): array
    {
        $summary = DB::table('microservice_usage_summary')
            ->where('tenant_id', $tenantId)
            ->where('microservice_slug', $microserviceSlug)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $totals = [
            'total_calls' => $summary->sum('total_calls'),
            'successful_calls' => $summary->sum('successful_calls'),
            'failed_calls' => $summary->sum('failed_calls'),
            'total_cost' => $summary->sum('total_cost'),
            'total_quantity' => $summary->sum('total_quantity'),
            'success_rate' => $summary->sum('total_calls') > 0
                ? round(($summary->sum('successful_calls') / $summary->sum('total_calls')) * 100, 2)
                : 0,
        ];

        return [
            'daily' => $summary,
            'totals' => $totals,
        ];
    }

    /**
     * Get all microservices usage for a tenant
     */
    public function getAllUsage(string $tenantId, string $startDate, string $endDate): array
    {
        $summary = DB::table('microservice_usage_summary')
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate, $endDate])
            ->select(
                'microservice_slug',
                DB::raw('SUM(total_calls) as total_calls'),
                DB::raw('SUM(successful_calls) as successful_calls'),
                DB::raw('SUM(failed_calls) as failed_calls'),
                DB::raw('SUM(total_cost) as total_cost'),
                DB::raw('SUM(total_quantity) as total_quantity')
            )
            ->groupBy('microservice_slug')
            ->get();

        return $summary->map(function ($item) {
            return [
                'microservice' => $item->microservice_slug,
                'total_calls' => $item->total_calls,
                'successful_calls' => $item->successful_calls,
                'failed_calls' => $item->failed_calls,
                'total_cost' => $item->total_cost,
                'total_quantity' => $item->total_quantity,
                'success_rate' => $item->total_calls > 0
                    ? round(($item->successful_calls / $item->total_calls) * 100, 2)
                    : 0,
            ];
        })->toArray();
    }

    /**
     * Clean up old metrics data
     */
    public function cleanup(int $retentionDays = null): int
    {
        $retentionDays = $retentionDays ?? config('microservices.metrics.retention_days', 90);

        $cutoffDate = now()->subDays($retentionDays);

        $deleted = DB::table('microservice_metrics')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        Log::info("Cleaned up {$deleted} old metric records");

        return $deleted;
    }
}
