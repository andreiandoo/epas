<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Event;
use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplacePayout;
use App\Models\Order;
use App\Models\Tenant;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MarketplaceStatistics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Marketplace Statistics';
    protected static ?string $navigationGroup = 'Marketplace';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.tenant.pages.marketplace-statistics';

    public ?string $period = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?int $organizerId = null;

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();
        return $tenant && $tenant->isMarketplace();
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        $tenant = filament()->getTenant();

        return $form
            ->schema([
                Select::make('period')
                    ->label('Quick Period')
                    ->options([
                        'today' => 'Today',
                        'week' => 'This Week',
                        'month' => 'This Month',
                        'quarter' => 'This Quarter',
                        'year' => 'This Year',
                        'all' => 'All Time',
                        'custom' => 'Custom Range',
                    ])
                    ->default('month')
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->updatePeriod($state)),

                DatePicker::make('dateFrom')
                    ->label('From')
                    ->visible(fn () => $this->period === 'custom'),

                DatePicker::make('dateTo')
                    ->label('To')
                    ->visible(fn () => $this->period === 'custom'),

                Select::make('organizerId')
                    ->label('Organizer')
                    ->options(fn () => MarketplaceOrganizer::where('tenant_id', $tenant->id)
                        ->pluck('name', 'id')
                        ->prepend('All Organizers', ''))
                    ->placeholder('All Organizers'),
            ])
            ->columns(4);
    }

    protected function updatePeriod(string $period): void
    {
        $now = now();

        $this->period = $period;

        switch ($period) {
            case 'today':
                $this->dateFrom = $now->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'quarter':
                $this->dateFrom = $now->startOfQuarter()->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'all':
                $this->dateFrom = null;
                $this->dateTo = null;
                break;
        }
    }

    public function getOverviewStats(): array
    {
        $tenant = filament()->getTenant();
        $query = $this->getBaseQuery($tenant);

        $ordersQuery = clone $query;
        $revenueQuery = clone $query;

        $totalOrders = $ordersQuery->count();
        $totalGrossRevenue = $revenueQuery->sum(DB::raw('total_cents / 100'));

        $commissionsQuery = clone $query;
        $tixelloCommission = $commissionsQuery->sum('tixello_commission');

        $marketplaceQuery = clone $query;
        $marketplaceCommission = $marketplaceQuery->sum('marketplace_commission');

        $organizerQuery = clone $query;
        $organizerRevenue = $organizerQuery->sum('organizer_revenue');

        $ticketsQuery = Order::where('tenant_id', $tenant->id)
            ->whereNotNull('organizer_id');
        $this->applyFilters($ticketsQuery);
        $totalTickets = $ticketsQuery->withCount('tickets')->get()->sum('tickets_count');

        return [
            'total_orders' => $totalOrders,
            'total_gross_revenue' => $totalGrossRevenue,
            'tixello_commission' => $tixelloCommission,
            'marketplace_commission' => $marketplaceCommission,
            'organizer_revenue' => $organizerRevenue,
            'total_tickets' => $totalTickets,
            'avg_order_value' => $totalOrders > 0 ? $totalGrossRevenue / $totalOrders : 0,
        ];
    }

    public function getOrganizerStats(): array
    {
        $tenant = filament()->getTenant();

        $organizers = MarketplaceOrganizer::where('tenant_id', $tenant->id)
            ->withCount(['events', 'orders' => function ($query) {
                $this->applyFilters($query);
            }])
            ->get()
            ->map(function ($organizer) {
                $ordersQuery = Order::where('organizer_id', $organizer->id);
                $this->applyFilters($ordersQuery);

                $revenue = $ordersQuery->sum('organizer_revenue');
                $commission = $ordersQuery->sum('marketplace_commission');
                $grossRevenue = $ordersQuery->sum(DB::raw('total_cents / 100'));

                return [
                    'id' => $organizer->id,
                    'name' => $organizer->name,
                    'status' => $organizer->status,
                    'is_verified' => $organizer->is_verified,
                    'events_count' => $organizer->events_count,
                    'orders_count' => $organizer->orders_count,
                    'gross_revenue' => $grossRevenue,
                    'marketplace_commission' => $commission,
                    'organizer_revenue' => $revenue,
                    'pending_payout' => $organizer->pending_payout,
                ];
            })
            ->sortByDesc('gross_revenue')
            ->values()
            ->toArray();

        return $organizers;
    }

    public function getRevenueByDay(): array
    {
        $tenant = filament()->getTenant();

        $query = Order::where('tenant_id', $tenant->id)
            ->whereNotNull('organizer_id')
            ->whereIn('status', ['paid', 'confirmed', 'completed']);

        $this->applyFilters($query);

        $data = $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_cents / 100) as gross_revenue'),
                DB::raw('SUM(tixello_commission) as tixello'),
                DB::raw('SUM(marketplace_commission) as marketplace'),
                DB::raw('SUM(organizer_revenue) as organizer'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        return $data;
    }

    public function getPayoutStats(): array
    {
        $tenant = filament()->getTenant();

        $query = MarketplacePayout::where('tenant_id', $tenant->id);

        if ($this->organizerId) {
            $query->where('organizer_id', $this->organizerId);
        }

        $pending = (clone $query)->where('status', 'pending')->sum('amount');
        $processing = (clone $query)->where('status', 'processing')->sum('amount');
        $completed = (clone $query)->where('status', 'completed')->sum('amount');
        $failed = (clone $query)->where('status', 'failed')->count();

        $pendingCount = (clone $query)->where('status', 'pending')->count();
        $processingCount = (clone $query)->where('status', 'processing')->count();
        $completedCount = (clone $query)->where('status', 'completed')->count();

        return [
            'pending_amount' => $pending,
            'pending_count' => $pendingCount,
            'processing_amount' => $processing,
            'processing_count' => $processingCount,
            'completed_amount' => $completed,
            'completed_count' => $completedCount,
            'failed_count' => $failed,
        ];
    }

    public function getTopEvents(): array
    {
        $tenant = filament()->getTenant();

        $events = Event::where('tenant_id', $tenant->id)
            ->whereNotNull('organizer_id')
            ->withCount(['orders' => function ($query) {
                $this->applyFilters($query);
            }])
            ->with('organizer:id,name')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get()
            ->map(function ($event) {
                $ordersQuery = Order::whereHas('tickets.ticketType', function ($q) use ($event) {
                    $q->where('event_id', $event->id);
                });
                $this->applyFilters($ordersQuery);

                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', 'ro'),
                    'organizer' => $event->organizer?->name,
                    'date' => $event->start_date?->format('M j, Y'),
                    'orders' => $event->orders_count,
                    'revenue' => $ordersQuery->sum('organizer_revenue'),
                ];
            })
            ->toArray();

        return $events;
    }

    protected function getBaseQuery(Tenant $tenant)
    {
        $query = Order::where('tenant_id', $tenant->id)
            ->whereNotNull('organizer_id')
            ->whereIn('status', ['paid', 'confirmed', 'completed']);

        $this->applyFilters($query);

        return $query;
    }

    protected function applyFilters($query): void
    {
        if ($this->dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        }

        if ($this->dateTo) {
            $query->where('created_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        if ($this->organizerId) {
            $query->where('organizer_id', $this->organizerId);
        }
    }

    public function getViewData(): array
    {
        return [
            'overview' => $this->getOverviewStats(),
            'organizers' => $this->getOrganizerStats(),
            'revenueByDay' => $this->getRevenueByDay(),
            'payouts' => $this->getPayoutStats(),
            'topEvents' => $this->getTopEvents(),
        ];
    }
}
