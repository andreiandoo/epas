<?php

namespace App\Filament\Widgets;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignMetric;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdsCampaignPerformanceWidget extends StatsOverviewWidget
{
    public ?AdsCampaign $record = null;

    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        if (!$this->record) {
            return $this->getGlobalStats();
        }

        return $this->getCampaignStats($this->record);
    }

    protected function getCampaignStats(AdsCampaign $campaign): array
    {
        $trend = $this->getTrend($campaign);

        return [
            Stat::make('Total Spend', $this->formatCurrency($campaign->total_spend, $campaign->currency))
                ->description($campaign->budget_utilization . '% of budget used')
                ->descriptionIcon($campaign->budget_utilization > 80 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($campaign->budget_utilization > 90 ? 'danger' : 'primary')
                ->chart($trend['spend'] ?? []),

            Stat::make('Impressions', number_format($campaign->total_impressions))
                ->description("Reach: " . number_format($campaign->platformCampaigns->sum('reach')))
                ->chart($trend['impressions'] ?? []),

            Stat::make('Clicks', number_format($campaign->total_clicks))
                ->description("CTR: " . number_format((float) $campaign->avg_ctr, 2) . '%')
                ->chart($trend['clicks'] ?? []),

            Stat::make('Conversions', number_format($campaign->total_conversions))
                ->description("CPC: " . $this->formatCurrency($campaign->avg_cpc, $campaign->currency))
                ->color($campaign->total_conversions > 0 ? 'success' : 'gray')
                ->chart($trend['conversions'] ?? []),

            Stat::make('Revenue', $this->formatCurrency($campaign->total_revenue, $campaign->currency))
                ->description("ROAS: " . number_format((float) $campaign->roas, 2) . 'x')
                ->color((float) $campaign->roas >= 2 ? 'success' : ((float) $campaign->roas >= 1 ? 'warning' : 'danger'))
                ->chart($trend['revenue'] ?? []),

            Stat::make('CAC', $this->formatCurrency($campaign->cac, $campaign->currency))
                ->description('Cost per Acquisition')
                ->color((float) $campaign->cac > 0 && (float) $campaign->cac < 10 ? 'success' : 'warning'),
        ];
    }

    protected function getGlobalStats(): array
    {
        $activeCampaigns = AdsCampaign::running()->count();
        $totalSpend = AdsCampaign::running()->sum('total_spend');
        $totalRevenue = AdsCampaign::running()->sum('total_revenue');
        $totalConversions = AdsCampaign::running()->sum('total_conversions');
        $pendingRequests = \App\Models\AdsCampaign\AdsServiceRequest::where('status', 'pending')->count();

        return [
            Stat::make('Active Campaigns', $activeCampaigns)
                ->description('Currently running')
                ->icon('heroicon-o-megaphone')
                ->color('success'),

            Stat::make('Total Ad Spend', $this->formatCurrency($totalSpend))
                ->description('Across all active campaigns')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Total Revenue', $this->formatCurrency($totalRevenue))
                ->description('Attributed to ads')
                ->icon('heroicon-o-currency-euro')
                ->color('success'),

            Stat::make('Total Conversions', number_format($totalConversions))
                ->description('Tickets sold via ads')
                ->icon('heroicon-o-ticket'),

            Stat::make('Avg ROAS', $totalSpend > 0 ? number_format($totalRevenue / $totalSpend, 2) . 'x' : '-')
                ->description('Return on Ad Spend')
                ->icon('heroicon-o-chart-bar')
                ->color($totalSpend > 0 && ($totalRevenue / $totalSpend) >= 2 ? 'success' : 'warning'),

            Stat::make('Pending Requests', $pendingRequests)
                ->description('Awaiting review')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($pendingRequests > 0 ? 'warning' : 'gray'),
        ];
    }

    protected function getTrend(AdsCampaign $campaign): array
    {
        $metrics = AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', 'aggregated')
            ->orderBy('date')
            ->limit(14)
            ->get();

        return [
            'spend' => $metrics->pluck('spend')->map(fn ($v) => (float)$v)->toArray(),
            'impressions' => $metrics->pluck('impressions')->toArray(),
            'clicks' => $metrics->pluck('clicks')->toArray(),
            'conversions' => $metrics->pluck('conversions')->toArray(),
            'revenue' => $metrics->pluck('revenue')->map(fn ($v) => (float)$v)->toArray(),
        ];
    }

    protected function formatCurrency(float|string|null $value, string $currency = 'EUR'): string
    {
        $value = (float) ($value ?? 0);
        $symbol = match (strtoupper($currency)) {
            'EUR' => "\u{20AC}",
            'RON' => 'RON',
            'USD' => '$',
            'GBP' => "\u{00A3}",
            default => $currency,
        };
        return $symbol . ' ' . number_format($value, 2);
    }
}
