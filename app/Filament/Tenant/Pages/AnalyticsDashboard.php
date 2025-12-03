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
}
