<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceRefundRequest;
use App\Models\MarketplaceTicketType;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesAnalysisService
{
    protected int $marketplaceId;
    protected ?string $dateRange;
    protected ?int $categoryId;
    protected ?string $currency;
    protected array $paidStatuses = ['paid', 'confirmed', 'completed'];

    public function __construct(int $marketplaceId, ?string $dateRange = '90d', ?int $categoryId = null, ?string $currency = null)
    {
        $this->marketplaceId = $marketplaceId;
        $this->dateRange = $dateRange;
        $this->categoryId = $categoryId;
        $this->currency = $currency;
    }

    protected function getStartDate(): ?Carbon
    {
        return match ($this->dateRange) {
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            '6m' => Carbon::now()->subMonths(6),
            '1y' => Carbon::now()->subYear(),
            'all' => null,
            default => Carbon::now()->subDays(90),
        };
    }

    protected function baseOrderQuery()
    {
        $q = Order::where('marketplace_client_id', $this->marketplaceId)
            ->whereIn('status', $this->paidStatuses)
            ->where('source', '!=', 'test_order')
            ->where('source', '!=', 'external_import');

        if ($start = $this->getStartDate()) {
            $q->where('created_at', '>=', $start);
        }
        if ($this->currency) {
            $q->where('currency', $this->currency);
        }
        if ($this->categoryId) {
            $q->whereHas('marketplaceEvent', fn($eq) => $eq->where('marketplace_event_category_id', $this->categoryId));
        }
        return $q;
    }

    protected function previousPeriodOrderQuery()
    {
        $start = $this->getStartDate();
        if (!$start) return null;

        $days = $start->diffInDays(Carbon::now());
        $prevStart = (clone $start)->subDays($days);

        $q = Order::where('marketplace_client_id', $this->marketplaceId)
            ->whereIn('status', $this->paidStatuses)
            ->where('source', '!=', 'test_order')
            ->where('source', '!=', 'external_import')
            ->whereBetween('created_at', [$prevStart, $start]);

        if ($this->currency) {
            $q->where('currency', $this->currency);
        }
        if ($this->categoryId) {
            $q->whereHas('marketplaceEvent', fn($eq) => $eq->where('marketplace_event_category_id', $this->categoryId));
        }
        return $q;
    }

    // ==========================================
    // KPI Stats
    // ==========================================

    public function getKpiStats(): array
    {
        $current = $this->baseOrderQuery();
        $totalRevenue = (clone $current)->sum('total');
        $totalOrders = (clone $current)->count();
        $totalTickets = Ticket::whereIn('order_id', (clone $current)->select('id'))->whereIn('status', ['valid', 'used'])->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $prev = $this->previousPeriodOrderQuery();
        $prevRevenue = $prev ? $prev->sum('total') : 0;
        $revenueChange = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

        $prevOrders = $prev ? (clone $prev)->count() : 0;
        $ordersChange = $prevOrders > 0 ? round((($totalOrders - $prevOrders) / $prevOrders) * 100, 1) : 0;

        $repeatCustomers = $this->getRepeatCustomerRateQuick();

        $bestDay = (clone $current)
            ->selectRaw('DAYOFWEEK(created_at) as dow, SUM(total) as rev')
            ->groupBy('dow')
            ->orderByDesc('rev')
            ->first();

        $dayNames = [1 => 'Dum', 2 => 'Lun', 3 => 'Mar', 4 => 'Mie', 5 => 'Joi', 6 => 'Vin', 7 => 'Sam'];
        $bestDayName = $bestDay ? ($dayNames[$bestDay->dow] ?? '-') : '-';

        return [
            'total_revenue' => (float) $totalRevenue,
            'total_orders' => $totalOrders,
            'total_tickets' => $totalTickets,
            'avg_order_value' => round((float) $avgOrderValue, 2),
            'revenue_change' => $revenueChange,
            'orders_change' => $ordersChange,
            'repeat_rate' => $repeatCustomers,
            'best_day' => $bestDayName,
        ];
    }

    protected function getRepeatCustomerRateQuick(): float
    {
        $orderIds = $this->baseOrderQuery()->pluck('id');
        $customers = Order::whereIn('id', $orderIds)
            ->whereNotNull('marketplace_customer_id')
            ->selectRaw('marketplace_customer_id, COUNT(*) as cnt')
            ->groupBy('marketplace_customer_id')
            ->get();

        $total = $customers->count();
        $repeat = $customers->where('cnt', '>', 1)->count();

        return $total > 0 ? round(($repeat / $total) * 100, 1) : 0;
    }

    // ==========================================
    // Tab 1: Performance Patterns
    // ==========================================

    public function getDayOfWeekRevenue(): array
    {
        $data = $this->baseOrderQuery()
            ->selectRaw('DAYOFWEEK(created_at) as dow, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('dow')
            ->orderBy('dow')
            ->get()
            ->keyBy('dow');

        $dayNames = [2 => 'Luni', 3 => 'Marti', 4 => 'Miercuri', 5 => 'Joi', 6 => 'Vineri', 7 => 'Sambata', 1 => 'Duminica'];
        $labels = [];
        $revenue = [];
        $orders = [];

        foreach ($dayNames as $dow => $name) {
            $labels[] = $name;
            $revenue[] = (float) ($data[$dow]->revenue ?? 0);
            $orders[] = (int) ($data[$dow]->orders ?? 0);
        }

        return ['labels' => $labels, 'revenue' => $revenue, 'orders' => $orders];
    }

    public function getDayOfWeekTickets(): array
    {
        $orderIds = $this->baseOrderQuery()->select('id');

        $data = Ticket::whereIn('order_id', $orderIds)
            ->whereIn('status', ['valid', 'used'])
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->selectRaw('DAYOFWEEK(orders.created_at) as dow, COUNT(tickets.id) as tickets')
            ->groupBy('dow')
            ->orderBy('dow')
            ->get()
            ->keyBy('dow');

        $dayNames = [2 => 'Luni', 3 => 'Marti', 4 => 'Miercuri', 5 => 'Joi', 6 => 'Vineri', 7 => 'Sambata', 1 => 'Duminica'];
        $labels = [];
        $tickets = [];

        foreach ($dayNames as $dow => $name) {
            $labels[] = $name;
            $tickets[] = (int) ($data[$dow]->tickets ?? 0);
        }

        return ['labels' => $labels, 'tickets' => $tickets];
    }

    public function getCategoryDayHeatmap(): array
    {
        $data = $this->baseOrderQuery()
            ->join('marketplace_events', 'orders.marketplace_event_id', '=', 'marketplace_events.id')
            ->join('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->selectRaw('marketplace_event_categories.id as cat_id, marketplace_event_categories.name as cat_name, DAYOFWEEK(orders.created_at) as dow, SUM(orders.total) as revenue')
            ->groupBy('cat_id', 'cat_name', 'dow')
            ->get();

        $dayMap = [2 => 'Lun', 3 => 'Mar', 4 => 'Mie', 5 => 'Joi', 6 => 'Vin', 7 => 'Sam', 1 => 'Dum'];
        $categories = [];
        $matrix = [];

        foreach ($data as $row) {
            $catName = is_array(json_decode($row->cat_name, true)) ? (json_decode($row->cat_name, true)['ro'] ?? json_decode($row->cat_name, true)['en'] ?? 'N/A') : ($row->cat_name ?? 'N/A');
            if (!isset($matrix[$row->cat_id])) {
                $categories[$row->cat_id] = $catName;
                $matrix[$row->cat_id] = array_fill_keys(array_keys($dayMap), 0);
            }
            $matrix[$row->cat_id][$row->dow] = (float) $row->revenue;
        }

        return ['days' => array_values($dayMap), 'categories' => $categories, 'matrix' => $matrix, 'dayKeys' => array_keys($dayMap)];
    }

    public function getHourlyHeatmap(): array
    {
        $data = $this->baseOrderQuery()
            ->selectRaw('DAYOFWEEK(created_at) as dow, HOUR(created_at) as hour, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('dow', 'hour')
            ->get();

        $dayMap = [2 => 'Lun', 3 => 'Mar', 4 => 'Mie', 5 => 'Joi', 6 => 'Vin', 7 => 'Sam', 1 => 'Dum'];
        $matrix = [];

        foreach ($dayMap as $dow => $name) {
            $matrix[$name] = array_fill(0, 24, ['revenue' => 0, 'orders' => 0]);
        }

        foreach ($data as $row) {
            $dayName = $dayMap[$row->dow] ?? null;
            if ($dayName) {
                $matrix[$dayName][$row->hour] = [
                    'revenue' => (float) $row->revenue,
                    'orders' => (int) $row->orders,
                ];
            }
        }

        return ['days' => array_values($dayMap), 'hours' => range(0, 23), 'matrix' => $matrix];
    }

    public function getPeakSalesWindows(): array
    {
        $data = $this->baseOrderQuery()
            ->selectRaw('DAYOFWEEK(created_at) as dow, HOUR(created_at) as hour, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('dow', 'hour')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $dayNames = [1 => 'Duminica', 2 => 'Luni', 3 => 'Marti', 4 => 'Miercuri', 5 => 'Joi', 6 => 'Vineri', 7 => 'Sambata'];

        return $data->map(fn($row) => [
            'day' => $dayNames[$row->dow] ?? '-',
            'hour' => sprintf('%02d:00-%02d:00', $row->hour, $row->hour + 1),
            'revenue' => (float) $row->revenue,
            'orders' => (int) $row->orders,
        ])->toArray();
    }

    // ==========================================
    // Tab 2: Predictions & Forecasting
    // ==========================================

    public function getMonthlyForecast(): array
    {
        $monthlyData = Order::where('marketplace_client_id', $this->marketplaceId)
            ->whereIn('status', $this->paidStatuses)
            ->where('source', '!=', 'test_order')
            ->where('source', '!=', 'external_import')
            ->where('created_at', '>=', Carbon::now()->subMonths(24))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total) as revenue, COUNT(*) as orders")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = [];
        $actuals = [];
        $predicted = [];

        foreach ($monthlyData as $row) {
            $labels[] = Carbon::parse($row->month . '-01')->format('M Y');
            $actuals[] = (float) $row->revenue;
        }

        // Holt-Winters triple exponential smoothing
        $forecast = $this->holtWintersForecast($actuals, 12, 3);

        $currentMonth = Carbon::now()->format('Y-m');
        $currentMonthPartial = $monthlyData->firstWhere('month', $currentMonth);

        // Add forecast months
        for ($i = 0; $i < 3; $i++) {
            $futureMonth = Carbon::now()->addMonths($i + 1);
            $labels[] = $futureMonth->format('M Y');
            $actuals[] = null;
            $predicted[] = round($forecast[$i] ?? 0, 2);
        }

        // Add prediction line starting from last actual
        $predictedFull = array_fill(0, count($actuals) - 3, null);
        if (count($actuals) > 3) {
            $predictedFull[count($actuals) - 4] = $actuals[count($actuals) - 4];
        }
        $predictedFull = array_merge($predictedFull, $predicted);

        return ['labels' => $labels, 'actuals' => $actuals, 'predicted' => $predictedFull];
    }

    public function getYearlyForecast(): array
    {
        $yearlyData = Order::where('marketplace_client_id', $this->marketplaceId)
            ->whereIn('status', $this->paidStatuses)
            ->where('source', '!=', 'test_order')
            ->where('source', '!=', 'external_import')
            ->selectRaw("YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as revenue")
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');

        $labels = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $thisYear = array_fill(0, 12, null);
        $lastYear = array_fill(0, 12, null);
        $predicted = array_fill(0, 12, null);

        foreach ($yearlyData as $row) {
            $idx = $row->month - 1;
            if ((int) $row->year === $currentYear) {
                $thisYear[$idx] = (float) $row->revenue;
            } elseif ((int) $row->year === $currentYear - 1) {
                $lastYear[$idx] = (float) $row->revenue;
            }
        }

        // Simple seasonal prediction for remaining months
        $completedMonths = array_filter($thisYear, fn($v) => $v !== null);
        $avgGrowth = 1.0;

        if (count($completedMonths) > 0) {
            $growths = [];
            foreach ($completedMonths as $idx => $val) {
                if ($lastYear[$idx] && $lastYear[$idx] > 0) {
                    $growths[] = $val / $lastYear[$idx];
                }
            }
            $avgGrowth = count($growths) > 0 ? array_sum($growths) / count($growths) : 1.0;
        }

        for ($m = $currentMonth; $m < 12; $m++) {
            if ($lastYear[$m] !== null) {
                $predicted[$m] = round($lastYear[$m] * $avgGrowth, 2);
            }
        }

        // Bridge: connect last actual to first predicted
        if ($currentMonth > 0 && $currentMonth < 12 && $thisYear[$currentMonth - 1] !== null) {
            $predicted[$currentMonth - 1] = $thisYear[$currentMonth - 1];
        }

        return [
            'labels' => $labels,
            'this_year' => $thisYear,
            'last_year' => $lastYear,
            'predicted' => $predicted,
            'current_year' => $currentYear,
        ];
    }

    public function getSeasonalityIndex(): array
    {
        $data = Order::where('marketplace_client_id', $this->marketplaceId)
            ->whereIn('status', $this->paidStatuses)
            ->where('source', '!=', 'test_order')
            ->where('source', '!=', 'external_import')
            ->selectRaw('MONTH(created_at) as month, SUM(total) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthNames = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $values = [];
        for ($i = 1; $i <= 12; $i++) {
            $values[] = (float) ($data[$i]->revenue ?? 0);
        }

        $avg = count(array_filter($values)) > 0 ? array_sum($values) / count(array_filter($values)) : 1;
        $index = array_map(fn($v) => $avg > 0 ? round(($v / $avg) * 100, 1) : 0, $values);

        return ['labels' => $monthNames, 'index' => $index, 'baseline' => 100];
    }

    public function getSalesVelocity(): array
    {
        $events = MarketplaceEvent::where('marketplace_client_id', $this->marketplaceId)
            ->where('status', 'published')
            ->whereNotNull('starts_at')
            ->with(['eventCategory'])
            ->withCount(['orders as paid_orders_count' => fn($q) => $q->whereIn('status', $this->paidStatuses)])
            ->having('paid_orders_count', '>', 0)
            ->limit(50)
            ->get();

        return $events->map(function ($event) {
            $firstOrder = $event->orders()->whereIn('status', $this->paidStatuses)->orderBy('created_at')->first();
            $lastOrder = $event->orders()->whereIn('status', $this->paidStatuses)->orderByDesc('created_at')->first();

            $salesDays = 1;
            if ($firstOrder && $lastOrder) {
                $salesDays = max(1, $firstOrder->created_at->diffInDays($lastOrder->created_at));
            }

            $catName = $event->eventCategory ? $event->eventCategory->getLocalizedName('ro') : ($event->category ?? 'N/A');

            return [
                'name' => $event->name,
                'category' => $catName,
                'tickets_sold' => $event->tickets_sold ?? 0,
                'capacity' => $event->capacity ?? 0,
                'sell_through' => $event->capacity > 0 ? round(($event->tickets_sold / $event->capacity) * 100, 1) : 0,
                'sales_days' => $salesDays,
                'tickets_per_day' => $salesDays > 0 ? round(($event->tickets_sold ?? 0) / $salesDays, 1) : 0,
            ];
        })->sortByDesc('tickets_per_day')->values()->take(20)->toArray();
    }

    protected function holtWintersForecast(array $data, int $seasonLength = 12, int $periods = 3): array
    {
        $n = count($data);
        if ($n < $seasonLength * 2) {
            // Fallback: simple moving average
            $avg = $n > 0 ? array_sum($data) / $n : 0;
            return array_fill(0, $periods, $avg);
        }

        $alpha = 0.3;
        $beta = 0.1;
        $gamma = 0.3;

        // Initialize level and trend
        $level = array_sum(array_slice($data, 0, $seasonLength)) / $seasonLength;
        $trend = 0;
        for ($i = 0; $i < $seasonLength; $i++) {
            $trend += ($data[$seasonLength + $i] - $data[$i]);
        }
        $trend /= ($seasonLength * $seasonLength);

        // Initialize seasonal
        $seasonal = [];
        for ($i = 0; $i < $seasonLength; $i++) {
            $seasonal[$i] = $level > 0 ? $data[$i] / $level : 1;
        }

        // Run smoothing
        for ($i = $seasonLength; $i < $n; $i++) {
            $si = $i % $seasonLength;
            $val = $data[$i];
            $oldLevel = $level;

            $level = $alpha * ($seasonal[$si] > 0 ? $val / $seasonal[$si] : $val) + (1 - $alpha) * ($oldLevel + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonal[$si] = $gamma * ($level > 0 ? $val / $level : 1) + (1 - $gamma) * $seasonal[$si];
        }

        // Forecast
        $forecast = [];
        for ($i = 1; $i <= $periods; $i++) {
            $si = ($n + $i - 1) % $seasonLength;
            $forecast[] = max(0, ($level + $i * $trend) * $seasonal[$si]);
        }

        return $forecast;
    }

    // ==========================================
    // Tab 3: Revenue Optimization
    // ==========================================

    public function getGoldenPriceZone(): array
    {
        $data = DB::table('marketplace_ticket_types')
            ->join('marketplace_events', 'marketplace_ticket_types.marketplace_event_id', '=', 'marketplace_events.id')
            ->leftJoin('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->where('marketplace_events.marketplace_client_id', $this->marketplaceId)
            ->where('marketplace_ticket_types.quantity_sold', '>', 0)
            ->select([
                'marketplace_event_categories.id as cat_id',
                'marketplace_event_categories.name as cat_name',
                'marketplace_ticket_types.price',
                'marketplace_ticket_types.quantity_sold',
                'marketplace_ticket_types.quantity',
            ])
            ->get();

        $categories = [];
        foreach ($data as $row) {
            $catName = 'Fara categorie';
            if ($row->cat_name) {
                $decoded = json_decode($row->cat_name, true);
                $catName = is_array($decoded) ? ($decoded['ro'] ?? $decoded['en'] ?? 'N/A') : $row->cat_name;
            }
            $catKey = $row->cat_id ?? 0;

            if (!isset($categories[$catKey])) {
                $categories[$catKey] = ['name' => $catName, 'prices' => []];
            }
            $categories[$catKey]['prices'][] = [
                'price' => (float) $row->price,
                'sold' => (int) $row->quantity_sold,
                'revenue' => (float) $row->price * (int) $row->quantity_sold,
            ];
        }

        $result = [];
        foreach ($categories as $catKey => $cat) {
            $prices = collect($cat['prices']);
            $totalSold = $prices->sum('sold');
            $totalRevenue = $prices->sum('revenue');

            // Find golden zone: price range with most volume
            $sorted = $prices->sortBy('price')->values();
            $allPrices = $sorted->pluck('price')->toArray();

            $minPrice = !empty($allPrices) ? min($allPrices) : 0;
            $maxPrice = !empty($allPrices) ? max($allPrices) : 0;

            // Weighted median price
            $weightedPrices = [];
            foreach ($prices as $p) {
                for ($i = 0; $i < $p['sold']; $i++) {
                    $weightedPrices[] = $p['price'];
                }
            }
            sort($weightedPrices);
            $medianIdx = (int) (count($weightedPrices) / 2);
            $goldenPrice = $weightedPrices[$medianIdx] ?? 0;

            // Golden zone: 20% around median
            $goldenMin = round($goldenPrice * 0.8, 2);
            $goldenMax = round($goldenPrice * 1.2, 2);
            $goldenSold = $prices->filter(fn($p) => $p['price'] >= $goldenMin && $p['price'] <= $goldenMax)->sum('sold');
            $goldenPct = $totalSold > 0 ? round(($goldenSold / $totalSold) * 100, 1) : 0;

            $result[] = [
                'category' => $cat['name'],
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'golden_min' => $goldenMin,
                'golden_max' => $goldenMax,
                'golden_price' => $goldenPrice,
                'golden_pct' => $goldenPct,
                'total_sold' => $totalSold,
                'total_revenue' => $totalRevenue,
            ];
        }

        return collect($result)->sortByDesc('total_revenue')->values()->toArray();
    }

    public function getPriceVolumeAnalysis(): array
    {
        return DB::table('marketplace_ticket_types')
            ->join('marketplace_events', 'marketplace_ticket_types.marketplace_event_id', '=', 'marketplace_events.id')
            ->leftJoin('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->where('marketplace_events.marketplace_client_id', $this->marketplaceId)
            ->where('marketplace_ticket_types.quantity_sold', '>', 0)
            ->select([
                'marketplace_event_categories.name as cat_name',
                'marketplace_ticket_types.price',
                'marketplace_ticket_types.quantity_sold',
                'marketplace_events.name as event_name',
            ])
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $catName = 'N/A';
                if ($row->cat_name) {
                    $decoded = json_decode($row->cat_name, true);
                    $catName = is_array($decoded) ? ($decoded['ro'] ?? $decoded['en'] ?? 'N/A') : $row->cat_name;
                }
                return [
                    'price' => (float) $row->price,
                    'volume' => (int) $row->quantity_sold,
                    'revenue' => (float) $row->price * (int) $row->quantity_sold,
                    'category' => $catName,
                    'event' => $row->event_name,
                ];
            })
            ->toArray();
    }

    public function getRevenueConcentration(): array
    {
        $events = MarketplaceEvent::where('marketplace_client_id', $this->marketplaceId)
            ->where('revenue', '>', 0)
            ->orderByDesc('revenue')
            ->select(['name', 'revenue', 'tickets_sold'])
            ->limit(50)
            ->get();

        $totalRevenue = $events->sum('revenue');
        $cumulative = 0;
        $result = [];

        foreach ($events as $i => $event) {
            $cumulative += $event->revenue;
            $result[] = [
                'name' => $event->name,
                'revenue' => (float) $event->revenue,
                'tickets' => (int) $event->tickets_sold,
                'pct' => $totalRevenue > 0 ? round(($event->revenue / $totalRevenue) * 100, 1) : 0,
                'cumulative_pct' => $totalRevenue > 0 ? round(($cumulative / $totalRevenue) * 100, 1) : 0,
                'event_pct' => round((($i + 1) / $events->count()) * 100, 1),
            ];
        }

        return $result;
    }

    public function getRepeatCustomerAnalysis(): array
    {
        $orderSubquery = $this->baseOrderQuery()
            ->whereNotNull('marketplace_customer_id')
            ->selectRaw('marketplace_customer_id, COUNT(*) as order_count, SUM(total) as total_spent')
            ->groupBy('marketplace_customer_id');

        $stats = DB::query()->fromSub($orderSubquery, 'customer_orders')
            ->selectRaw('
                COUNT(*) as total_customers,
                SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as repeat_customers,
                AVG(order_count) as avg_orders,
                AVG(total_spent) as avg_total_spent,
                AVG(CASE WHEN order_count > 1 THEN total_spent ELSE NULL END) as avg_repeat_spent,
                AVG(CASE WHEN order_count = 1 THEN total_spent ELSE NULL END) as avg_single_spent
            ')
            ->first();

        // Frequency distribution
        $distribution = DB::query()->fromSub($orderSubquery, 'customer_orders')
            ->selectRaw('order_count, COUNT(*) as customers')
            ->groupBy('order_count')
            ->orderBy('order_count')
            ->limit(10)
            ->get();

        return [
            'total_customers' => (int) ($stats->total_customers ?? 0),
            'repeat_customers' => (int) ($stats->repeat_customers ?? 0),
            'repeat_rate' => $stats->total_customers > 0 ? round(($stats->repeat_customers / $stats->total_customers) * 100, 1) : 0,
            'avg_orders' => round((float) ($stats->avg_orders ?? 0), 1),
            'avg_total_spent' => round((float) ($stats->avg_total_spent ?? 0), 2),
            'avg_repeat_spent' => round((float) ($stats->avg_repeat_spent ?? 0), 2),
            'avg_single_spent' => round((float) ($stats->avg_single_spent ?? 0), 2),
            'distribution' => $distribution->map(fn($r) => [
                'orders' => $r->order_count,
                'customers' => $r->customers,
            ])->toArray(),
        ];
    }

    public function getBookingLeadTime(): array
    {
        $data = $this->baseOrderQuery()
            ->join('marketplace_events', 'orders.marketplace_event_id', '=', 'marketplace_events.id')
            ->leftJoin('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->whereNotNull('marketplace_events.starts_at')
            ->selectRaw('marketplace_event_categories.name as cat_name, AVG(DATEDIFF(marketplace_events.starts_at, orders.created_at)) as avg_lead_days, COUNT(*) as orders')
            ->groupBy('cat_name')
            ->having('orders', '>=', 3)
            ->get();

        return $data->map(function ($row) {
            $catName = 'Fara categorie';
            if ($row->cat_name) {
                $decoded = json_decode($row->cat_name, true);
                $catName = is_array($decoded) ? ($decoded['ro'] ?? $decoded['en'] ?? 'N/A') : $row->cat_name;
            }
            return [
                'category' => $catName,
                'avg_lead_days' => round((float) $row->avg_lead_days, 1),
                'orders' => (int) $row->orders,
            ];
        })->sortByDesc('avg_lead_days')->values()->toArray();
    }

    public function getRefundRateByCategory(): array
    {
        $refundData = MarketplaceRefundRequest::where('marketplace_client_id', $this->marketplaceId)
            ->join('marketplace_events', 'marketplace_refund_requests.marketplace_event_id', '=', 'marketplace_events.id')
            ->leftJoin('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->selectRaw('marketplace_event_categories.name as cat_name, COUNT(*) as refund_count, SUM(marketplace_refund_requests.requested_amount) as refund_amount')
            ->groupBy('cat_name')
            ->get();

        $orderData = $this->baseOrderQuery()
            ->join('marketplace_events', 'orders.marketplace_event_id', '=', 'marketplace_events.id')
            ->leftJoin('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->selectRaw('marketplace_event_categories.name as cat_name, COUNT(*) as order_count, SUM(orders.total) as total_revenue')
            ->groupBy('cat_name')
            ->get()
            ->keyBy('cat_name');

        return $refundData->map(function ($row) use ($orderData) {
            $catName = 'Fara categorie';
            if ($row->cat_name) {
                $decoded = json_decode($row->cat_name, true);
                $catName = is_array($decoded) ? ($decoded['ro'] ?? $decoded['en'] ?? 'N/A') : $row->cat_name;
            }

            $orders = $orderData[$row->cat_name] ?? null;
            $orderCount = $orders ? (int) $orders->order_count : 0;
            $totalRevenue = $orders ? (float) $orders->total_revenue : 0;

            return [
                'category' => $catName,
                'refund_count' => (int) $row->refund_count,
                'refund_amount' => (float) $row->refund_amount,
                'order_count' => $orderCount,
                'refund_rate' => $orderCount > 0 ? round(($row->refund_count / $orderCount) * 100, 1) : 0,
            ];
        })->sortByDesc('refund_rate')->values()->toArray();
    }

    // ==========================================
    // Tab 4: Audience Intelligence
    // ==========================================

    public function getRfmSegmentation(): array
    {
        $customers = $this->baseOrderQuery()
            ->whereNotNull('marketplace_customer_id')
            ->selectRaw('marketplace_customer_id, MAX(created_at) as last_order, COUNT(*) as frequency, SUM(total) as monetary')
            ->groupBy('marketplace_customer_id')
            ->get();

        if ($customers->isEmpty()) {
            return ['segments' => [], 'total' => 0];
        }

        $now = Carbon::now();
        $maxRecency = $customers->max(fn($c) => $now->diffInDays(Carbon::parse($c->last_order)));
        $maxFreq = $customers->max('frequency');
        $maxMoney = $customers->max('monetary');

        $segments = ['Champions' => 0, 'Fideli' => 0, 'Potentiali' => 0, 'Noi' => 0, 'La Risc' => 0, 'Pierduti' => 0];

        foreach ($customers as $c) {
            $recencyDays = $now->diffInDays(Carbon::parse($c->last_order));
            $r = $maxRecency > 0 ? 1 - ($recencyDays / $maxRecency) : 1;
            $f = $maxFreq > 0 ? $c->frequency / $maxFreq : 0;
            $m = $maxMoney > 0 ? $c->monetary / $maxMoney : 0;

            $score = ($r + $f + $m) / 3;

            if ($score >= 0.7 && $f >= 0.5) $segments['Champions']++;
            elseif ($f >= 0.4 && $m >= 0.3) $segments['Fideli']++;
            elseif ($score >= 0.5) $segments['Potentiali']++;
            elseif ($r >= 0.7 && $f < 0.3) $segments['Noi']++;
            elseif ($r < 0.3 && $f >= 0.2) $segments['La Risc']++;
            else $segments['Pierduti']++;
        }

        return ['segments' => $segments, 'total' => $customers->count()];
    }

    public function getGeographicRevenue(): array
    {
        $data = $this->baseOrderQuery()
            ->join('marketplace_events', 'orders.marketplace_event_id', '=', 'marketplace_events.id')
            ->selectRaw("COALESCE(marketplace_events.venue_city, 'Necunoscut') as city, SUM(orders.total) as revenue, COUNT(orders.id) as orders")
            ->groupBy('city')
            ->orderByDesc('revenue')
            ->limit(15)
            ->get();

        return $data->map(fn($row) => [
            'city' => $row->city,
            'revenue' => (float) $row->revenue,
            'orders' => (int) $row->orders,
        ])->toArray();
    }

    public function getCrossCategoryAffinity(): array
    {
        $customerCategories = $this->baseOrderQuery()
            ->join('marketplace_events', 'orders.marketplace_event_id', '=', 'marketplace_events.id')
            ->join('marketplace_event_categories', 'marketplace_events.marketplace_event_category_id', '=', 'marketplace_event_categories.id')
            ->whereNotNull('orders.marketplace_customer_id')
            ->select(['orders.marketplace_customer_id', 'marketplace_event_categories.id as cat_id', 'marketplace_event_categories.name as cat_name'])
            ->distinct()
            ->get();

        $customerCats = [];
        $catNames = [];
        foreach ($customerCategories as $row) {
            $customerCats[$row->marketplace_customer_id][] = $row->cat_id;
            if (!isset($catNames[$row->cat_id])) {
                $decoded = json_decode($row->cat_name, true);
                $catNames[$row->cat_id] = is_array($decoded) ? ($decoded['ro'] ?? $decoded['en'] ?? 'N/A') : ($row->cat_name ?? 'N/A');
            }
        }

        // Build affinity matrix
        $pairs = [];
        foreach ($customerCats as $cats) {
            if (count($cats) < 2) continue;
            $cats = array_unique($cats);
            sort($cats);
            for ($i = 0; $i < count($cats); $i++) {
                for ($j = $i + 1; $j < count($cats); $j++) {
                    $key = $cats[$i] . '-' . $cats[$j];
                    $pairs[$key] = ($pairs[$key] ?? 0) + 1;
                }
            }
        }

        arsort($pairs);
        $topPairs = array_slice($pairs, 0, 10, true);

        $result = [];
        foreach ($topPairs as $key => $count) {
            [$catA, $catB] = explode('-', $key);
            $result[] = [
                'category_a' => $catNames[(int) $catA] ?? 'N/A',
                'category_b' => $catNames[(int) $catB] ?? 'N/A',
                'shared_customers' => $count,
            ];
        }

        return $result;
    }

    // ==========================================
    // Tab 5: Operational Insights
    // ==========================================

    public function getOrganizerLeaderboard(): array
    {
        return MarketplaceOrganizer::where('marketplace_client_id', $this->marketplaceId)
            ->where('status', 'active')
            ->where('total_revenue', '>', 0)
            ->orderByDesc('total_revenue')
            ->limit(15)
            ->get()
            ->map(fn($o) => [
                'name' => $o->name ?? $o->company_name ?? 'N/A',
                'revenue' => (float) $o->total_revenue,
                'tickets' => (int) $o->total_tickets_sold,
                'events' => (int) $o->total_events,
            ])
            ->toArray();
    }

    public function getCapacityUtilization(): array
    {
        $events = MarketplaceEvent::where('marketplace_client_id', $this->marketplaceId)
            ->where('status', 'published')
            ->where('capacity', '>', 0)
            ->with('eventCategory')
            ->select(['id', 'name', 'capacity', 'tickets_sold', 'marketplace_event_category_id'])
            ->get();

        $byCategory = [];
        foreach ($events as $event) {
            $catName = $event->eventCategory ? $event->eventCategory->getLocalizedName('ro') : ($event->category ?? 'General');
            if (!isset($byCategory[$catName])) {
                $byCategory[$catName] = ['capacity' => 0, 'sold' => 0, 'events' => 0];
            }
            $byCategory[$catName]['capacity'] += $event->capacity;
            $byCategory[$catName]['sold'] += $event->tickets_sold ?? 0;
            $byCategory[$catName]['events']++;
        }

        $result = [];
        foreach ($byCategory as $cat => $data) {
            $result[] = [
                'category' => $cat,
                'capacity' => $data['capacity'],
                'sold' => $data['sold'],
                'utilization' => $data['capacity'] > 0 ? round(($data['sold'] / $data['capacity']) * 100, 1) : 0,
                'events' => $data['events'],
            ];
        }

        return collect($result)->sortByDesc('utilization')->values()->toArray();
    }

    public function getDiscountImpact(): array
    {
        $withDiscount = (clone $this->baseOrderQuery())
            ->where('discount_amount', '>', 0)
            ->selectRaw('COUNT(*) as orders, SUM(total) as revenue, SUM(discount_amount) as discount_total, AVG(total) as avg_order')
            ->first();

        $withoutDiscount = (clone $this->baseOrderQuery())
            ->where(fn($q) => $q->where('discount_amount', '<=', 0)->orWhereNull('discount_amount'))
            ->selectRaw('COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_order')
            ->first();

        return [
            'with_discount' => [
                'orders' => (int) ($withDiscount->orders ?? 0),
                'revenue' => (float) ($withDiscount->revenue ?? 0),
                'discount_total' => (float) ($withDiscount->discount_total ?? 0),
                'avg_order' => round((float) ($withDiscount->avg_order ?? 0), 2),
            ],
            'without_discount' => [
                'orders' => (int) ($withoutDiscount->orders ?? 0),
                'revenue' => (float) ($withoutDiscount->revenue ?? 0),
                'avg_order' => round((float) ($withoutDiscount->avg_order ?? 0), 2),
            ],
        ];
    }

    public function getPaymentMethodDistribution(): array
    {
        $data = $this->baseOrderQuery()
            ->selectRaw("COALESCE(payment_processor, 'N/A') as method, COUNT(*) as orders, SUM(total) as revenue")
            ->groupBy('method')
            ->orderByDesc('revenue')
            ->get();

        $total = $data->sum('orders');

        return $data->map(fn($row) => [
            'method' => ucfirst($row->method),
            'orders' => (int) $row->orders,
            'revenue' => (float) $row->revenue,
            'pct' => $total > 0 ? round(($row->orders / $total) * 100, 1) : 0,
        ])->toArray();
    }

    public function getRefundTimeline(): array
    {
        $data = MarketplaceRefundRequest::where('marketplace_client_id', $this->marketplaceId)
            ->join('marketplace_events', 'marketplace_refund_requests.marketplace_event_id', '=', 'marketplace_events.id')
            ->whereNotNull('marketplace_events.starts_at')
            ->selectRaw('DATEDIFF(marketplace_refund_requests.created_at, marketplace_events.starts_at) as days_diff, COUNT(*) as refunds')
            ->groupBy('days_diff')
            ->orderBy('days_diff')
            ->get();

        $buckets = [
            '30+ zile inainte' => 0,
            '15-30 zile inainte' => 0,
            '7-14 zile inainte' => 0,
            '1-7 zile inainte' => 0,
            'Ziua evenimentului' => 0,
            'Dupa eveniment' => 0,
        ];

        foreach ($data as $row) {
            $d = (int) $row->days_diff;
            if ($d > 0) $buckets['Dupa eveniment'] += $row->refunds;
            elseif ($d === 0) $buckets['Ziua evenimentului'] += $row->refunds;
            elseif ($d >= -7) $buckets['1-7 zile inainte'] += $row->refunds;
            elseif ($d >= -14) $buckets['7-14 zile inainte'] += $row->refunds;
            elseif ($d >= -30) $buckets['15-30 zile inainte'] += $row->refunds;
            else $buckets['30+ zile inainte'] += $row->refunds;
        }

        return ['labels' => array_keys($buckets), 'data' => array_values($buckets)];
    }

    // ==========================================
    // AI Insights
    // ==========================================

    public function generateInsights(string $tab): array
    {
        $insights = [];

        if ($tab === 'patterns') {
            $dow = $this->getDayOfWeekRevenue();
            $maxIdx = array_search(max($dow['revenue']), $dow['revenue']);
            $minIdx = array_search(min(array_filter($dow['revenue'], fn($v) => $v > 0) ?: [0]), $dow['revenue']);
            $totalRev = array_sum($dow['revenue']);
            $bestPct = $totalRev > 0 ? round(($dow['revenue'][$maxIdx] / $totalRev) * 100, 1) : 0;

            if ($maxIdx !== false) {
                $insights[] = "{$dow['labels'][$maxIdx]} este cea mai profitabila zi, generand {$bestPct}% din revenue-ul total.";
            }

            $peak = $this->getPeakSalesWindows();
            if (!empty($peak)) {
                $insights[] = "Golden window: {$peak[0]['day']} {$peak[0]['hour']} cu " . number_format($peak[0]['revenue'], 0) . " revenue.";
            }
        }

        if ($tab === 'predictions') {
            $season = $this->getSeasonalityIndex();
            $maxMonth = array_search(max($season['index']), $season['index']);
            $minMonth = array_search(min(array_filter($season['index'], fn($v) => $v > 0) ?: [0]), $season['index']);

            if ($maxMonth !== false) {
                $insights[] = "Luna de varf este {$season['labels'][$maxMonth]} (index {$season['index'][$maxMonth]}% din medie).";
            }
            if ($minMonth !== false) {
                $insights[] = "Low season: {$season['labels'][$minMonth]} (index {$season['index'][$minMonth]}% din medie).";
            }
        }

        if ($tab === 'optimization') {
            $golden = $this->getGoldenPriceZone();
            foreach (array_slice($golden, 0, 2) as $g) {
                $insights[] = "Categoria {$g['category']}: sweet spot de pret intre {$g['golden_min']}-{$g['golden_max']} RON ({$g['golden_pct']}% din vanzari).";
            }
        }

        if ($tab === 'audience') {
            $rfm = $this->getRfmSegmentation();
            if ($rfm['total'] > 0) {
                $champPct = round(($rfm['segments']['Champions'] / $rfm['total']) * 100, 1);
                $riskPct = round(($rfm['segments']['La Risc'] / $rfm['total']) * 100, 1);
                $insights[] = "{$champPct}% din clienti sunt Champions - cei mai valorosi cumparatori.";
                if ($riskPct > 15) {
                    $insights[] = "Atentie: {$riskPct}% din clienti sunt La Risc - consider campanii de re-engagement.";
                }
            }
        }

        if ($tab === 'operational') {
            $capacity = $this->getCapacityUtilization();
            $lowUtil = collect($capacity)->filter(fn($c) => $c['utilization'] < 50 && $c['events'] >= 2);
            if ($lowUtil->isNotEmpty()) {
                $insights[] = "Categorii cu utilizare sub 50%: " . $lowUtil->pluck('category')->join(', ') . ". Oportunitate de optimizare pret/marketing.";
            }
        }

        return $insights;
    }
}
