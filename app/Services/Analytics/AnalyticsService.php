<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsWidget;
use App\Models\AnalyticsReport;
use App\Models\AnalyticsMetric;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Create a new dashboard
     */
    public function createDashboard(array $data): AnalyticsDashboard
    {
        return AnalyticsDashboard::create([
            'tenant_id' => $data['tenant_id'],
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'is_shared' => $data['is_shared'] ?? false,
            'layout' => $data['layout'] ?? ['columns' => 12, 'rowHeight' => 100],
            'filters' => $data['filters'] ?? [],
        ]);
    }

    /**
     * Add widget to dashboard
     */
    public function addWidget(AnalyticsDashboard $dashboard, array $data): AnalyticsWidget
    {
        return AnalyticsWidget::create([
            'dashboard_id' => $dashboard->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'data_source' => $data['data_source'],
            'config' => $data['config'] ?? [],
            'position' => $data['position'] ?? ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 2],
            'refresh_interval' => $data['refresh_interval'] ?? '5m',
        ]);
    }

    /**
     * Get widget data
     */
    public function getWidgetData(AnalyticsWidget $widget, array $filters = []): array
    {
        $tenantId = $widget->dashboard->tenant_id;
        $dateRange = $this->getDateRange($filters);

        return match ($widget->data_source) {
            'sales' => $this->getSalesData($tenantId, $dateRange, $widget->config),
            'attendance' => $this->getAttendanceData($tenantId, $dateRange, $widget->config),
            'revenue' => $this->getRevenueData($tenantId, $dateRange, $widget->config),
            'tickets' => $this->getTicketData($tenantId, $dateRange, $widget->config),
            default => [],
        };
    }

    /**
     * Track analytics event
     */
    public function trackEvent(string $tenantId, string $eventType, array $properties = [], ?int $eventId = null): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'properties' => $properties,
            'session_id' => $properties['session_id'] ?? null,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Aggregate metrics for caching
     */
    public function aggregateMetrics(string $tenantId, Carbon $date): void
    {
        // Daily sales aggregation
        $salesData = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->selectRaw('COUNT(*) as count, SUM(total) as total, AVG(total) as average')
            ->first();

        AnalyticsMetric::updateOrCreate(
            ['tenant_id' => $tenantId, 'event_id' => null, 'metric_type' => 'daily_sales', 'date' => $date, 'hour' => null],
            ['data' => ['count' => $salesData->count, 'total' => $salesData->total, 'average' => $salesData->average]]
        );

        // Daily tickets aggregation
        $ticketData = Ticket::where('tenant_id', $tenantId)
            ->whereDate('created_at', $date)
            ->selectRaw('COUNT(*) as sold, SUM(CASE WHEN status = "checked_in" THEN 1 ELSE 0 END) as checked_in')
            ->first();

        AnalyticsMetric::updateOrCreate(
            ['tenant_id' => $tenantId, 'event_id' => null, 'metric_type' => 'daily_tickets', 'date' => $date, 'hour' => null],
            ['data' => ['sold' => $ticketData->sold, 'checked_in' => $ticketData->checked_in]]
        );
    }

    /**
     * Generate report
     */
    public function generateReport(AnalyticsReport $report): array
    {
        $config = $report->config;
        $dateRange = $this->getDateRange($config);

        $data = match ($report->type) {
            'sales' => $this->getSalesReport($report->tenant_id, $dateRange, $config),
            'attendance' => $this->getAttendanceReport($report->tenant_id, $dateRange, $config),
            'financial' => $this->getFinancialReport($report->tenant_id, $dateRange, $config),
            default => $this->getCustomReport($report->tenant_id, $dateRange, $config),
        };

        $report->update(['last_generated_at' => now()]);

        return ['success' => true, 'data' => $data, 'generated_at' => now()];
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(string $tenantId, ?int $eventId = null): array
    {
        $query = Order::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMinutes(30));

        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $recentSales = $query->count();
        $recentRevenue = $query->sum('total');

        $activeVisitors = AnalyticsEvent::where('tenant_id', $tenantId)
            ->where('event_type', 'page_view')
            ->where('occurred_at', '>=', now()->subMinutes(5))
            ->distinct('session_id')
            ->count();

        return [
            'active_visitors' => $activeVisitors,
            'sales_last_30min' => $recentSales,
            'revenue_last_30min' => $recentRevenue,
            'timestamp' => now(),
        ];
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(string $tenantId, ?int $eventId = null): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $todaySales = Order::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $today)
            ->sum('total');

        $yesterdaySales = Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$yesterday, $today])
            ->sum('total');

        $change = $yesterdaySales > 0
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100
            : 0;

        return [
            'today_sales' => $todaySales,
            'yesterday_sales' => $yesterdaySales,
            'change_percentage' => round($change, 2),
            'total_events' => \App\Models\Event::where('tenant_id', $tenantId)->count(),
            'upcoming_events' => \App\Models\Event::where('tenant_id', $tenantId)
                ->where('start_date', '>', now())
                ->count(),
        ];
    }

    protected function getDateRange(array $filters): array
    {
        return [
            'start' => Carbon::parse($filters['start_date'] ?? now()->subDays(30)),
            'end' => Carbon::parse($filters['end_date'] ?? now()),
        ];
    }

    protected function getSalesData(string $tenantId, array $dateRange, array $config): array
    {
        return Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getAttendanceData(string $tenantId, array $dateRange, array $config): array
    {
        return Ticket::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as sold, SUM(CASE WHEN status = "checked_in" THEN 1 ELSE 0 END) as attended')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getRevenueData(string $tenantId, array $dateRange, array $config): array
    {
        return Order::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, SUM(total - subtotal) as fees')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getTicketData(string $tenantId, array $dateRange, array $config): array
    {
        return Ticket::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('ticket_type_id, COUNT(*) as count')
            ->groupBy('ticket_type_id')
            ->get()
            ->toArray();
    }

    protected function getSalesReport(string $tenantId, array $dateRange, array $config): array
    {
        return [
            'summary' => Order::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->selectRaw('COUNT(*) as total_orders, SUM(total) as gross_revenue, AVG(total) as avg_order')
                ->first(),
            'by_date' => $this->getSalesData($tenantId, $dateRange, $config),
        ];
    }

    protected function getAttendanceReport(string $tenantId, array $dateRange, array $config): array
    {
        return [
            'summary' => Ticket::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->selectRaw('COUNT(*) as total_tickets, SUM(CASE WHEN status = "checked_in" THEN 1 ELSE 0 END) as checked_in')
                ->first(),
            'by_date' => $this->getAttendanceData($tenantId, $dateRange, $config),
        ];
    }

    protected function getFinancialReport(string $tenantId, array $dateRange, array $config): array
    {
        return [
            'revenue' => $this->getRevenueData($tenantId, $dateRange, $config),
            'refunds' => Order::where('tenant_id', $tenantId)
                ->where('status', 'refunded')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('total'),
        ];
    }

    protected function getCustomReport(string $tenantId, array $dateRange, array $config): array
    {
        return ['message' => 'Custom report generation'];
    }
}
