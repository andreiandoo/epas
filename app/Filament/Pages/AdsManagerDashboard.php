<?php

namespace App\Filament\Pages;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignMetric;
use App\Models\AdsCampaign\AdsServiceRequest;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class AdsManagerDashboard extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected string $view = 'filament.pages.ads-manager-dashboard';
    protected static \UnitEnum|string|null $navigationGroup = 'Ads Manager';
    protected static ?int $navigationSort = 0;
    protected static ?string $title = 'Ads Dashboard';
    protected static ?string $navigationLabel = 'Dashboard';

    public array $summary = [];
    public array $platformComparison = [];
    public array $dailyTrend = [];
    public array $topCampaigns = [];
    public array $activeCampaigns = [];
    public array $pendingRequests = [];
    public array $recentOptimizations = [];

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->subDays(30)->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->format('Y-m-d');
        $this->loadDashboardData();
    }

    public function updatedStartDate(): void
    {
        $this->loadDashboardData();
    }

    public function updatedEndDate(): void
    {
        $this->loadDashboardData();
    }

    protected function loadDashboardData(): void
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $this->loadSummary($start, $end);
        $this->loadPlatformComparison($start, $end);
        $this->loadDailyTrend($start, $end);
        $this->loadTopCampaigns($start, $end);
        $this->loadActiveCampaigns();
        $this->loadPendingRequests();
        $this->loadRecentOptimizations();
    }

    protected function loadSummary(Carbon $start, Carbon $end): void
    {
        $metrics = AdsCampaignMetric::where('platform', 'aggregated')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(spend) as total_spend,
                SUM(revenue) as total_revenue,
                SUM(tickets_sold) as total_tickets,
                SUM(new_customers) as total_new_customers
            ')
            ->first();

        $activeCampaigns = AdsCampaign::where('status', 'active')->count();
        $totalSpend = (float) ($metrics?->total_spend ?? 0);
        $totalRevenue = (float) ($metrics?->total_revenue ?? 0);
        $totalConversions = (int) ($metrics?->total_conversions ?? 0);
        $totalClicks = (int) ($metrics?->total_clicks ?? 0);

        $this->summary = [
            'active_campaigns' => $activeCampaigns,
            'total_spend' => $totalSpend,
            'total_revenue' => $totalRevenue,
            'total_impressions' => (int) ($metrics?->total_impressions ?? 0),
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'total_tickets' => (int) ($metrics?->total_tickets ?? 0),
            'total_new_customers' => (int) ($metrics?->total_new_customers ?? 0),
            'avg_ctr' => $totalClicks > 0 && ($metrics?->total_impressions ?? 0) > 0
                ? round(($totalClicks / (float) $metrics->total_impressions) * 100, 2)
                : 0,
            'avg_cpc' => $totalClicks > 0 ? round($totalSpend / $totalClicks, 2) : 0,
            'roas' => $totalSpend > 0 ? round($totalRevenue / $totalSpend, 2) : 0,
            'cac' => $totalConversions > 0 ? round($totalSpend / $totalConversions, 2) : 0,
            'profit' => $totalRevenue - $totalSpend,
            'pending_requests' => AdsServiceRequest::where('status', 'pending')->count(),
        ];
    }

    protected function loadPlatformComparison(Carbon $start, Carbon $end): void
    {
        $platforms = AdsCampaignMetric::where('platform', '!=', 'aggregated')
            ->whereBetween('date', [$start, $end])
            ->groupBy('platform')
            ->selectRaw('
                platform,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(spend) as spend,
                SUM(revenue) as revenue
            ')
            ->get();

        $this->platformComparison = $platforms->map(function ($p) {
            $clicks = (int) $p->clicks;
            $impressions = (int) $p->impressions;
            $spend = (float) $p->spend;
            $revenue = (float) $p->revenue;
            $conversions = (int) $p->conversions;

            return [
                'platform' => ucfirst($p->platform),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'spend' => $spend,
                'revenue' => $revenue,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'cpc' => $clicks > 0 ? round($spend / $clicks, 2) : 0,
                'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0,
                'conversion_rate' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
            ];
        })->toArray();
    }

    protected function loadDailyTrend(Carbon $start, Carbon $end): void
    {
        $daily = AdsCampaignMetric::where('platform', 'aggregated')
            ->whereBetween('date', [$start, $end])
            ->groupBy('date')
            ->selectRaw('
                date,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(spend) as spend,
                SUM(revenue) as revenue
            ')
            ->orderBy('date')
            ->get();

        $this->dailyTrend = $daily->map(fn ($d) => [
            'date' => Carbon::parse($d->date)->format('M d'),
            'date_full' => $d->date,
            'impressions' => (int) $d->impressions,
            'clicks' => (int) $d->clicks,
            'conversions' => (int) $d->conversions,
            'spend' => (float) $d->spend,
            'revenue' => (float) $d->revenue,
        ])->toArray();
    }

    protected function loadTopCampaigns(Carbon $start, Carbon $end): void
    {
        $this->topCampaigns = AdsCampaign::whereIn('status', ['active', 'completed', 'paused'])
            ->withSum(['metrics as period_spend' => function ($q) use ($start, $end) {
                $q->where('platform', 'aggregated')->whereBetween('date', [$start, $end]);
            }], 'spend')
            ->withSum(['metrics as period_revenue' => function ($q) use ($start, $end) {
                $q->where('platform', 'aggregated')->whereBetween('date', [$start, $end]);
            }], 'revenue')
            ->withSum(['metrics as period_conversions' => function ($q) use ($start, $end) {
                $q->where('platform', 'aggregated')->whereBetween('date', [$start, $end]);
            }], 'conversions')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'total_budget' => (float) $c->total_budget,
                'spend' => (float) ($c->period_spend ?? $c->total_spend ?? 0),
                'revenue' => (float) ($c->period_revenue ?? $c->total_revenue ?? 0),
                'conversions' => (int) ($c->period_conversions ?? $c->total_conversions ?? 0),
                'roas' => $c->roas ? round((float) $c->roas, 2) : 0,
                'platforms' => $c->target_platforms ?? [],
                'health_score' => $this->calculateHealthScore($c),
            ])
            ->toArray();
    }

    protected function loadActiveCampaigns(): void
    {
        $this->activeCampaigns = AdsCampaign::where('status', 'active')
            ->with('event')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'event_name' => $c->event?->getTranslation('title', 'en') ?? $c->event?->getTranslation('title', 'ro') ?? '-',
                'budget_used_percent' => $c->total_budget > 0 ? round(($c->spent_budget / $c->total_budget) * 100) : 0,
                'daily_budget' => (float) $c->daily_budget,
                'roas' => $c->roas ? round((float) $c->roas, 2) : 0,
                'total_conversions' => (int) ($c->total_conversions ?? 0),
                'days_remaining' => $c->end_date ? max(0, now()->diffInDays($c->end_date, false)) : null,
                'health_score' => $this->calculateHealthScore($c),
            ])
            ->toArray();
    }

    protected function loadPendingRequests(): void
    {
        $this->pendingRequests = AdsServiceRequest::whereIn('status', ['pending', 'under_review'])
            ->with(['tenant', 'event'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'tenant_name' => $r->tenant?->name ?? '-',
                'event_name' => $r->event?->getTranslation('title', 'en') ?? '-',
                'budget' => (float) $r->budget,
                'currency' => $r->currency,
                'status' => $r->status,
                'created_at' => $r->created_at->diffForHumans(),
                'platforms' => $r->target_platforms ?? [],
            ])
            ->toArray();
    }

    protected function loadRecentOptimizations(): void
    {
        $this->recentOptimizations = DB::table('ads_optimization_logs')
            ->join('ads_campaigns', 'ads_campaigns.id', '=', 'ads_optimization_logs.campaign_id')
            ->select([
                'ads_optimization_logs.*',
                'ads_campaigns.name as campaign_name',
            ])
            ->orderByDesc('ads_optimization_logs.created_at')
            ->limit(8)
            ->get()
            ->map(fn ($o) => [
                'campaign_name' => $o->campaign_name,
                'action' => str_replace('_', ' ', ucfirst($o->action)),
                'description' => $o->description,
                'source' => $o->source,
                'platform' => $o->platform ?? '-',
                'created_at' => Carbon::parse($o->created_at)->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Calculate a holistic health score (0-100) for a campaign.
     */
    public function calculateHealthScore(AdsCampaign $campaign): int
    {
        $scores = [];
        $weights = [];

        // ROAS Score (0-100) — weight: 30%
        $roas = (float) ($campaign->roas ?? 0);
        $roasScore = min(100, ($roas / 3) * 100); // 3x ROAS = 100
        $scores[] = $roasScore;
        $weights[] = 0.30;

        // CTR Score (0-100) — weight: 20%
        $ctr = (float) ($campaign->avg_ctr ?? 0);
        $ctrScore = min(100, ($ctr / 2) * 100); // 2% CTR = 100
        $scores[] = $ctrScore;
        $weights[] = 0.20;

        // CPC Score (0-100, inverse) — weight: 15%
        $cpc = (float) ($campaign->avg_cpc ?? 0);
        $maxCpc = (float) ($campaign->optimization_rules['max_cpc'] ?? 3.00);
        $cpcScore = $cpc > 0 ? min(100, (($maxCpc - $cpc) / $maxCpc) * 100) : 50;
        $scores[] = max(0, $cpcScore);
        $weights[] = 0.15;

        // Budget Pacing Score (0-100) — weight: 15%
        if ($campaign->total_budget > 0 && $campaign->start_date && $campaign->end_date) {
            $totalDays = max(1, $campaign->start_date->diffInDays($campaign->end_date));
            $elapsedDays = max(1, $campaign->start_date->diffInDays(now()));
            $expectedSpendPct = min(1, $elapsedDays / $totalDays);
            $actualSpendPct = (float) $campaign->spent_budget / (float) $campaign->total_budget;

            $pacingRatio = $expectedSpendPct > 0 ? $actualSpendPct / $expectedSpendPct : 1;
            // Perfect pacing = 1.0. Deviations reduce score.
            $pacingScore = max(0, 100 - abs(1 - $pacingRatio) * 100);
        } else {
            $pacingScore = 50;
        }
        $scores[] = $pacingScore;
        $weights[] = 0.15;

        // Conversion Rate Score (0-100) — weight: 20%
        $convRate = $campaign->total_clicks > 0
            ? ($campaign->total_conversions / $campaign->total_clicks) * 100
            : 0;
        $convScore = min(100, ($convRate / 5) * 100); // 5% conv rate = 100
        $scores[] = $convScore;
        $weights[] = 0.20;

        // Weighted average
        $weightedSum = 0;
        $weightTotal = 0;
        foreach ($scores as $i => $score) {
            $weightedSum += $score * $weights[$i];
            $weightTotal += $weights[$i];
        }

        return (int) round($weightTotal > 0 ? $weightedSum / $weightTotal : 0);
    }

    public function getHealthLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            $score >= 20 => 'Needs Attention',
            default => 'Critical',
        };
    }

    public function getHealthColor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'text-green-600',
            $score >= 60 => 'text-blue-600',
            $score >= 40 => 'text-yellow-600',
            $score >= 20 => 'text-orange-600',
            default => 'text-red-600',
        };
    }
}
