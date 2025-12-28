<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Platform\CoreSession;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreCustomer;
use App\Services\Platform\PlatformTrackingService;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsDashboard extends Page
{
    use Forms\Concerns\InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.marketplace.pages.analytics-dashboard';

    public ?string $dateRange = '30d';
    public ?array $data = [];

    /**
     * Analytics dashboard is tenant-specific, not applicable to marketplace panel
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            abort(404);
        }

        // Check if microservice is active
        $hasAccess = $tenant->microservices()
            ->where('microservices.slug', 'analytics')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasAccess) {
            Notification::make()
                ->warning()
                ->title('Microservice Not Active')
                ->body('You need to activate the Analytics microservice first.')
                ->send();

            redirect()->route('filament.marketplace.pages.microservices');
            return;
        }

        $this->data = [
            'date_range' => $this->dateRange,
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('date_range')
                    ->label('Date Range')
                    ->options([
                        '7d' => 'Last 7 days',
                        '30d' => 'Last 30 days',
                        '90d' => 'Last 90 days',
                        'all' => 'All time',
                    ])
                    ->default('30d')
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->dateRange = $state),
            ])
            ->statePath('data');
    }

    public function getMetrics(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return [
                'total_revenue' => 0,
                'total_orders' => 0,
                'total_tickets' => 0,
                'avg_order_value' => 0,
                'revenue_change' => 0,
            ];
        }

        $query = Order::where('tenant_id', $tenant->id);

        // Apply date filter
        $startDate = match ($this->dateRange) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            default => null,
        };

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $totalRevenue = (clone $query)->where('status', 'paid')->sum('total_cents') / 100;
        $totalOrders = (clone $query)->where('status', 'paid')->count();
        $totalTickets = Ticket::whereIn('order_id', (clone $query)->where('status', 'paid')->pluck('id'))->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Get previous period for comparison
        $previousStartDate = $startDate ? (clone $startDate)->subDays($startDate->diffInDays(Carbon::now())) : null;
        $previousQuery = Order::where('tenant_id', $tenant->id)->where('status', 'paid');

        if ($previousStartDate && $startDate) {
            $previousQuery->whereBetween('created_at', [$previousStartDate, $startDate]);
        }

        $previousRevenue = $previousQuery->sum('total_cents') / 100;
        $revenueChange = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'total_tickets' => $totalTickets,
            'avg_order_value' => $avgOrderValue,
            'revenue_change' => $revenueChange,
        ];
    }

    public function getSalesData(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return ['labels' => [], 'revenue' => [], 'orders' => []];
        }

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $data = Order::where('tenant_id', $tenant->id)
            ->where('status', 'paid')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(total_cents) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $revenue = [];
        $orders = [];

        // Fill in missing dates
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::parse($date)->format('M d');
            $dayData = $data->firstWhere('date', $date);
            $revenue[] = $dayData ? ($dayData->revenue / 100) : 0;
            $orders[] = $dayData ? $dayData->orders : 0;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders' => $orders,
        ];
    }

    public function getTopEvents(): array
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return [];
        }

        $startDate = match ($this->dateRange) {
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            default => null,
        };

        // Get events with ticket sales - sum order totals (not ticket prices) to account for discounts
        // Most orders are for a single event, so we can safely use order totals
        $orders = Order::where('orders.tenant_id', $tenant->id)
            ->where('orders.status', 'paid')
            ->with(['tickets.ticketType:id,event_id'])
            ->when($startDate, fn ($q) => $q->where('orders.created_at', '>=', $startDate))
            ->get(['id', 'total_cents']);

        // Group orders by event and sum totals
        $eventData = [];
        foreach ($orders as $order) {
            // Get unique event IDs for this order
            $eventIds = $order->tickets
                ->map(fn ($t) => $t->ticketType?->event_id)
                ->filter()
                ->unique()
                ->values();

            // If order has tickets from multiple events, split proportionally
            $eventCount = $eventIds->count();
            $revenuePerEvent = $eventCount > 0 ? ($order->total_cents / $eventCount) : 0;

            foreach ($eventIds as $eventId) {
                if (!isset($eventData[$eventId])) {
                    $eventData[$eventId] = ['orders' => 0, 'revenue_cents' => 0];
                }
                $eventData[$eventId]['orders']++;
                $eventData[$eventId]['revenue_cents'] += $revenuePerEvent;
            }
        }

        // Sort by revenue and take top 5
        uasort($eventData, fn ($a, $b) => $b['revenue_cents'] <=> $a['revenue_cents']);
        $topEventIds = array_slice(array_keys($eventData), 0, 5);

        // Get event titles
        $events = Event::whereIn('id', $topEventIds)->get()->keyBy('id');

        return collect($topEventIds)->map(fn ($eventId) => [
            'name' => is_array($events[$eventId]?->title ?? null)
                ? ($events[$eventId]->title['en'] ?? $events[$eventId]->title[array_key_first($events[$eventId]->title)] ?? 'Untitled')
                : ($events[$eventId]?->title ?? 'Untitled'),
            'orders' => $eventData[$eventId]['orders'] ?? 0,
            'revenue' => ($eventData[$eventId]['revenue_cents'] ?? 0) / 100,
        ])->toArray();
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getTitle(): string
    {
        return 'Analytics Dashboard';
    }

    /**
     * Get real-time analytics data from platform tracking
     */
    public function getRealtimeData(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        // Get real active sessions
        $activeUsers = CoreSession::active()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->count();

        // Get users per minute for last 30 minutes
        $usersPerMinute = [];
        for ($i = 29; $i >= 0; $i--) {
            $start = now()->subMinutes($i + 1);
            $end = now()->subMinutes($i);
            $count = CoreSession::query()
                ->when($tenantId, fn($q) => $q->forTenant($tenantId))
                ->whereBetween('started_at', [$start, $end])
                ->count();
            $usersPerMinute[] = $count;
        }

        // Get active pages with user counts
        $activePages = CoreCustomerEvent::pageViews()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('created_at', '>=', now()->subMinutes(30))
            ->selectRaw('page_url as path, COUNT(DISTINCT session_id) as users')
            ->groupBy('page_url')
            ->orderByDesc('users')
            ->limit(5)
            ->get()
            ->map(fn($page) => [
                'path' => parse_url($page->path, PHP_URL_PATH) ?: '/',
                'users' => $page->users,
            ])
            ->toArray();

        // Fill with defaults if no data
        if (empty($activePages)) {
            $activePages = [['path' => '/', 'users' => 0]];
        }

        // Get recent events from real tracking
        $recentEvents = $this->getRealRecentEvents();

        // Today's totals
        $todayUsers = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->count();

        $yesterdayUsers = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today()->subDay())
            ->count();

        $usersChange = $yesterdayUsers > 0
            ? round((($todayUsers - $yesterdayUsers) / $yesterdayUsers) * 100, 1)
            : 0;

        // Sessions today
        $todaySessions = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->count();

        $yesterdaySessions = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today()->subDay())
            ->count();

        $sessionsChange = $yesterdaySessions > 0
            ? round((($todaySessions - $yesterdaySessions) / $yesterdaySessions) * 100, 1)
            : 0;

        // Bounce rate - calculate from pageviews (sessions with only 1 page view)
        // For ended sessions, use is_bounce. For active sessions, check pageviews
        $bouncedSessions = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->where(function($q) {
                $q->where('is_bounce', true)
                  ->orWhere(function($q2) {
                      $q2->whereNull('ended_at')
                         ->where('pageviews', '<=', 1);
                  });
            })
            ->count();

        $bounceRate = $todaySessions > 0
            ? round(($bouncedSessions / $todaySessions) * 100, 1)
            : 0;

        // Average session duration - include active sessions with calculated duration
        $endedAvg = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->avg('duration_seconds') ?? 0;

        // For active sessions, calculate duration from started_at
        $activeSessions = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->whereNull('ended_at')
            ->get();

        $totalDuration = $endedAvg * CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->count();

        foreach ($activeSessions as $session) {
            $totalDuration += $session->started_at->diffInSeconds(now());
        }

        $totalSessionCount = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->where(function($q) {
                $q->whereNotNull('duration_seconds')
                  ->where('duration_seconds', '>', 0)
                  ->orWhereNull('ended_at');
            })
            ->count();

        $avgDurationSeconds = $totalSessionCount > 0 ? $totalDuration / $totalSessionCount : 0;

        $minutes = floor($avgDurationSeconds / 60);
        $seconds = (int) ($avgDurationSeconds % 60);

        return [
            'active_users' => $activeUsers,
            'users_per_minute' => $usersPerMinute,
            'active_pages' => $activePages,
            'recent_events' => $recentEvents,
            'total_users' => $todayUsers,
            'users_change' => $usersChange,
            'total_sessions' => $todaySessions,
            'sessions_change' => $sessionsChange,
            'bounce_rate' => $bounceRate,
            'avg_duration' => sprintf('%d:%02d', $minutes, $seconds),
        ];
    }

    /**
     * Get real recent activity events from tracking
     */
    protected function getRealRecentEvents(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;
        $currencySymbol = $this->getCurrencySymbol();

        $events = CoreCustomerEvent::with(['session', 'coreCustomer'])
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $events->map(function ($event) use ($currencySymbol) {
            $type = match ($event->event_type) {
                'purchase' => 'purchase',
                'add_to_cart' => 'cart',
                'begin_checkout' => 'checkout',
                'sign_up' => 'signup',
                'page_view' => 'view',
                default => 'view',
            };

            $pageName = $event->page_title ?: basename(parse_url($event->page_url ?? '/', PHP_URL_PATH));

            $description = match ($event->event_type) {
                'purchase' => "Purchased tickets" . ($event->order_total ? " ({$currencySymbol}" . number_format($event->order_total, 2) . ")" : ""),
                'add_to_cart' => "Added to cart: {$pageName}",
                'begin_checkout' => "Started checkout",
                'sign_up' => "New user registration",
                'page_view' => "Viewing {$pageName}",
                'view_item' => "Viewing event: {$pageName}",
                'search' => "Searched: " . ($event->event_data['searchTerm'] ?? 'events'),
                default => "Browsing {$pageName}",
            };

            // Build location string
            $locationParts = array_filter([
                $event->city,
                $event->country_code,
            ]);
            $location = !empty($locationParts) ? implode(', ', $locationParts) : 'Unknown';

            return [
                'type' => $type,
                'description' => $description,
                'location' => $location,
                'time' => $event->created_at->diffForHumans(short: true),
            ];
        })->toArray();
    }

    /**
     * Get traffic sources data from real tracking
     */
    public function getTrafficSources(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $caseExpression = "
            CASE
                WHEN gclid IS NOT NULL THEN 'Google Ads'
                WHEN fbclid IS NOT NULL THEN 'Facebook'
                WHEN ttclid IS NOT NULL THEN 'TikTok'
                WHEN utm_source = 'google' AND utm_medium = 'organic' THEN 'Organic Search'
                WHEN utm_source IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(utm_source, 1, 1)), LOWER(SUBSTRING(utm_source, 2)))
                WHEN referrer IS NOT NULL AND referrer != '' THEN 'Referral'
                ELSE 'Direct'
            END";

        $sources = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->selectRaw("{$caseExpression} as source, COUNT(*) as count")
            ->groupByRaw($caseExpression)
            ->orderByDesc('count')
            ->get();

        $total = $sources->sum('count') ?: 1;

        $colors = [
            'Direct' => 'bg-blue-500',
            'Organic Search' => 'bg-green-500',
            'Google Ads' => 'bg-red-500',
            'Facebook' => 'bg-indigo-500',
            'TikTok' => 'bg-pink-500',
            'LinkedIn' => 'bg-sky-500',
            'Referral' => 'bg-orange-500',
            'Email' => 'bg-yellow-500',
        ];

        return $sources->map(fn($source) => [
            'name' => $source->source,
            'percentage' => round(($source->count / $total) * 100, 1),
            'color' => $colors[$source->source] ?? 'bg-gray-500',
        ])->take(6)->toArray();
    }

    /**
     * Get top pages data from real tracking
     */
    public function getTopPages(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        return CoreCustomerEvent::pageViews()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('page_url, COUNT(*) as views')
            ->groupBy('page_url')
            ->orderByDesc('views')
            ->limit(5)
            ->get()
            ->map(fn($page) => [
                'path' => parse_url($page->page_url, PHP_URL_PATH) ?: '/',
                'views' => $page->views,
            ])
            ->toArray();
    }

    /**
     * Get geographic distribution data from real tracking
     */
    public function getGeographicData(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        // Country code to flag emoji mapping
        $flags = [
            'RO' => '🇷🇴', 'DE' => '🇩🇪', 'AT' => '🇦🇹', 'HU' => '🇭🇺',
            'GB' => '🇬🇧', 'US' => '🇺🇸', 'FR' => '🇫🇷', 'IT' => '🇮🇹',
            'ES' => '🇪🇸', 'NL' => '🇳🇱', 'BE' => '🇧🇪', 'PL' => '🇵🇱',
            'CZ' => '🇨🇿', 'SK' => '🇸🇰', 'CH' => '🇨🇭', 'SE' => '🇸🇪',
            'BG' => '🇧🇬', 'GR' => '🇬🇷', 'RS' => '🇷🇸', 'UA' => '🇺🇦',
            'MD' => '🇲🇩', 'HR' => '🇭🇷', 'SI' => '🇸🇮', 'BA' => '🇧🇦',
        ];

        return CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as users')
            ->groupBy('country_code')
            ->orderByDesc('users')
            ->limit(5)
            ->get()
            ->map(fn($country) => [
                'flag' => $flags[$country->country_code] ?? '🏳️',
                'name' => $country->country_code,
                'users' => $country->users,
            ])
            ->toArray();
    }

    /**
     * Refresh real-time data (called by wire:poll)
     */
    public function refreshRealtime(): void
    {
        // This triggers a re-render which will call getRealtimeData() again
    }

    /**
     * Get tenant's currency symbol
     */
    public function getCurrencySymbol(): string
    {
        $tenant = auth()->user()->tenant;
        $currency = $tenant?->currency ?? 'EUR';

        return match (strtoupper($currency)) {
            'RON' => 'RON',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            default => '€',
        };
    }

    /**
     * Get device statistics from real tracking data
     */
    public function getDeviceStats(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $devices = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('device_type')
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->get();

        $total = $devices->sum('count') ?: 1;

        $labels = [];
        $data = [];
        $colors = [
            'desktop' => 'rgb(99, 102, 241)',
            'mobile' => 'rgb(16, 185, 129)',
            'tablet' => 'rgb(249, 115, 22)',
        ];
        $colorValues = [];

        foreach (['desktop', 'mobile', 'tablet'] as $type) {
            $device = $devices->firstWhere('device_type', $type);
            $count = $device?->count ?? 0;
            if ($count > 0 || $devices->count() === 0) {
                $labels[] = ucfirst($type);
                $data[] = round(($count / $total) * 100, 1);
                $colorValues[] = $colors[$type];
            }
        }

        // If no data, return demo-like placeholders
        if (empty($data)) {
            return [
                'labels' => ['Desktop', 'Mobile', 'Tablet'],
                'data' => [0, 0, 0],
                'colors' => array_values($colors),
                'hasData' => false,
            ];
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colorValues,
            'hasData' => true,
        ];
    }

    /**
     * Get browser statistics from real tracking data
     */
    public function getBrowserStats(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $browsers = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('browser')
            ->where('browser', '!=', '')
            ->selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $total = $browsers->sum('count') ?: 1;

        $colors = [
            'chrome' => 'rgb(59, 130, 246)',
            'safari' => 'rgb(249, 115, 22)',
            'firefox' => 'rgb(168, 85, 247)',
            'edge' => 'rgb(16, 185, 129)',
            'opera' => 'rgb(239, 68, 68)',
        ];
        $defaultColors = [
            'rgb(59, 130, 246)',
            'rgb(249, 115, 22)',
            'rgb(168, 85, 247)',
            'rgb(16, 185, 129)',
            'rgb(107, 114, 128)',
        ];

        $labels = [];
        $data = [];
        $colorValues = [];

        foreach ($browsers as $index => $browser) {
            $browserName = ucfirst(strtolower($browser->browser));
            $labels[] = $browserName;
            $data[] = round(($browser->count / $total) * 100, 1);
            $colorValues[] = $colors[strtolower($browser->browser)] ?? $defaultColors[$index] ?? 'rgb(107, 114, 128)';
        }

        // If no data, return empty state
        if (empty($data)) {
            return [
                'labels' => ['Chrome', 'Safari', 'Firefox', 'Edge', 'Other'],
                'data' => [0, 0, 0, 0, 0],
                'colors' => $defaultColors,
                'hasData' => false,
            ];
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $colorValues,
            'hasData' => true,
        ];
    }

    /**
     * Check if we have real tracking data
     */
    public function hasTrackingData(): bool
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        return CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays(30))
            ->exists();
    }

    /**
     * Get landing pages (entry pages) from sessions
     */
    public function getLandingPages(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        return CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('landing_page')
            ->where('landing_page', '!=', '')
            ->selectRaw('landing_page, COUNT(*) as sessions')
            ->groupBy('landing_page')
            ->orderByDesc('sessions')
            ->limit(10)
            ->get()
            ->map(fn($page) => [
                'path' => parse_url($page->landing_page, PHP_URL_PATH) ?: '/',
                'sessions' => $page->sessions,
            ])
            ->toArray();
    }

    /**
     * Get exit pages from sessions
     */
    public function getExitPages(): array
    {
        $tenant = auth()->user()->tenant;
        $tenantId = $tenant?->id;

        $days = match ($this->dateRange) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        return CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->whereNotNull('exit_page')
            ->where('exit_page', '!=', '')
            ->selectRaw('exit_page, COUNT(*) as sessions')
            ->groupBy('exit_page')
            ->orderByDesc('sessions')
            ->limit(10)
            ->get()
            ->map(fn($page) => [
                'path' => parse_url($page->exit_page, PHP_URL_PATH) ?: '/',
                'sessions' => $page->sessions,
            ])
            ->toArray();
    }
}
