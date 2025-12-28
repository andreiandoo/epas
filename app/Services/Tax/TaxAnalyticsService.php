<?php

namespace App\Services\Tax;

use App\Models\Tax\GeneralTax;
use App\Models\Tax\LocalTax;
use App\Models\Tax\TaxCollectionRecord;
use App\Models\Tax\TaxAnalyticsCache;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaxAnalyticsService
{
    /**
     * Get dashboard summary statistics
     */
    public function getDashboardSummary(int $tenantId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfMonth();

        $previousStart = $startDate->copy()->subMonth()->startOfMonth();
        $previousEnd = $startDate->copy()->subMonth()->endOfMonth();

        // Current period stats
        $currentStats = $this->getPeriodStats($tenantId, $startDate, $endDate);

        // Previous period stats for comparison
        $previousStats = $this->getPeriodStats($tenantId, $previousStart, $previousEnd);

        // Calculate trends
        $trends = $this->calculateTrends($currentStats, $previousStats);

        return [
            'current' => $currentStats,
            'previous' => $previousStats,
            'trends' => $trends,
            'period' => [
                'start' => $startDate->format('M j, Y'),
                'end' => $endDate->format('M j, Y'),
            ],
        ];
    }

    /**
     * Get statistics for a period
     */
    protected function getPeriodStats(int $tenantId, Carbon $start, Carbon $end): array
    {
        $records = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end);

        $stats = $records->selectRaw('
            COUNT(*) as transaction_count,
            COALESCE(SUM(taxable_amount), 0) as total_taxable,
            COALESCE(SUM(tax_amount), 0) as total_collected,
            COALESCE(AVG(CASE WHEN taxable_amount > 0 THEN (tax_amount / taxable_amount) * 100 END), 0) as avg_rate
        ')->first();

        $byType = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->selectRaw('tax_type, SUM(tax_amount) as total')
            ->groupBy('tax_type')
            ->pluck('total', 'tax_type')
            ->toArray();

        $exemptionStats = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->where('exemption_applied', true)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(original_tax_amount - tax_amount), 0) as saved
            ')
            ->first();

        return [
            'transaction_count' => (int) $stats->transaction_count,
            'total_taxable' => (float) $stats->total_taxable,
            'total_collected' => (float) $stats->total_collected,
            'average_rate' => round((float) $stats->avg_rate, 2),
            'by_type' => [
                'general' => (float) ($byType['general'] ?? 0),
                'local' => (float) ($byType['local'] ?? 0),
            ],
            'exemptions' => [
                'count' => (int) $exemptionStats->count,
                'amount_saved' => (float) $exemptionStats->saved,
            ],
        ];
    }

    /**
     * Calculate trends between periods
     */
    protected function calculateTrends(array $current, array $previous): array
    {
        $calculateChange = function ($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }
            return round((($current - $previous) / $previous) * 100, 1);
        };

        return [
            'transaction_count' => $calculateChange($current['transaction_count'], $previous['transaction_count']),
            'total_collected' => $calculateChange($current['total_collected'], $previous['total_collected']),
            'average_rate' => round($current['average_rate'] - $previous['average_rate'], 2),
        ];
    }

    /**
     * Get daily collection data for chart
     */
    public function getDailyCollectionChart(int $tenantId, Carbon $start, Carbon $end): array
    {
        $data = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->selectRaw('collection_date, tax_type, SUM(tax_amount) as total')
            ->groupBy('collection_date', 'tax_type')
            ->orderBy('collection_date')
            ->get();

        $labels = [];
        $generalData = [];
        $localData = [];

        $currentDate = $start->copy();
        while ($currentDate <= $end) {
            $dateStr = $currentDate->format('Y-m-d');
            $labels[] = $currentDate->format('M j');

            $dayData = $data->where('collection_date', $dateStr);
            $generalData[] = (float) $dayData->where('tax_type', 'general')->sum('total');
            $localData[] = (float) $dayData->where('tax_type', 'local')->sum('total');

            $currentDate->addDay();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'General Taxes',
                    'data' => $generalData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
                [
                    'label' => 'Local Taxes',
                    'data' => $localData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                ],
            ],
        ];
    }

    /**
     * Get collection by country
     */
    public function getCollectionByCountry(int $tenantId, Carbon $start, Carbon $end): array
    {
        return TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->whereNotNull('country')
            ->selectRaw('country, SUM(tax_amount) as total, COUNT(*) as count')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'country' => $row->country,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Get top taxes by collection
     */
    public function getTopTaxes(int $tenantId, Carbon $start, Carbon $end, int $limit = 10): array
    {
        return TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->selectRaw('tax_type, tax_id, tax_name, SUM(tax_amount) as total, COUNT(*) as count, AVG(rate) as avg_rate')
            ->groupBy('tax_type', 'tax_id', 'tax_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'type' => $row->tax_type,
                'id' => $row->tax_id,
                'name' => $row->tax_name,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
                'avg_rate' => round((float) $row->avg_rate, 2),
            ])
            ->toArray();
    }

    /**
     * Get monthly summary for the year
     */
    public function getMonthlySummary(int $tenantId, int $year): array
    {
        $data = TaxCollectionRecord::forTenant($tenantId)
            ->whereYear('collection_date', $year)
            ->selectRaw('MONTH(collection_date) as month, SUM(tax_amount) as total, COUNT(*) as count')
            ->groupBy(DB::raw('MONTH(collection_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'month' => Carbon::create($year, $i, 1)->format('M'),
                'total' => (float) ($data[$i]->total ?? 0),
                'count' => (int) ($data[$i]->count ?? 0),
            ];
        }

        return $months;
    }

    /**
     * Get exemption report
     */
    public function getExemptionReport(int $tenantId, Carbon $start, Carbon $end): array
    {
        $data = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->where('exemption_applied', true)
            ->selectRaw('
                exemption_name,
                COUNT(*) as usage_count,
                SUM(original_tax_amount) as original_total,
                SUM(tax_amount) as actual_total,
                SUM(original_tax_amount - tax_amount) as savings
            ')
            ->groupBy('exemption_name')
            ->orderByDesc('savings')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->exemption_name,
                'usage_count' => (int) $row->usage_count,
                'original_total' => (float) $row->original_total,
                'actual_total' => (float) $row->actual_total,
                'savings' => (float) $row->savings,
            ])
            ->toArray();

        $totals = [
            'usage_count' => array_sum(array_column($data, 'usage_count')),
            'savings' => array_sum(array_column($data, 'savings')),
        ];

        return [
            'exemptions' => $data,
            'totals' => $totals,
        ];
    }

    /**
     * Generate export data for tax filing
     */
    public function generateTaxFilingExport(int $tenantId, Carbon $start, Carbon $end): array
    {
        $summary = $this->getPeriodStats($tenantId, $start, $end);
        $byCountry = $this->getCollectionByCountry($tenantId, $start, $end);
        $exemptions = $this->getExemptionReport($tenantId, $start, $end);

        // Get detailed breakdown by tax
        $byTax = TaxCollectionRecord::forTenant($tenantId)
            ->forPeriod($start, $end)
            ->selectRaw('
                tax_type, tax_id, tax_name, rate, rate_type,
                country, county, city,
                SUM(taxable_amount) as taxable_total,
                SUM(tax_amount) as tax_total,
                COUNT(*) as transaction_count
            ')
            ->groupBy('tax_type', 'tax_id', 'tax_name', 'rate', 'rate_type', 'country', 'county', 'city')
            ->orderBy('tax_type')
            ->orderByDesc('tax_total')
            ->get()
            ->toArray();

        return [
            'period' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'summary' => $summary,
            'by_country' => $byCountry,
            'by_tax' => $byTax,
            'exemptions' => $exemptions,
            'generated_at' => now()->toISOString(),
        ];
    }
}
