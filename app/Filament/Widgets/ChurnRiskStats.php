<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomer;
use App\Services\Platform\ChurnPredictionService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChurnRiskStats extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $churnService = app(ChurnPredictionService::class);

        // Get at-risk customers by level
        $criticalCount = $this->getCustomerCountByRisk(80, 100);
        $highRiskCount = $this->getCustomerCountByRisk(60, 80);
        $mediumRiskCount = $this->getCustomerCountByRisk(40, 60);

        // Calculate total value at risk (high + critical)
        $valueAtRisk = CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->where('churn_risk_score', '>=', 60)
            ->sum('lifetime_value');

        // 30-day churn rate
        $thirtyDaysAgo = now()->subDays(30);
        $sixtyDaysAgo = now()->subDays(60);

        $totalPurchasers = CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->count();

        $recentlyChurned = CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->where('last_seen_at', '<', $thirtyDaysAgo)
            ->where('last_seen_at', '>=', $sixtyDaysAgo)
            ->count();

        $churnRate = $totalPurchasers > 0 ? round(($recentlyChurned / $totalPurchasers) * 100, 1) : 0;

        // Trend: compare this month vs last month
        $lastMonthChurned = CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->where('last_seen_at', '<', $sixtyDaysAgo)
            ->where('last_seen_at', '>=', now()->subDays(90))
            ->count();

        $churnTrend = $lastMonthChurned > 0
            ? round((($recentlyChurned - $lastMonthChurned) / $lastMonthChurned) * 100, 1)
            : 0;

        return [
            Stat::make('Critical Risk', number_format($criticalCount))
                ->description('80%+ churn probability')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger')
                ->chart($this->getRiskTrend('critical')),

            Stat::make('High Risk', number_format($highRiskCount))
                ->description('60-80% churn probability')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Medium Risk', number_format($mediumRiskCount))
                ->description('40-60% churn probability')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('info'),

            Stat::make('Value at Risk', '$' . number_format($valueAtRisk, 0))
                ->description('High + Critical customers LTV')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('danger'),

            Stat::make('30-Day Churn Rate', $churnRate . '%')
                ->description($this->formatTrend($churnTrend))
                ->descriptionIcon($churnTrend <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->color($churnTrend <= 0 ? 'success' : 'danger'),

            Stat::make('Recently Churned', number_format($recentlyChurned))
                ->description('Last 30 days')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('gray'),
        ];
    }

    protected function getCustomerCountByRisk(int $minScore, int $maxScore): int
    {
        return CoreCustomer::query()
            ->notMerged()
            ->notAnonymized()
            ->where('churn_risk_score', '>=', $minScore)
            ->where('churn_risk_score', '<', $maxScore)
            ->count();
    }

    protected function formatTrend(float $trend): string
    {
        if ($trend === 0.0) {
            return 'No change vs last month';
        }
        $direction = $trend > 0 ? '+' : '';
        return "{$direction}{$trend}% vs last month";
    }

    protected function getRiskTrend(string $level): array
    {
        $trend = [];
        $minScore = match ($level) {
            'critical' => 80,
            'high' => 60,
            'medium' => 40,
            default => 0,
        };
        $maxScore = match ($level) {
            'critical' => 100,
            'high' => 80,
            'medium' => 60,
            default => 40,
        };

        // Get weekly counts for the last 6 weeks
        for ($i = 5; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();

            // This is an approximation - in production you'd track historical scores
            $count = CoreCustomer::query()
                ->notMerged()
                ->notAnonymized()
                ->where('churn_risk_score', '>=', $minScore)
                ->where('churn_risk_score', '<', $maxScore)
                ->count();

            $trend[] = $count;
        }

        return $trend;
    }
}
