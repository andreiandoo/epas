<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
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
     * Get real-time analytics data
     * Note: This returns simulated data. In production, connect to actual tracking service.
     */
    public function getRealtimeData(): array
    {
        // Simulated real-time data - would come from tracking service in production
        $baseUsers = rand(5, 25);

        // Generate users per minute for last 30 minutes
        $usersPerMinute = [];
        for ($i = 0; $i < 30; $i++) {
            $usersPerMinute[] = max(0, $baseUsers + rand(-8, 12));
        }

        return [
            'active_users' => $baseUsers,
            'users_per_minute' => $usersPerMinute,
            'active_pages' => [
                ['path' => '/events', 'users' => rand(3, 12)],
                ['path' => '/checkout', 'users' => rand(1, 5)],
                ['path' => '/event/concert-xyz', 'users' => rand(2, 8)],
                ['path' => '/tickets', 'users' => rand(1, 4)],
                ['path' => '/', 'users' => rand(2, 6)],
            ],
            'recent_events' => $this->generateRecentEvents(),
            'total_users' => rand(1200, 2500),
            'users_change' => rand(-5, 15) + (rand(0, 100) / 10),
            'total_sessions' => rand(1800, 4000),
            'sessions_change' => rand(0, 20) + (rand(0, 100) / 10),
            'bounce_rate' => 35 + rand(0, 20) + (rand(0, 100) / 100),
            'avg_duration' => sprintf('%d:%02d', rand(2, 5), rand(0, 59)),
        ];
    }

    /**
     * Generate simulated recent activity events
     */
    protected function generateRecentEvents(): array
    {
        $events = [];
        $types = ['purchase', 'view', 'cart', 'view', 'view', 'purchase', 'cart'];
        $locations = ['Bucharest, RO', 'Cluj-Napoca, RO', 'Timișoara, RO', 'Berlin, DE', 'Vienna, AT', 'Budapest, HU'];
        $pages = ['Summer Festival 2025', 'Jazz Night', 'Rock Concert', 'Classical Evening', 'Electronic Party'];

        for ($i = 0; $i < 8; $i++) {
            $type = $types[array_rand($types)];
            $page = $pages[array_rand($pages)];

            $description = match ($type) {
                'purchase' => "Purchased 2 tickets for {$page}",
                'view' => "Viewing {$page}",
                'cart' => "Added {$page} to cart",
                default => "Browsing events",
            };

            $events[] = [
                'type' => $type,
                'description' => $description,
                'location' => $locations[array_rand($locations)],
                'time' => rand(1, 15) . ' sec ago',
            ];
        }

        return $events;
    }

    /**
     * Get traffic sources data
     */
    public function getTrafficSources(): array
    {
        return [
            ['name' => 'Direct', 'percentage' => 42, 'color' => 'bg-blue-500'],
            ['name' => 'Organic Search', 'percentage' => 28, 'color' => 'bg-green-500'],
            ['name' => 'Social Media', 'percentage' => 18, 'color' => 'bg-purple-500'],
            ['name' => 'Referral', 'percentage' => 8, 'color' => 'bg-orange-500'],
            ['name' => 'Email', 'percentage' => 4, 'color' => 'bg-pink-500'],
        ];
    }

    /**
     * Get top pages data
     */
    public function getTopPages(): array
    {
        return [
            ['path' => '/events', 'views' => rand(800, 1500)],
            ['path' => '/events/summer-fest', 'views' => rand(400, 800)],
            ['path' => '/checkout', 'views' => rand(200, 500)],
            ['path' => '/artists', 'views' => rand(150, 400)],
            ['path' => '/about', 'views' => rand(80, 200)],
        ];
    }

    /**
     * Get geographic distribution data
     */
    public function getGeographicData(): array
    {
        return [
            ['flag' => '🇷🇴', 'name' => 'Romania', 'users' => rand(800, 1500)],
            ['flag' => '🇩🇪', 'name' => 'Germany', 'users' => rand(200, 500)],
            ['flag' => '🇦🇹', 'name' => 'Austria', 'users' => rand(100, 300)],
            ['flag' => '🇭🇺', 'name' => 'Hungary', 'users' => rand(80, 200)],
            ['flag' => '🇬🇧', 'name' => 'UK', 'users' => rand(50, 150)],
        ];
    }

    /**
     * Refresh real-time data (called by wire:poll)
     */
    public function refreshRealtime(): void
    {
        // This triggers a re-render which will call getRealtimeData() again
    }
}
