<?php

namespace App\Filament\Pages;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\PlatformAdAccount;
use App\Models\Platform\PlatformConversion;
use App\Models\Tenant;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class PlatformAnalytics extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-pie';
    protected string $view = 'filament.pages.platform-analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Platform Analytics';
    protected static ?string $navigationLabel = 'Analytics Dashboard';

    // Enable real-time polling every 30 seconds
    protected $listeners = ['refreshData' => 'refreshRealTimeData'];

    public function refreshRealTimeData(): void
    {
        // Only refresh real-time metrics (active visitors, recent conversions)
        $tenantId = $this->tenantId ? (int) $this->tenantId : null;

        $this->activeVisitors = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->active()
            ->count();

        $this->recentConversions = CoreCustomerEvent::with('coreCustomer')
            ->purchases()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($event) => [
                'id' => $event->id,
                'value' => $event->conversion_value,
                'source' => $event->getAttributionSource(),
                'customer' => $event->coreCustomer?->email ?? 'Anonymous',
                'time_ago' => $event->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    public array $overview = [];
    public array $trafficSources = [];
    public array $conversionStats = [];
    public array $customerMetrics = [];
    public array $tenantBreakdown = [];
    public array $adPlatformStats = [];
    public array $dailyTrend = [];
    public array $geoData = [];
    public array $deviceData = [];
    public int $activeVisitors = 0;
    public array $recentConversions = [];
    public array $topPages = [];

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?string $tenantId = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->subDays(30)->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->format('Y-m-d');
        $this->calculateMetrics();
    }

    public function updatedStartDate(): void
    {
        $this->calculateMetrics();
    }

    public function updatedEndDate(): void
    {
        $this->calculateMetrics();
    }

    public function updatedTenantId(): void
    {
        $this->calculateMetrics();
    }

    protected function calculateMetrics(): void
    {
        $startDate = Carbon::parse($this->startDate)->startOfDay();
        $endDate = Carbon::parse($this->endDate)->endOfDay();
        $tenantId = $this->tenantId ? (int) $this->tenantId : null;

        // Base queries with optional tenant filter
        $sessionQuery = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('started_at', [$startDate, $endDate]);

        $eventQuery = CoreCustomerEvent::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('created_at', [$startDate, $endDate]);

        $customerQuery = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // === OVERVIEW METRICS ===
        $this->overview = [
            'total_sessions' => (clone $sessionQuery)->count(),
            'total_visitors' => (clone $sessionQuery)->distinct('visitor_id')->count('visitor_id'),
            'total_page_views' => (clone $eventQuery)->pageViews()->count(),
            'total_purchases' => (clone $eventQuery)->purchases()->count(),
            'total_revenue' => (clone $eventQuery)->purchases()->sum('conversion_value'),
            'total_customers' => (clone $customerQuery)->count(),
            'new_customers' => (clone $customerQuery)->where('first_seen_at', '>=', $startDate)->count(),
            'returning_customers' => (clone $customerQuery)->where('total_visits', '>', 1)->count(),
        ];

        // Conversion rate
        $this->overview['conversion_rate'] = $this->overview['total_sessions'] > 0
            ? round(($this->overview['total_purchases'] / $this->overview['total_sessions']) * 100, 2)
            : 0;

        // Average order value
        $this->overview['avg_order_value'] = $this->overview['total_purchases'] > 0
            ? round($this->overview['total_revenue'] / $this->overview['total_purchases'], 2)
            : 0;

        // === ACTIVE VISITORS (real-time) ===
        $this->activeVisitors = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->active()
            ->count();

        // === TRAFFIC SOURCES ===
        $this->trafficSources = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('started_at', [$startDate, $endDate])
            ->selectRaw("
                CASE
                    WHEN gclid IS NOT NULL THEN 'Google Ads'
                    WHEN fbclid IS NOT NULL THEN 'Facebook Ads'
                    WHEN ttclid IS NOT NULL THEN 'TikTok Ads'
                    WHEN utm_source IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(utm_source, 1, 1)), LOWER(SUBSTRING(utm_source, 2)))
                    WHEN referrer IS NOT NULL AND referrer != '' THEN 'Referral'
                    ELSE 'Direct'
                END as source,
                COUNT(*) as sessions,
                COUNT(DISTINCT visitor_id) as visitors
            ")
            ->groupBy('source')
            ->orderByDesc('sessions')
            ->limit(10)
            ->get()
            ->toArray();

        // === CONVERSION STATS BY SOURCE ===
        $this->conversionStats = CoreCustomerEvent::purchases()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                CASE
                    WHEN gclid IS NOT NULL THEN 'Google Ads'
                    WHEN fbclid IS NOT NULL THEN 'Facebook Ads'
                    WHEN ttclid IS NOT NULL THEN 'TikTok Ads'
                    WHEN li_fat_id IS NOT NULL THEN 'LinkedIn Ads'
                    WHEN utm_source IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(utm_source, 1, 1)), LOWER(SUBSTRING(utm_source, 2)))
                    ELSE 'Direct/Organic'
                END as source,
                COUNT(*) as conversions,
                SUM(conversion_value) as revenue
            ")
            ->groupBy('source')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        // === CUSTOMER METRICS ===
        $this->customerMetrics = [
            'total' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->count(),
            'with_email' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->whereNotNull('email')->count(),
            'purchasers' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('total_orders', '>', 0)->count(),
            'repeat_buyers' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('total_orders', '>', 1)->count(),
            'high_value' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('total_spent', '>=', 500)->count(),
            'avg_rfm_score' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->whereNotNull('rfm_score')->avg('rfm_score') ?? 0,
            'total_lifetime_value' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->sum('total_spent'),
            'avg_orders_per_customer' => CoreCustomer::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))->where('total_orders', '>', 0)->avg('total_orders') ?? 0,
        ];

        // Calculate identified rate
        $this->customerMetrics['identification_rate'] = $this->customerMetrics['total'] > 0
            ? round(($this->customerMetrics['with_email'] / $this->customerMetrics['total']) * 100, 1)
            : 0;

        // === TENANT BREAKDOWN (only for platform-wide view) ===
        if (!$tenantId) {
            $this->tenantBreakdown = CoreCustomerEvent::purchases()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('tenant_id')
                ->join('tenants', 'tenants.id', '=', 'core_customer_events.tenant_id')
                ->selectRaw('tenants.name as tenant_name, tenants.id as tenant_id, COUNT(*) as purchases, SUM(conversion_value) as revenue')
                ->groupBy('tenant_id', 'tenant_name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->toArray();
        }

        // === AD PLATFORM STATS ===
        $this->adPlatformStats = [];
        $accounts = PlatformAdAccount::active()->get();
        foreach ($accounts as $account) {
            $conversionCount = PlatformConversion::where('platform_ad_account_id', $account->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $totalValue = PlatformConversion::where('platform_ad_account_id', $account->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('value');

            $this->adPlatformStats[] = [
                'platform' => PlatformAdAccount::PLATFORMS[$account->platform] ?? $account->platform,
                'account_name' => $account->account_name ?? $account->account_id,
                'conversions' => $conversionCount,
                'revenue' => $totalValue,
                'token_status' => $account->isTokenExpired() ? 'expired' : ($account->isTokenExpiringSoon() ? 'expiring' : 'valid'),
            ];
        }

        // === DAILY TREND (last 30 days) ===
        $this->dailyTrend = CoreCustomerEvent::when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE(created_at) as date,
                         SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as page_views,
                         SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchases,
                         SUM(CASE WHEN event_type = 'purchase' THEN conversion_value ELSE 0 END) as revenue")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        // === GEO DATA ===
        $this->geoData = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('started_at', [$startDate, $endDate])
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as visitors')
            ->groupBy('country_code')
            ->orderByDesc('visitors')
            ->limit(10)
            ->get()
            ->toArray();

        // === DEVICE DATA ===
        $this->deviceData = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('started_at', [$startDate, $endDate])
            ->selectRaw("device_type, COUNT(*) as count")
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        // === RECENT CONVERSIONS ===
        $this->recentConversions = CoreCustomerEvent::with('coreCustomer')
            ->purchases()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($event) => [
                'id' => $event->id,
                'value' => $event->conversion_value,
                'source' => $event->getAttributionSource(),
                'customer' => $event->coreCustomer?->email ?? 'Anonymous',
                'time_ago' => $event->created_at->diffForHumans(),
            ])
            ->toArray();

        // === TOP PAGES ===
        $this->topPages = CoreCustomerEvent::pageViews()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('page_url, page_title, COUNT(*) as views')
            ->groupBy('page_url', 'page_title')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getTenantOptions(): array
    {
        return ['' => 'All Tenants'] + Tenant::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function getNavigationBadge(): ?string
    {
        $active = CoreSession::active()->count();
        return $active > 0 ? (string) $active : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}
