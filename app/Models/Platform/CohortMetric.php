<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class CohortMetric extends Model
{
    protected $fillable = [
        'cohort_period',
        'cohort_type',
        'period_offset',
        'customers_count',
        'active_customers',
        'purchasers_count',
        'total_revenue',
        'retention_rate',
        'avg_revenue_per_customer',
    ];

    protected $casts = [
        'customers_count' => 'integer',
        'active_customers' => 'integer',
        'purchasers_count' => 'integer',
        'total_revenue' => 'decimal:2',
        'retention_rate' => 'decimal:2',
        'avg_revenue_per_customer' => 'decimal:2',
    ];

    const TYPE_MONTH = 'month';
    const TYPE_WEEK = 'week';

    public function scopeMonthly($query)
    {
        return $query->where('cohort_type', self::TYPE_MONTH);
    }

    public function scopeWeekly($query)
    {
        return $query->where('cohort_type', self::TYPE_WEEK);
    }

    public function scopeForCohort($query, string $cohortPeriod)
    {
        return $query->where('cohort_period', $cohortPeriod);
    }

    public function scopeAtOffset($query, int $offset)
    {
        return $query->where('period_offset', $offset);
    }

    /**
     * Calculate and store cohort metrics
     */
    public static function calculateForCohort(string $cohortPeriod, string $type = self::TYPE_MONTH): void
    {
        $cohortField = $type === self::TYPE_WEEK ? 'cohort_week' : 'cohort_month';

        // Get all customers in this cohort
        $cohortCustomers = CoreCustomer::where($cohortField, $cohortPeriod)
            ->notMerged()
            ->get();

        $baseCount = $cohortCustomers->count();

        if ($baseCount === 0) {
            return;
        }

        // Calculate metrics for each period offset (0-12)
        for ($offset = 0; $offset <= 12; $offset++) {
            $periodStart = self::getPeriodStart($cohortPeriod, $type, $offset);
            $periodEnd = self::getPeriodEnd($cohortPeriod, $type, $offset);

            // Skip future periods
            if ($periodStart > now()) {
                continue;
            }

            $activeCustomers = 0;
            $purchasers = 0;
            $totalRevenue = 0;

            foreach ($cohortCustomers as $customer) {
                // Check if customer was active in this period
                $events = CoreCustomerEvent::where('core_customer_id', $customer->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count();

                if ($events > 0) {
                    $activeCustomers++;
                }

                // Check for purchases in this period
                $purchases = CoreCustomerEvent::where('core_customer_id', $customer->id)
                    ->where('event_type', 'purchase')
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->sum('conversion_value');

                if ($purchases > 0) {
                    $purchasers++;
                    $totalRevenue += $purchases;
                }
            }

            $retentionRate = $baseCount > 0 ? ($activeCustomers / $baseCount) * 100 : 0;
            $avgRevenue = $baseCount > 0 ? $totalRevenue / $baseCount : 0;

            static::updateOrCreate(
                [
                    'cohort_period' => $cohortPeriod,
                    'cohort_type' => $type,
                    'period_offset' => $offset,
                ],
                [
                    'customers_count' => $baseCount,
                    'active_customers' => $activeCustomers,
                    'purchasers_count' => $purchasers,
                    'total_revenue' => $totalRevenue,
                    'retention_rate' => round($retentionRate, 2),
                    'avg_revenue_per_customer' => round($avgRevenue, 2),
                ]
            );
        }
    }

    protected static function getPeriodStart(string $cohortPeriod, string $type, int $offset): \DateTime
    {
        if ($type === self::TYPE_WEEK) {
            [$year, $week] = explode('-W', $cohortPeriod);
            $date = new \DateTime();
            $date->setISODate((int)$year, (int)$week);
            $date->modify("+{$offset} weeks");
            return $date;
        }

        $date = \DateTime::createFromFormat('Y-m', $cohortPeriod);
        $date->modify("+{$offset} months");
        $date->modify('first day of this month');
        return $date;
    }

    protected static function getPeriodEnd(string $cohortPeriod, string $type, int $offset): \DateTime
    {
        $start = self::getPeriodStart($cohortPeriod, $type, $offset);

        if ($type === self::TYPE_WEEK) {
            $start->modify('+6 days 23:59:59');
        } else {
            $start->modify('last day of this month 23:59:59');
        }

        return $start;
    }

    /**
     * Get cohort retention data formatted for display
     */
    public static function getRetentionMatrix(string $type = self::TYPE_MONTH, int $cohortCount = 12): array
    {
        $cohorts = [];
        $currentPeriod = $type === self::TYPE_WEEK
            ? now()->format('Y-\WW')
            : now()->format('Y-m');

        for ($i = $cohortCount - 1; $i >= 0; $i--) {
            $cohortPeriod = $type === self::TYPE_WEEK
                ? now()->subWeeks($i)->format('Y-\WW')
                : now()->subMonths($i)->format('Y-m');

            $metrics = static::where('cohort_period', $cohortPeriod)
                ->where('cohort_type', $type)
                ->orderBy('period_offset')
                ->get()
                ->keyBy('period_offset');

            $cohorts[$cohortPeriod] = [
                'period' => $cohortPeriod,
                'base_count' => $metrics->get(0)?->customers_count ?? 0,
                'retention' => [],
                'revenue' => [],
            ];

            for ($offset = 0; $offset <= 12; $offset++) {
                $metric = $metrics->get($offset);
                $cohorts[$cohortPeriod]['retention'][$offset] = $metric?->retention_rate ?? null;
                $cohorts[$cohortPeriod]['revenue'][$offset] = $metric?->avg_revenue_per_customer ?? null;
            }
        }

        return $cohorts;
    }
}
