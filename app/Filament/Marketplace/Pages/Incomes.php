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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->whereIn('status', ['paid', 'confirmed', 'completed']);

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
                'deltas' => [],
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

        // ─── Previous Period Comparison ───
        $prevStats = $this->getPreviousPeriodStats($marketplaceId);
        $deltas = [
            'total_sales' => self::deltaPercent($totalSales, $prevStats['total_sales']),
            'total_orders' => self::deltaPercent($totalOrders, $prevStats['total_orders']),
            'total_commissions' => self::deltaPercent($totalCommissions, $prevStats['total_commissions']),
            'refund_fee_revenue' => self::deltaPercent($refundFeeRevenue, $prevStats['refund_fee_revenue']),
            'gift_card_revenue' => self::deltaPercent($giftCardRevenue, $prevStats['gift_card_revenue']),
            'services_revenue' => self::deltaPercent($servicesRevenue, $prevStats['services_revenue']),
            'grand_total' => self::deltaPercent($grandTotal, $prevStats['grand_total']),
        ];

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
            'deltas' => $deltas,
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
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
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
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
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

    /**
     * Get the previous period date range (same length, immediately before).
     */
    protected function getPreviousPeriodRange(): array
    {
        [$startDate, $endDate] = $this->getDateRange();
        $daysInRange = $this->getDaysInRange();

        return [
            $startDate->copy()->subDays($daysInRange),
            $startDate->copy()->subSecond(),
        ];
    }

    /**
     * Compute previous period stats for comparison deltas.
     */
    protected function getPreviousPeriodStats(int $marketplaceId): array
    {
        [$prevStart, $prevEnd] = $this->getPreviousPeriodRange();

        $prevOrderQuery = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('paid_at', [$prevStart, $prevEnd])
            ->when($this->organizerId, fn ($q) => $q->where('marketplace_organizer_id', $this->organizerId));

        $prevSales = (float) (clone $prevOrderQuery)->sum('total');
        $prevCommissions = (float) (clone $prevOrderQuery)->sum('commission_amount');
        $prevOrders = (int) (clone $prevOrderQuery)->count();

        $prevRefundFees = (float) MarketplaceRefundRequest::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->whereBetween('completed_at', [$prevStart, $prevEnd])
            ->when($this->organizerId, fn ($q) => $q->where('marketplace_organizer_id', $this->organizerId))
            ->sum('fees_refund');

        $prevGiftCards = (float) MarketplaceGiftCard::where('marketplace_client_id', $marketplaceId)
            ->whereNotIn('status', ['cancelled', 'revoked'])
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('initial_amount');

        $prevServices = (float) ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$prevStart, $prevEnd])
            ->when($this->organizerId, fn ($q) => $q->where('marketplace_organizer_id', $this->organizerId))
            ->sum('total');

        $prevGrandTotal = $prevCommissions + $prevRefundFees + $prevGiftCards + $prevServices;

        return [
            'total_sales' => $prevSales,
            'total_commissions' => $prevCommissions,
            'total_orders' => $prevOrders,
            'refund_fee_revenue' => $prevRefundFees,
            'gift_card_revenue' => $prevGiftCards,
            'services_revenue' => $prevServices,
            'grand_total' => $prevGrandTotal,
        ];
    }

    /**
     * Calculate percentage change between current and previous value.
     */
    protected static function deltaPercent(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Export incomes data as CSV download.
     */
    public function exportCsv(): StreamedResponse
    {
        $data = $this->getViewData();
        $stats = $data['stats'];
        $breakdown = $data['breakdown'];
        $servicesByType = $data['servicesByType'];
        $topOrganizers = $data['topOrganizers'];
        $deltas = $data['deltas'] ?? [];

        $filename = 'incomes-' . ($this->period === 'custom' ? ($this->customFrom . '_' . $this->customTo) : $this->period) . '.csv';

        return response()->streamDownload(function () use ($stats, $breakdown, $servicesByType, $topOrganizers, $deltas) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // Summary
            fputcsv($handle, ['=== SUMAR INCASARI ===']);
            fputcsv($handle, ['Metric', 'Valoare (RON)', 'Variatie vs Perioada Anterioara']);
            fputcsv($handle, ['Vanzari Totale', number_format($stats['total_sales'], 2), $this->formatDelta($deltas['total_sales'] ?? null)]);
            fputcsv($handle, ['Nr. Comenzi', $stats['total_orders'], $this->formatDelta($deltas['total_orders'] ?? null)]);
            fputcsv($handle, ['Comisioane', number_format($stats['total_commissions'], 2), $this->formatDelta($deltas['total_commissions'] ?? null)]);
            fputcsv($handle, ['Taxa Refund', number_format($stats['refund_fee_revenue'], 2), $this->formatDelta($deltas['refund_fee_revenue'] ?? null)]);
            fputcsv($handle, ['Carduri Cadou', number_format($stats['gift_card_revenue'], 2), $this->formatDelta($deltas['gift_card_revenue'] ?? null)]);
            fputcsv($handle, ['Servicii Extra', number_format($stats['services_revenue'], 2), $this->formatDelta($deltas['services_revenue'] ?? null)]);
            fputcsv($handle, ['TOTAL VENIT MARKETPLACE', number_format($stats['grand_total'], 2), $this->formatDelta($deltas['grand_total'] ?? null)]);
            fputcsv($handle, []);

            // Averages
            fputcsv($handle, ['=== MEDII ===']);
            fputcsv($handle, ['Medie Zilnica Vanzari', number_format($stats['avg_daily_sales'], 2)]);
            fputcsv($handle, ['Medie Zilnica Comisioane', number_format($stats['avg_daily_commissions'], 2)]);
            fputcsv($handle, ['Medie Zilnica Venit Total', number_format($stats['avg_daily_revenue'], 2)]);
            fputcsv($handle, ['Valoare Medie Comanda', number_format($stats['avg_order_value'], 2)]);
            fputcsv($handle, ['Comision Mediu/Comanda', number_format($stats['avg_commission_per_order'], 2)]);
            fputcsv($handle, ['Rata Efectiva Comision %', number_format($stats['effective_commission_rate'], 1) . '%']);
            fputcsv($handle, []);

            // Services breakdown
            if (!empty($servicesByType)) {
                fputcsv($handle, ['=== DETALIU SERVICII EXTRA ===']);
                fputcsv($handle, ['Tip Serviciu', 'Nr. Comenzi', 'Venit (RON)']);
                foreach ($servicesByType as $svc) {
                    fputcsv($handle, [$svc['label'], $svc['count'], number_format($svc['revenue'], 2)]);
                }
                fputcsv($handle, []);
            }

            // Top organizers
            if (!empty($topOrganizers)) {
                fputcsv($handle, ['=== TOP ORGANIZATORI ===']);
                fputcsv($handle, ['Organizator', 'Nr. Comenzi', 'Vanzari (RON)', 'Comisioane (RON)']);
                foreach ($topOrganizers as $org) {
                    fputcsv($handle, [$org['name'], $org['order_count'], number_format($org['total_sales'], 2), number_format($org['total_commissions'], 2)]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function formatDelta(?float $delta): string
    {
        if ($delta === null) {
            return 'N/A';
        }

        $sign = $delta >= 0 ? '+' : '';
        return $sign . number_format($delta, 1) . '%';
    }
}
