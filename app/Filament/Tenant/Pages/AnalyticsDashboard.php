<?php

namespace App\Filament\Tenant\Pages;

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
    protected string $view = 'filament.tenant.pages.analytics-dashboard';

    public ?string $dateRange = '30d';
    public ?array $data = [];

    /**
     * Only show if tenant has analytics microservice active
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('microservices.slug', 'analytics')
            ->wherePivot('is_active', true)
            ->exists();
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

            redirect()->route('filament.tenant.pages.microservices');
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

        // Get events with ticket sales through ticket_types -> tickets -> orders
        return Event::where('events.tenant_id', $tenant->id)
            ->select('events.id', 'events.title')
            ->join('ticket_types', 'ticket_types.event_id', '=', 'events.id')
            ->join('tickets', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('orders', function ($join) use ($startDate) {
                $join->on('orders.id', '=', 'tickets.order_id')
                    ->where('orders.status', '=', 'paid');
                if ($startDate) {
                    $join->where('orders.created_at', '>=', $startDate);
                }
            })
            ->groupBy('events.id', 'events.title')
            ->selectRaw('COUNT(DISTINCT orders.id) as order_count, SUM(ticket_types.price_cents) as revenue_cents')
            ->orderByDesc('revenue_cents')
            ->limit(5)
            ->get()
            ->map(fn ($event) => [
                'name' => is_array($event->title) ? ($event->title['en'] ?? $event->title[array_key_first($event->title)] ?? 'Untitled') : $event->title,
                'orders' => $event->order_count ?? 0,
                'revenue' => ($event->revenue_cents ?? 0) / 100,
            ])
            ->toArray();
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

        // Bounce rate (sessions with only 1 page view)
        $bouncedSessions = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->where('is_bounce', true)
            ->count();

        $bounceRate = $todaySessions > 0
            ? round(($bouncedSessions / $todaySessions) * 100, 1)
            : 0;

        // Average session duration
        $avgDurationSeconds = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->whereDate('started_at', today())
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds') ?? 0;

        $minutes = floor($avgDurationSeconds / 60);
        $seconds = $avgDurationSeconds % 60;

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

        $events = CoreCustomerEvent::with(['session', 'coreCustomer'])
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $events->map(function ($event) {
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
                'purchase' => "Purchased tickets" . ($event->order_total ? " (€" . number_format($event->order_total, 2) . ")" : ""),
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

        $sources = CoreSession::query()
            ->when($tenantId, fn($q) => $q->forTenant($tenantId))
            ->where('started_at', '>=', now()->subDays($days))
            ->selectRaw("
                CASE
                    WHEN gclid IS NOT NULL THEN 'Google Ads'
                    WHEN fbclid IS NOT NULL THEN 'Facebook'
                    WHEN ttclid IS NOT NULL THEN 'TikTok'
                    WHEN utm_source = 'google' AND utm_medium = 'organic' THEN 'Organic Search'
                    WHEN utm_source IS NOT NULL THEN CONCAT(UPPER(SUBSTRING(utm_source, 1, 1)), LOWER(SUBSTRING(utm_source, 2)))
                    WHEN referrer IS NOT NULL AND referrer != '' THEN 'Referral'
                    ELSE 'Direct'
                END as source,
                COUNT(*) as count
            ")
            ->groupBy('source')
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
}
