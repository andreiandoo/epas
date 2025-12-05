<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Customer;
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
        // Livewire will automatically re-render with new period
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

        // Total sales (sum of paid orders) - total_cents / 100
        $totalSales = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('total_cents') / 100;

        // Total tickets sold (filter through orders since tickets don't have tenant_id)
        $totalTickets = Ticket::whereHas('order', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId)
                ->where('status', 'completed');
        })->count();

        // Total customers
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();

        // Unpaid invoices VALUE - uses 'amount' decimal column
        $unpaidInvoicesValue = $tenant->invoices()
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        // Chart data - daily sales for the selected period
        $chartData = $this->getChartData($tenantId, $startDate, $endDate, $days);

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
            'chartPeriod' => $this->chartPeriod,
        ];
    }

    private function getChartData(int $tenantId, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $data = [];

        // Get daily totals (total_cents / 100)
        $dailySales = Order::where('tenant_id', $tenantId)
            ->where('status', 'completed')
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
}
