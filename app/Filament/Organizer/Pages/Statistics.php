<?php

namespace App\Filament\Organizer\Pages;

use App\Models\Event;
use App\Models\Marketplace\MarketplacePayout;
use App\Models\Order;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Statistics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Statistics';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.organizer.pages.statistics';

    public ?string $period = 'month';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;
    public ?int $eventId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function form(Form $form): Form
    {
        $organizer = auth('organizer')->user()?->organizer;

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

                Select::make('eventId')
                    ->label('Event')
                    ->options(fn () => $organizer ? Event::where('organizer_id', $organizer->id)
                        ->orderByDesc('start_date')
                        ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro'))"), 'id')
                        ->prepend('All Events', '') : [])
                    ->placeholder('All Events')
                    ->searchable(),
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
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return [];
        }

        $query = $this->getBaseQuery($organizer);

        $totalOrders = (clone $query)->count();
        $totalGrossRevenue = (clone $query)->sum(DB::raw('total_cents / 100'));
        $tixelloCommission = (clone $query)->sum('tixello_commission');
        $marketplaceCommission = (clone $query)->sum('marketplace_commission');
        $organizerRevenue = (clone $query)->sum('organizer_revenue');

        // Tickets
        $ticketsQuery = Order::where('organizer_id', $organizer->id);
        $this->applyFilters($ticketsQuery);
        $totalTickets = $ticketsQuery->withCount('tickets')->get()->sum('tickets_count');

        // Comparison with previous period
        $previousPeriodRevenue = $this->getPreviousPeriodRevenue($organizer);
        $revenueChange = $previousPeriodRevenue > 0
            ? (($organizerRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100
            : 0;

        return [
            'total_orders' => $totalOrders,
            'total_gross_revenue' => $totalGrossRevenue,
            'tixello_commission' => $tixelloCommission,
            'marketplace_commission' => $marketplaceCommission,
            'organizer_revenue' => $organizerRevenue,
            'total_tickets' => $totalTickets,
            'avg_order_value' => $totalOrders > 0 ? $totalGrossRevenue / $totalOrders : 0,
            'avg_ticket_price' => $totalTickets > 0 ? $totalGrossRevenue / $totalTickets : 0,
            'revenue_change' => $revenueChange,
        ];
    }

    protected function getPreviousPeriodRevenue($organizer): float
    {
        if (!$this->dateFrom || !$this->dateTo) {
            return 0;
        }

        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);
        $periodDays = $from->diffInDays($to) + 1;

        $prevFrom = $from->copy()->subDays($periodDays);
        $prevTo = $from->copy()->subDay();

        return Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->sum('organizer_revenue');
    }

    public function getRevenueByDay(): array
    {
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return [];
        }

        $query = Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed']);

        $this->applyFilters($query);

        return $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_cents / 100) as gross_revenue'),
                DB::raw('SUM(organizer_revenue) as net_revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function getEventStats(): array
    {
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return [];
        }

        $events = Event::where('organizer_id', $organizer->id)
            ->withCount(['tickets', 'orders' => function ($query) {
                $this->applyFilters($query);
            }])
            ->orderByDesc('start_date')
            ->limit(20)
            ->get()
            ->map(function ($event) use ($organizer) {
                $ordersQuery = Order::where('organizer_id', $organizer->id)
                    ->whereHas('tickets.ticketType', function ($q) use ($event) {
                        $q->where('event_id', $event->id);
                    });
                $this->applyFilters($ordersQuery);

                $revenue = $ordersQuery->sum('organizer_revenue');
                $grossRevenue = $ordersQuery->sum(DB::raw('total_cents / 100'));

                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', 'ro'),
                    'date' => $event->start_date?->format('M j, Y'),
                    'status' => $event->is_cancelled ? 'cancelled' : ($event->is_sold_out ? 'sold_out' : 'active'),
                    'tickets_sold' => $event->tickets_count,
                    'orders' => $event->orders_count,
                    'gross_revenue' => $grossRevenue,
                    'net_revenue' => $revenue,
                ];
            })
            ->toArray();

        return $events;
    }

    public function getPayoutHistory(): array
    {
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return [];
        }

        return MarketplacePayout::where('organizer_id', $organizer->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($payout) => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'period' => $payout->period_start->format('M j') . ' - ' . $payout->period_end->format('M j, Y'),
                'amount' => $payout->amount,
                'status' => $payout->status,
                'processed_at' => $payout->processed_at?->format('M j, Y'),
            ])
            ->toArray();
    }

    public function getCommissionBreakdown(): array
    {
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return [];
        }

        $query = $this->getBaseQuery($organizer);

        $grossRevenue = (clone $query)->sum(DB::raw('total_cents / 100'));
        $tixello = (clone $query)->sum('tixello_commission');
        $marketplace = (clone $query)->sum('marketplace_commission');
        $net = (clone $query)->sum('organizer_revenue');

        return [
            'gross_revenue' => $grossRevenue,
            'tixello_fee' => $tixello,
            'tixello_percent' => $grossRevenue > 0 ? ($tixello / $grossRevenue) * 100 : 0,
            'marketplace_fee' => $marketplace,
            'marketplace_percent' => $grossRevenue > 0 ? ($marketplace / $grossRevenue) * 100 : 0,
            'net_revenue' => $net,
            'net_percent' => $grossRevenue > 0 ? ($net / $grossRevenue) * 100 : 0,
        ];
    }

    public function getTicketTypeStats(): array
    {
        $organizer = auth('organizer')->user()?->organizer;
        if (!$organizer) {
            return [];
        }

        $query = DB::table('tickets')
            ->join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->where('events.organizer_id', $organizer->id)
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed']);

        if ($this->dateFrom) {
            $query->where('orders.created_at', '>=', Carbon::parse($this->dateFrom)->startOfDay());
        }

        if ($this->dateTo) {
            $query->where('orders.created_at', '<=', Carbon::parse($this->dateTo)->endOfDay());
        }

        if ($this->eventId) {
            $query->where('events.id', $this->eventId);
        }

        return $query
            ->select(
                'ticket_types.id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(ticket_types.name, '$.ro')) as name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(events.title, '$.ro')) as event_name"),
                DB::raw('ticket_types.price_cents / 100 as price'),
                DB::raw('COUNT(tickets.id) as sold'),
                DB::raw('SUM(ticket_types.price_cents / 100) as revenue')
            )
            ->groupBy('ticket_types.id', 'ticket_types.name', 'events.title', 'ticket_types.price_cents')
            ->orderByDesc('sold')
            ->limit(15)
            ->get()
            ->toArray();
    }

    protected function getBaseQuery($organizer)
    {
        $query = Order::where('organizer_id', $organizer->id)
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

        if ($this->eventId) {
            $query->whereHas('tickets.ticketType', function ($q) {
                $q->where('event_id', $this->eventId);
            });
        }
    }

    public function getViewData(): array
    {
        return [
            'overview' => $this->getOverviewStats(),
            'revenueByDay' => $this->getRevenueByDay(),
            'events' => $this->getEventStats(),
            'payouts' => $this->getPayoutHistory(),
            'commission' => $this->getCommissionBreakdown(),
            'ticketTypes' => $this->getTicketTypeStats(),
        ];
    }
}
