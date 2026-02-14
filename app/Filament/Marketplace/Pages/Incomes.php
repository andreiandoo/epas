<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\MarketplaceGiftCard;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use App\Models\ServiceOrder;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class Incomes extends Page
{
    use HasMarketplaceContext;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Incomes';
    protected static ?string $title = 'Incomes';
    protected static string|\UnitEnum|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.marketplace.pages.incomes';

    #[Url]
    public string $period = '30d';

    #[Url]
    public ?string $customFrom = null;

    #[Url]
    public ?string $customTo = null;

    #[Url]
    public ?string $organizerId = null;

    public function mount(): void
    {
        // defaults
    }

    public function updatedPeriod(): void
    {
        if ($this->period !== 'custom') {
            $this->customFrom = null;
            $this->customTo = null;
        }
    }

    public function updatedCustomFrom(): void
    {
        $this->period = 'custom';
    }

    public function updatedCustomTo(): void
    {
        $this->period = 'custom';
    }

    public function updatedOrganizerId(): void
    {
        // triggers re-render
    }

    /**
     * Get the date range from the current period selection.
     */
    protected function getDateRange(): array
    {
        if ($this->period === 'custom' && $this->customFrom && $this->customTo) {
            return [
                Carbon::parse($this->customFrom)->startOfDay(),
                Carbon::parse($this->customTo)->endOfDay(),
            ];
        }

        $days = match ($this->period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        return [
            Carbon::now()->subDays($days)->startOfDay(),
            Carbon::now()->endOfDay(),
        ];
    }

    /**
     * Get the number of days in the selected range.
     */
    protected function getDaysInRange(): int
    {
        [$start, $end] = $this->getDateRange();
        return max(1, (int) $start->diffInDays($end));
    }

    /**
     * Base order query scoped to marketplace and optionally organizer.
     */
    protected function baseOrderQuery()
    {
        $marketplace = static::getMarketplaceClient();
        $query = Order::where('marketplace_client_id', $marketplace?->id)
            ->whereIn('status', ['paid', 'confirmed']);

        if ($this->organizerId) {
            $query->where('marketplace_organizer_id', $this->organizerId);
        }

        return $query;
    }

    /**
     * Get all income data for the view.
     */
    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();

        if (!$marketplace) {
            return [
                'marketplace' => null,
                'stats' => [],
                'breakdown' => [],
                'chartData' => [],
                'organizers' => [],
                'organizerId' => null,
                'selectedOrganizerName' => null,
                'period' => $this->period,
                'customFrom' => $this->customFrom,
                'customTo' => $this->customTo,
                'daysInRange' => 0,
                'topOrganizers' => [],
            ];
        }

        $marketplaceId = $marketplace->id;
        [$startDate, $endDate] = $this->getDateRange();
        $daysInRange = $this->getDaysInRange();

        // ─── Total Sales (gross order totals) ───
        $totalSales = (float) $this->baseOrderQuery()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('total');

        $totalOrders = (int) $this->baseOrderQuery()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->count();

        // ─── Total Commissions ───
        $totalCommissions = (float) $this->baseOrderQuery()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('commission_amount');

        // ─── Refund Fee Revenue ───
        // fees_refund represents the non-refundable fee the marketplace keeps from refunds
        $refundFeeQuery = MarketplaceRefundRequest::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->whereBetween('completed_at', [$startDate, $endDate]);

        if ($this->organizerId) {
            $refundFeeQuery->where('marketplace_organizer_id', $this->organizerId);
        }

        $refundFeeRevenue = (float) $refundFeeQuery->sum('fees_refund');
        $totalRefunds = (int) $refundFeeQuery->count();

        // ─── Gift Card Revenue ───
        // Revenue from gift card purchases (initial_amount when purchased)
        $giftCardQuery = MarketplaceGiftCard::where('marketplace_client_id', $marketplaceId)
            ->whereNotIn('status', ['cancelled', 'revoked'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $giftCardRevenue = (float) $giftCardQuery->sum('initial_amount');
        $giftCardCount = (int) $giftCardQuery->count();

        // ─── Extra Services Revenue ───
        $serviceQuery = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($this->organizerId) {
            $serviceQuery->where('marketplace_organizer_id', $this->organizerId);
        }

        $servicesRevenue = (float) $serviceQuery->sum('total');
        $serviceCount = (int) $serviceQuery->count();

        // Services breakdown by type
        $servicesByType = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->when($this->organizerId, fn ($q) => $q->where('marketplace_organizer_id', $this->organizerId))
            ->select('service_type', DB::raw('SUM(total) as revenue'), DB::raw('COUNT(*) as count'))
            ->groupBy('service_type')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->service_type,
                'label' => match ($row->service_type) {
                    'featuring' => 'Promovare Eveniment',
                    'email' => 'Email Marketing',
                    'tracking' => 'Ad Tracking',
                    'campaign' => 'Creare Campanie',
                    default => ucfirst($row->service_type),
                },
                'revenue' => (float) $row->revenue,
                'count' => (int) $row->count,
            ])
            ->toArray();

        // ─── Grand Total ───
        $grandTotal = $totalCommissions + $refundFeeRevenue + $giftCardRevenue + $servicesRevenue;

        // ─── Averages ───
        $avgDailySales = $daysInRange > 0 ? $totalSales / $daysInRange : 0;
        $avgDailyCommissions = $daysInRange > 0 ? $totalCommissions / $daysInRange : 0;
        $avgDailyRevenue = $daysInRange > 0 ? $grandTotal / $daysInRange : 0;
        $avgOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        $avgCommissionPerOrder = $totalOrders > 0 ? $totalCommissions / $totalOrders : 0;
        $effectiveCommissionRate = $totalSales > 0 ? ($totalCommissions / $totalSales) * 100 : 0;

        // ─── Chart Data (daily breakdown) ───
        $chartData = $this->getChartData($marketplaceId, $startDate, $endDate, $daysInRange);

        // ─── Organizers list for filter ───
        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $selectedOrganizerName = null;
        if ($this->organizerId) {
            $selectedOrganizerName = $organizers[$this->organizerId] ?? null;
        }

        // ─── Top Organizers by revenue in period ───
        $topOrganizers = $this->getTopOrganizers($marketplaceId, $startDate, $endDate);

        // ─── Breakdown ───
        $breakdown = [
            [
                'label' => 'Comisioane din vanzari',
                'value' => $totalCommissions,
                'icon' => 'heroicon-o-receipt-percent',
                'color' => 'emerald',
                'detail' => $totalOrders . ' comenzi | rata efectiva: ' . number_format($effectiveCommissionRate, 1) . '%',
            ],
            [
                'label' => 'Taxa refund (nerambursabila)',
                'value' => $refundFeeRevenue,
                'icon' => 'heroicon-o-arrow-uturn-left',
                'color' => 'amber',
                'detail' => $totalRefunds . ' refunduri procesate',
            ],
            [
                'label' => 'Carduri cadou',
                'value' => $giftCardRevenue,
                'icon' => 'heroicon-o-gift',
                'color' => 'pink',
                'detail' => $giftCardCount . ' carduri vandute',
            ],
            [
                'label' => 'Servicii extra',
                'value' => $servicesRevenue,
                'icon' => 'heroicon-o-bolt',
                'color' => 'violet',
                'detail' => $serviceCount . ' servicii',
            ],
        ];

        return [
            'marketplace' => $marketplace,
            'stats' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'total_commissions' => $totalCommissions,
                'refund_fee_revenue' => $refundFeeRevenue,
                'gift_card_revenue' => $giftCardRevenue,
                'services_revenue' => $servicesRevenue,
                'grand_total' => $grandTotal,
                'avg_daily_sales' => $avgDailySales,
                'avg_daily_commissions' => $avgDailyCommissions,
                'avg_daily_revenue' => $avgDailyRevenue,
                'avg_order_value' => $avgOrderValue,
                'avg_commission_per_order' => $avgCommissionPerOrder,
                'effective_commission_rate' => $effectiveCommissionRate,
            ],
            'breakdown' => $breakdown,
            'servicesByType' => $servicesByType,
            'chartData' => $chartData,
            'organizers' => $organizers,
            'organizerId' => $this->organizerId,
            'selectedOrganizerName' => $selectedOrganizerName,
            'period' => $this->period,
            'customFrom' => $this->customFrom,
            'customTo' => $this->customTo,
            'daysInRange' => $daysInRange,
            'topOrganizers' => $topOrganizers,
        ];
    }

    /**
     * Build daily chart data for sales, commissions, and services.
     */
    protected function getChartData(int $marketplaceId, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $salesData = [];
        $commissionsData = [];
        $servicesData = [];

        // Daily sales & commissions in a single query
        $dailyOrderData = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->when($this->organizerId, fn ($q) => $q->where('marketplace_organizer_id', $this->organizerId))
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(total) as total_sales'),
                DB::raw('SUM(commission_amount) as total_commissions')
            )
            ->groupBy('date')
            ->get();

        $dailySales = $dailyOrderData->pluck('total_sales', 'date')->toArray();
        $dailyCommissions = $dailyOrderData->pluck('total_commissions', 'date')->toArray();

        // Daily services
        $dailyServices = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->when($this->organizerId, fn ($q) => $q->where('marketplace_organizer_id', $this->organizerId))
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Fill all days
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
            $salesData[] = (float) ($dailySales[$dateKey] ?? 0);
            $commissionsData[] = (float) ($dailyCommissions[$dateKey] ?? 0);
            $servicesData[] = (float) ($dailyServices[$dateKey] ?? 0);
            $current->addDay();
        }

        return [
            'labels' => $labels,
            'sales' => $salesData,
            'commissions' => $commissionsData,
            'services' => $servicesData,
        ];
    }

    /**
     * Get top organizers by commission revenue in the period.
     */
    protected function getTopOrganizers(int $marketplaceId, Carbon $startDate, Carbon $endDate): array
    {
        return Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->whereNotNull('marketplace_organizer_id')
            ->select(
                'marketplace_organizer_id',
                DB::raw('SUM(total) as total_sales'),
                DB::raw('SUM(commission_amount) as total_commissions'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy('marketplace_organizer_id')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $organizer = MarketplaceOrganizer::find($row->marketplace_organizer_id);
                return [
                    'id' => $row->marketplace_organizer_id,
                    'name' => $organizer?->company_name ?? $organizer?->name ?? 'Unknown',
                    'total_sales' => (float) $row->total_sales,
                    'total_commissions' => (float) $row->total_commissions,
                    'order_count' => (int) $row->order_count,
                ];
            })
            ->toArray();
    }
}
