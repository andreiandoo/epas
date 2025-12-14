<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\Venue;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class Dashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.tenant.pages.dashboard';

    public ?Tenant $tenant = null;

    #[Url]
    public string $chartPeriod = '30';

    public function mount(): void
    {
        $this->tenant = auth()->user()->tenant;
    }

    public function getTitle(): string
    {
        return ''; // Empty title as requested
    }

    public function getHeading(): string|null
    {
        return null; // Remove heading
    }

    public function updatedChartPeriod(): void
    {
        // Dispatch browser event to trigger chart re-initialization
        $this->dispatch('charts-updated');
    }

    public function getViewData(): array
    {
        $tenant = $this->tenant;

        if (!$tenant) {
            return [
                'tenant' => null,
                'stats' => [],
                'chartData' => [],
            ];
        }

        $tenantId = $tenant->id;

        // Calculate date range for chart
        $days = (int) $this->chartPeriod;
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Active events (upcoming or ongoing)
        $activeEvents = Event::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $today = Carbon::now()->startOfDay();
                $query->where('event_date', '>=', $today)
                    ->orWhere('range_end_date', '>=', $today);
            })
            ->where('is_cancelled', false)
            ->count();

        // Total sales (sum of paid/confirmed orders) - total_cents / 100
        $totalSales = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->sum('total_cents') / 100;

        // Total tickets sold (filter through orders since tickets don't have tenant_id)
        $totalTickets = Ticket::whereHas('order', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId)
                ->whereIn('status', ['paid', 'confirmed']);
        })->count();

        // Total customers
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        // Unpaid invoices VALUE - uses 'amount' decimal column
        $unpaidInvoicesValue = $tenant->invoices()
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        // Chart data - daily sales for the selected period
        $chartData = $this->getChartData($tenantId, $startDate, $endDate, $days);

        // Ticket chart data - daily ticket sales with event breakdown
        $ticketChartData = $this->getTicketChartData($tenantId, $startDate, $endDate, $days);

        // Venue activity stats (for tenants who own venues)
        $venueStats = $this->getVenueActivityStats($tenant);

        return [
            'tenant' => $tenant,
            'stats' => [
                'active_events' => $activeEvents,
                'total_sales' => $totalSales,
                'total_tickets' => $totalTickets,
                'total_customers' => $totalCustomers,
                'unpaid_invoices_value' => $unpaidInvoicesValue,
            ],
            'chartData' => $chartData,
            'ticketChartData' => $ticketChartData,
            'chartPeriod' => $this->chartPeriod,
            'venueStats' => $venueStats,
        ];
    }

    /**
     * Get venue activity statistics for hosted events
     */
    private function getVenueActivityStats(Tenant $tenant): ?array
    {
        // Check if tenant owns any venues
        if (!$tenant->ownsVenues()) {
            return null;
        }

        $ownedVenueIds = $tenant->venues()->pluck('id')->toArray();
        $tenantId = $tenant->id;

        // Hosted events (events at owned venues by other tenants)
        $hostedEventIds = Event::whereIn('venue_id', $ownedVenueIds)
            ->where('tenant_id', '!=', $tenantId)
            ->pluck('id')
            ->toArray();

        if (empty($hostedEventIds)) {
            return [
                'has_venues' => true,
                'venues_count' => count($ownedVenueIds),
                'hosted_events' => 0,
                'upcoming_hosted_events' => 0,
                'hosted_tickets_sold' => 0,
                'hosted_revenue' => 0,
            ];
        }

        // Count hosted events
        $hostedEventsCount = count($hostedEventIds);

        // Upcoming hosted events
        $upcomingHostedEvents = Event::whereIn('id', $hostedEventIds)
            ->upcoming()
            ->count();

        // Tickets sold for hosted events
        $hostedTicketsSold = Ticket::whereHas('ticketType', function ($query) use ($hostedEventIds) {
            $query->whereIn('event_id', $hostedEventIds);
        })->whereHas('order', function ($query) {
            $query->whereIn('status', ['paid', 'confirmed']);
        })->count();

        // Revenue from hosted events
        $hostedRevenue = Order::whereIn('status', ['paid', 'confirmed'])
            ->whereHas('tickets.ticketType', function ($query) use ($hostedEventIds) {
                $query->whereIn('event_id', $hostedEventIds);
            })
            ->sum('total_cents') / 100;

        return [
            'has_venues' => true,
            'venues_count' => count($ownedVenueIds),
            'hosted_events' => $hostedEventsCount,
            'upcoming_hosted_events' => $upcomingHostedEvents,
            'hosted_tickets_sold' => $hostedTicketsSold,
            'hosted_revenue' => $hostedRevenue,
        ];
    }

    private function getChartData(int $tenantId, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $data = [];

        // Get daily totals (total_cents / 100)
        $dailySales = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total_cents) / 100 as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Fill in all days
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
            $data[] = (float) ($dailySales[$dateKey] ?? 0);
            $current->addDay();
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function getTicketChartData(int $tenantId, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $data = [];
        $tooltipData = [];

        // Get tickets with event info, grouped by date
        $tickets = Ticket::with(['ticketType.event', 'order'])
            ->whereHas('order', function ($query) use ($tenantId, $startDate, $endDate) {
                $query->where('tenant_id', $tenantId)
                    ->whereIn('status', ['paid', 'confirmed'])
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

        // Group by date and event
        $dailyTickets = [];
        foreach ($tickets as $ticket) {
            $dateKey = $ticket->order->created_at->format('Y-m-d');
            $eventTitle = $ticket->ticketType?->event?->getTranslation('title', 'ro')
                ?? $ticket->ticketType?->event?->getTranslation('title', 'en')
                ?? 'Eveniment necunoscut';

            if (!isset($dailyTickets[$dateKey])) {
                $dailyTickets[$dateKey] = [
                    'total' => 0,
                    'events' => [],
                ];
            }

            $dailyTickets[$dateKey]['total']++;

            if (!isset($dailyTickets[$dateKey]['events'][$eventTitle])) {
                $dailyTickets[$dateKey]['events'][$eventTitle] = 0;
            }
            $dailyTickets[$dateKey]['events'][$eventTitle]++;
        }

        // Fill in all days
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
            $data[] = $dailyTickets[$dateKey]['total'] ?? 0;

            // Build tooltip data for this day
            $dayEvents = $dailyTickets[$dateKey]['events'] ?? [];
            $tooltipData[] = $dayEvents;

            $current->addDay();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'tooltipData' => $tooltipData,
        ];
    }
}
