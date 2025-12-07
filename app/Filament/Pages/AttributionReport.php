<?php

namespace App\Filament\Pages;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Tenant;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class AttributionReport extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-pointing-in';
    protected string $view = 'filament.pages.attribution-report';
    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Attribution Report';
    protected static ?string $navigationLabel = 'Attribution';

    public array $firstTouchAttribution = [];
    public array $lastTouchAttribution = [];
    public array $channelComparison = [];
    public array $conversionPaths = [];
    public array $assistedConversions = [];
    public array $timeToConversion = [];
    public array $touchpointAnalysis = [];
    public array $summary = [];

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    #[Url]
    public ?string $tenantId = null;

    #[Url]
    public string $attributionModel = 'last_touch';

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->subDays(30)->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->format('Y-m-d');
        $this->calculateAttribution();
    }

    public function updatedStartDate(): void
    {
        $this->calculateAttribution();
    }

    public function updatedEndDate(): void
    {
        $this->calculateAttribution();
    }

    public function updatedTenantId(): void
    {
        $this->calculateAttribution();
    }

    public function updatedAttributionModel(): void
    {
        $this->calculateAttribution();
    }

    protected function calculateAttribution(): void
    {
        $startDate = Carbon::parse($this->startDate)->startOfDay();
        $endDate = Carbon::parse($this->endDate)->endOfDay();
        $tenantId = $this->tenantId ? (int) $this->tenantId : null;

        // Get all conversions in the period
        $conversions = CoreCustomerEvent::purchases()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalConversions = $conversions->count();
        $totalRevenue = $conversions->sum('conversion_value');

        // === FIRST-TOUCH ATTRIBUTION ===
        // Credits the first interaction that brought the customer
        $this->firstTouchAttribution = CoreCustomer::purchasers()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->whereHas('events', fn($q) => $q->purchases()->whereBetween('created_at', [$startDate, $endDate]))
            ->selectRaw("
                CASE
                    WHEN first_gclid IS NOT NULL THEN 'Google Ads'
                    WHEN first_fbclid IS NOT NULL THEN 'Facebook Ads'
                    WHEN first_ttclid IS NOT NULL THEN 'TikTok Ads'
                    WHEN first_li_fat_id IS NOT NULL THEN 'LinkedIn Ads'
                    WHEN first_utm_source IS NOT NULL THEN first_utm_source
                    WHEN first_referrer IS NOT NULL AND first_referrer != '' THEN 'Referral'
                    ELSE 'Direct'
                END as channel,
                COUNT(*) as conversions,
                SUM(total_spent) as revenue
            ")
            ->groupBy('channel')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        // === LAST-TOUCH ATTRIBUTION ===
        // Credits the last interaction before conversion
        $this->lastTouchAttribution = CoreCustomerEvent::purchases()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                CASE
                    WHEN gclid IS NOT NULL THEN 'Google Ads'
                    WHEN fbclid IS NOT NULL THEN 'Facebook Ads'
                    WHEN ttclid IS NOT NULL THEN 'TikTok Ads'
                    WHEN li_fat_id IS NOT NULL THEN 'LinkedIn Ads'
                    WHEN utm_source IS NOT NULL THEN utm_source
                    WHEN referrer IS NOT NULL AND referrer != '' THEN 'Referral'
                    ELSE 'Direct'
                END as channel,
                COUNT(*) as conversions,
                SUM(conversion_value) as revenue
            ")
            ->groupBy('channel')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        // === CHANNEL COMPARISON ===
        // Compare first-touch vs last-touch
        $this->channelComparison = $this->buildChannelComparison();

        // === CONVERSION PATHS ===
        // Show common paths to conversion
        $this->conversionPaths = $this->analyzeConversionPaths($tenantId, $startDate, $endDate);

        // === ASSISTED CONVERSIONS ===
        // Channels that assisted but didn't get last-touch credit
        $this->assistedConversions = $this->calculateAssistedConversions($tenantId, $startDate, $endDate);

        // === TIME TO CONVERSION ===
        // How long from first visit to purchase
        $this->timeToConversion = CoreCustomer::purchasers()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->whereHas('events', fn($q) => $q->purchases()->whereBetween('created_at', [$startDate, $endDate]))
            ->whereNotNull('first_seen_at')
            ->whereNotNull('first_purchase_at')
            ->selectRaw("
                CASE
                    WHEN TIMESTAMPDIFF(HOUR, first_seen_at, first_purchase_at) < 1 THEN 'Same Session'
                    WHEN TIMESTAMPDIFF(DAY, first_seen_at, first_purchase_at) < 1 THEN 'Same Day'
                    WHEN TIMESTAMPDIFF(DAY, first_seen_at, first_purchase_at) < 7 THEN '1-7 Days'
                    WHEN TIMESTAMPDIFF(DAY, first_seen_at, first_purchase_at) < 30 THEN '7-30 Days'
                    ELSE '30+ Days'
                END as time_range,
                COUNT(*) as count
            ")
            ->groupBy('time_range')
            ->orderByRaw("FIELD(time_range, 'Same Session', 'Same Day', '1-7 Days', '7-30 Days', '30+ Days')")
            ->get()
            ->toArray();

        // === TOUCHPOINT ANALYSIS ===
        // Average touchpoints before conversion
        $this->touchpointAnalysis = [
            'avg_sessions' => CoreCustomer::purchasers()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->whereHas('events', fn($q) => $q->purchases()->whereBetween('created_at', [$startDate, $endDate]))
                ->avg('total_visits') ?? 0,
            'avg_page_views' => CoreCustomer::purchasers()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->whereHas('events', fn($q) => $q->purchases()->whereBetween('created_at', [$startDate, $endDate]))
                ->avg('total_pageviews') ?? 0,
        ];

        // === SUMMARY ===
        $this->summary = [
            'total_conversions' => $totalConversions,
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $totalConversions > 0 ? $totalRevenue / $totalConversions : 0,
            'period_days' => $startDate->diffInDays($endDate) + 1,
        ];
    }

    protected function buildChannelComparison(): array
    {
        $comparison = [];
        $firstTouch = collect($this->firstTouchAttribution)->keyBy('channel');
        $lastTouch = collect($this->lastTouchAttribution)->keyBy('channel');

        $allChannels = $firstTouch->keys()->merge($lastTouch->keys())->unique();

        foreach ($allChannels as $channel) {
            $ft = $firstTouch->get($channel, ['conversions' => 0, 'revenue' => 0]);
            $lt = $lastTouch->get($channel, ['conversions' => 0, 'revenue' => 0]);

            $comparison[] = [
                'channel' => $channel,
                'first_touch_conversions' => $ft['conversions'] ?? 0,
                'first_touch_revenue' => $ft['revenue'] ?? 0,
                'last_touch_conversions' => $lt['conversions'] ?? 0,
                'last_touch_revenue' => $lt['revenue'] ?? 0,
                'difference' => ($ft['conversions'] ?? 0) - ($lt['conversions'] ?? 0),
            ];
        }

        return collect($comparison)
            ->sortByDesc('first_touch_revenue')
            ->values()
            ->toArray();
    }

    protected function analyzeConversionPaths(?int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        // Get customers who converted and analyze their journey
        $paths = [];

        $convertedCustomers = CoreCustomer::purchasers()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->whereHas('events', fn($q) => $q->purchases()->whereBetween('created_at', [$startDate, $endDate]))
            ->limit(100)
            ->get();

        foreach ($convertedCustomers as $customer) {
            $path = [];

            // Add first touch
            if ($customer->first_gclid) {
                $path[] = 'Google Ads';
            } elseif ($customer->first_fbclid) {
                $path[] = 'Facebook Ads';
            } elseif ($customer->first_ttclid) {
                $path[] = 'TikTok Ads';
            } elseif ($customer->first_li_fat_id) {
                $path[] = 'LinkedIn Ads';
            } elseif ($customer->first_utm_source) {
                $path[] = ucfirst($customer->first_utm_source);
            } else {
                $path[] = 'Direct';
            }

            // Check if last touch was different
            if ($customer->last_gclid && !$customer->first_gclid) {
                $path[] = 'Google Ads';
            } elseif ($customer->last_fbclid && !$customer->first_fbclid) {
                $path[] = 'Facebook Ads';
            } elseif ($customer->last_utm_source && $customer->last_utm_source !== $customer->first_utm_source) {
                $path[] = ucfirst($customer->last_utm_source);
            }

            $pathKey = implode(' > ', array_unique($path));
            $paths[$pathKey] = ($paths[$pathKey] ?? 0) + 1;
        }

        arsort($paths);

        return array_map(fn($path, $count) => [
            'path' => $path,
            'count' => $count,
        ], array_keys(array_slice($paths, 0, 10)), array_slice($paths, 0, 10));
    }

    protected function calculateAssistedConversions(?int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        // Find channels that appear in first-touch but not last-touch
        // These are "assisting" channels
        $assisted = [];

        $customers = CoreCustomer::purchasers()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->whereHas('events', fn($q) => $q->purchases()->whereBetween('created_at', [$startDate, $endDate]))
            ->get();

        foreach ($customers as $customer) {
            $firstChannel = $this->getChannel($customer, 'first');
            $lastChannel = $this->getChannel($customer, 'last');

            // If first and last touch are different, first touch is an assist
            if ($firstChannel !== $lastChannel && $firstChannel) {
                $assisted[$firstChannel] = ($assisted[$firstChannel] ?? 0) + 1;
            }
        }

        arsort($assisted);

        return array_map(fn($channel, $count) => [
            'channel' => $channel,
            'assisted_conversions' => $count,
        ], array_keys($assisted), $assisted);
    }

    protected function getChannel($customer, string $type): ?string
    {
        $prefix = $type === 'first' ? 'first_' : 'last_';

        if ($customer->{$prefix . 'gclid'}) return 'Google Ads';
        if ($customer->{$prefix . 'fbclid'}) return 'Facebook Ads';
        if ($customer->{$prefix . 'ttclid'}) return 'TikTok Ads';
        if ($customer->{$prefix . 'li_fat_id'}) return 'LinkedIn Ads';
        if ($customer->{$prefix . 'utm_source'}) return ucfirst($customer->{$prefix . 'utm_source'});
        if ($customer->{$prefix . 'referrer'}) return 'Referral';

        return 'Direct';
    }

    public function getTenantOptions(): array
    {
        return ['' => 'All Tenants'] + Tenant::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
