<?php

namespace App\Filament\Pages;

use App\Models\Platform\CohortMetric;
use App\Models\Platform\CoreCustomer;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CohortAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected string $view = 'filament.pages.cohort-analysis';

    protected static ?string $navigationLabel = 'Cohort Analysis';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Cohort Analysis';

    #[Url]
    public string $cohortType = 'month';

    #[Url]
    public int $cohortCount = 12;

    #[Url]
    public string $metricType = 'retention';

    public array $cohortData = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->loadCohortData();
    }

    public function updatedCohortType(): void
    {
        $this->loadCohortData();
    }

    public function updatedCohortCount(): void
    {
        $this->loadCohortData();
    }

    public function updatedMetricType(): void
    {
        // Just triggers re-render, data is already loaded
    }

    public function loadCohortData(): void
    {
        // Get cohort data from database or calculate on the fly
        $this->cohortData = $this->calculateCohortData();
        $this->summary = $this->calculateSummary();
    }

    protected function calculateCohortData(): array
    {
        $cohorts = [];
        $cohortField = $this->cohortType === 'week' ? 'cohort_week' : 'cohort_month';

        // Get distinct cohorts
        $distinctCohorts = CoreCustomer::whereNotNull($cohortField)
            ->notMerged()
            ->select($cohortField)
            ->distinct()
            ->orderByDesc($cohortField)
            ->limit($this->cohortCount)
            ->pluck($cohortField)
            ->reverse()
            ->values();

        foreach ($distinctCohorts as $cohortPeriod) {
            // Get base cohort size
            $cohortCustomers = CoreCustomer::where($cohortField, $cohortPeriod)
                ->notMerged()
                ->get();

            $baseCount = $cohortCustomers->count();

            if ($baseCount === 0) {
                continue;
            }

            $customerIds = $cohortCustomers->pluck('id')->toArray();

            $retention = [];
            $revenue = [];
            $purchasers = [];

            // Calculate metrics for each period offset
            for ($offset = 0; $offset <= min(12, $this->cohortCount); $offset++) {
                $periodStart = $this->getPeriodStart($cohortPeriod, $offset);
                $periodEnd = $this->getPeriodEnd($cohortPeriod, $offset);

                // Skip future periods
                if ($periodStart > now()) {
                    $retention[$offset] = null;
                    $revenue[$offset] = null;
                    $purchasers[$offset] = null;
                    continue;
                }

                // Active customers in this period
                $activeCount = DB::table('core_customer_events')
                    ->whereIn('core_customer_id', $customerIds)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->distinct('core_customer_id')
                    ->count('core_customer_id');

                // Purchasers and revenue
                $purchaseData = DB::table('core_customer_events')
                    ->whereIn('core_customer_id', $customerIds)
                    ->where('event_type', 'purchase')
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->selectRaw('COUNT(DISTINCT core_customer_id) as purchasers, COALESCE(SUM(conversion_value), 0) as revenue')
                    ->first();

                $retention[$offset] = $baseCount > 0 ? round(($activeCount / $baseCount) * 100, 1) : 0;
                $revenue[$offset] = $baseCount > 0 ? round(($purchaseData->revenue ?? 0) / $baseCount, 2) : 0;
                $purchasers[$offset] = $purchaseData->purchasers ?? 0;
            }

            $cohorts[] = [
                'period' => $cohortPeriod,
                'period_label' => $this->formatPeriodLabel($cohortPeriod),
                'base_count' => $baseCount,
                'retention' => $retention,
                'revenue' => $revenue,
                'purchasers' => $purchasers,
            ];
        }

        return $cohorts;
    }

    protected function calculateSummary(): array
    {
        if (empty($this->cohortData)) {
            return [];
        }

        $avgRetentionByOffset = [];
        $avgRevenueByOffset = [];

        for ($offset = 0; $offset <= 12; $offset++) {
            $retentionValues = [];
            $revenueValues = [];

            foreach ($this->cohortData as $cohort) {
                if (isset($cohort['retention'][$offset]) && $cohort['retention'][$offset] !== null) {
                    $retentionValues[] = $cohort['retention'][$offset];
                }
                if (isset($cohort['revenue'][$offset]) && $cohort['revenue'][$offset] !== null) {
                    $revenueValues[] = $cohort['revenue'][$offset];
                }
            }

            $avgRetentionByOffset[$offset] = !empty($retentionValues)
                ? round(array_sum($retentionValues) / count($retentionValues), 1)
                : null;

            $avgRevenueByOffset[$offset] = !empty($revenueValues)
                ? round(array_sum($revenueValues) / count($revenueValues), 2)
                : null;
        }

        return [
            'total_cohorts' => count($this->cohortData),
            'total_customers' => array_sum(array_column($this->cohortData, 'base_count')),
            'avg_retention' => $avgRetentionByOffset,
            'avg_revenue' => $avgRevenueByOffset,
        ];
    }

    protected function getPeriodStart(string $cohortPeriod, int $offset): \DateTime
    {
        if ($this->cohortType === 'week') {
            if (preg_match('/(\d{4})-W(\d{2})/', $cohortPeriod, $matches)) {
                $date = new \DateTime();
                $date->setISODate((int)$matches[1], (int)$matches[2]);
                $date->modify("+{$offset} weeks");
                $date->setTime(0, 0, 0);
                return $date;
            }
        }

        $date = \DateTime::createFromFormat('Y-m', $cohortPeriod);
        if ($date) {
            $date->modify("+{$offset} months");
            $date->modify('first day of this month');
            $date->setTime(0, 0, 0);
        }
        return $date ?? new \DateTime();
    }

    protected function getPeriodEnd(string $cohortPeriod, int $offset): \DateTime
    {
        $start = $this->getPeriodStart($cohortPeriod, $offset);

        if ($this->cohortType === 'week') {
            $start->modify('+6 days 23:59:59');
        } else {
            $start->modify('last day of this month 23:59:59');
        }

        return $start;
    }

    protected function formatPeriodLabel(string $period): string
    {
        if ($this->cohortType === 'week') {
            return $period;
        }

        try {
            $date = \DateTime::createFromFormat('Y-m', $period);
            return $date ? $date->format('M Y') : $period;
        } catch (\Exception $e) {
            return $period;
        }
    }

    public function getRetentionColor(float $rate): string
    {
        if ($rate >= 80) return 'bg-success-500';
        if ($rate >= 60) return 'bg-success-400';
        if ($rate >= 40) return 'bg-warning-400';
        if ($rate >= 20) return 'bg-warning-500';
        return 'bg-danger-400';
    }

    public function getOffsetLabels(): array
    {
        $labels = [];
        $prefix = $this->cohortType === 'week' ? 'W' : 'M';

        for ($i = 0; $i <= 12; $i++) {
            $labels[] = $prefix . $i;
        }

        return $labels;
    }
}
