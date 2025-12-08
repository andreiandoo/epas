<?php

namespace App\Jobs;

use App\Models\Platform\CohortMetric;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CalculateCohortMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public string $cohortType = 'month', // 'month' or 'week'
        public int $periodsBack = 12, // How many cohorts back to calculate
        public int $retentionPeriods = 12, // How many periods to track retention
        public ?int $tenantId = null
    ) {}

    public function handle(): void
    {
        Log::info('Starting cohort metrics calculation', [
            'cohort_type' => $this->cohortType,
            'periods_back' => $this->periodsBack,
            'tenant_id' => $this->tenantId,
        ]);

        $startTime = microtime(true);

        // Calculate cohorts for each period
        for ($i = 0; $i < $this->periodsBack; $i++) {
            $cohortDate = $this->cohortType === 'month'
                ? now()->subMonths($i)->startOfMonth()
                : now()->subWeeks($i)->startOfWeek();

            $cohortPeriod = $this->cohortType === 'month'
                ? $cohortDate->format('Y-m')
                : $cohortDate->format('Y-\WW');

            $this->calculateCohortMetrics($cohortPeriod, $cohortDate);
        }

        $duration = round(microtime(true) - $startTime, 2);
        Log::info('Cohort metrics calculation completed', [
            'duration_seconds' => $duration,
            'periods_calculated' => $this->periodsBack,
        ]);
    }

    protected function calculateCohortMetrics(string $cohortPeriod, Carbon $cohortDate): void
    {
        // Get customers in this cohort
        $cohortField = $this->cohortType === 'month' ? 'cohort_month' : 'cohort_week';

        $cohortCustomers = CoreCustomer::query()
            ->when($this->tenantId, fn($q) => $q->fromTenant($this->tenantId))
            ->where($cohortField, $cohortPeriod)
            ->notMerged()
            ->notAnonymized()
            ->get();

        if ($cohortCustomers->isEmpty()) {
            return;
        }

        $cohortSize = $cohortCustomers->count();
        $cohortCustomerIds = $cohortCustomers->pluck('id')->toArray();

        // Calculate metrics for each retention period
        for ($offset = 0; $offset <= $this->retentionPeriods; $offset++) {
            $periodStart = $this->cohortType === 'month'
                ? $cohortDate->copy()->addMonths($offset)->startOfMonth()
                : $cohortDate->copy()->addWeeks($offset)->startOfWeek();

            $periodEnd = $this->cohortType === 'month'
                ? $periodStart->copy()->endOfMonth()
                : $periodStart->copy()->endOfWeek();

            // Skip future periods
            if ($periodStart->isFuture()) {
                continue;
            }

            // Calculate active customers in this period
            $activeCustomers = CoreCustomerEvent::query()
                ->whereIn('core_customer_id', $cohortCustomerIds)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->distinct('core_customer_id')
                ->count('core_customer_id');

            // Calculate revenue in this period
            $periodRevenue = CoreCustomerEvent::query()
                ->whereIn('core_customer_id', $cohortCustomerIds)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->where('is_converted', true)
                ->sum('conversion_value');

            // Calculate orders in this period
            $periodOrders = CoreCustomerEvent::query()
                ->whereIn('core_customer_id', $cohortCustomerIds)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->where('event_type', 'purchase')
                ->count();

            // Calculate retention rate
            $retentionRate = $cohortSize > 0 ? round(($activeCustomers / $cohortSize) * 100, 2) : 0;

            // Calculate revenue per customer
            $revenuePerCustomer = $cohortSize > 0 ? round($periodRevenue / $cohortSize, 2) : 0;

            // Upsert the metric
            CohortMetric::updateOrCreate(
                [
                    'cohort_type' => $this->cohortType,
                    'cohort_period' => $cohortPeriod,
                    'period_offset' => $offset,
                    'tenant_id' => $this->tenantId,
                ],
                [
                    'customers_count' => $cohortSize,
                    'active_customers' => $activeCustomers,
                    'retention_rate' => $retentionRate,
                    'total_revenue' => $periodRevenue,
                    'total_orders' => $periodOrders,
                    'revenue_per_customer' => $revenuePerCustomer,
                    'average_order_value' => $periodOrders > 0 ? round($periodRevenue / $periodOrders, 2) : 0,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'calculated_at' => now(),
                ]
            );
        }
    }

    public function tags(): array
    {
        return ['analytics', 'cohorts', $this->cohortType];
    }
}
