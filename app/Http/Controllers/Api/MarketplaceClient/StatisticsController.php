<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends BaseController
{
    /**
     * Get dashboard statistics
     */
    public function dashboard(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $fromDate = $request->input('from_date', now()->subDays(30)->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $orders = Order::where('marketplace_client_id', $client->id)
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59']);

        $completedOrders = (clone $orders)->where('status', 'completed');

        return $this->success([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'summary' => [
                'total_orders' => $orders->count(),
                'completed_orders' => $completedOrders->count(),
                'total_revenue' => (float) $completedOrders->sum('total'),
                'total_commission' => (float) $completedOrders->sum('commission_amount'),
                'net_revenue' => (float) $completedOrders->sum('subtotal'),
                'average_order_value' => (float) $completedOrders->avg('total') ?? 0,
                'total_tickets_sold' => $completedOrders->withCount('tickets')->get()->sum('tickets_count'),
            ],
            'by_status' => Order::where('marketplace_client_id', $client->id)
                ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
                ->selectRaw('status, COUNT(*) as count, SUM(total) as total')
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->toArray(),
        ]);
    }

    /**
     * Get sales over time
     */
    public function salesTimeline(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $fromDate = $request->input('from_date', now()->subDays(30)->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());
        $groupBy = $request->input('group_by', 'day'); // day, week, month

        $dateFormat = match ($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $sales = Order::where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('SUM(commission_amount) as commission')
            ->selectRaw('SUM(subtotal) as net_revenue')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->success([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'group_by' => $groupBy,
            ],
            'timeline' => $sales,
        ]);
    }

    /**
     * Get sales by event
     */
    public function salesByEvent(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $fromDate = $request->input('from_date', now()->subDays(30)->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $sales = Order::where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->with('event:id,title,slug,start_date')
            ->selectRaw('event_id')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('SUM(commission_amount) as commission')
            ->groupBy('event_id')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get()
            ->map(function ($item) {
                return [
                    'event' => [
                        'id' => $item->event->id,
                        'title' => $item->event->title,
                        'slug' => $item->event->slug,
                        'date' => $item->event->start_date?->toIso8601String(),
                    ],
                    'orders' => $item->orders,
                    'revenue' => (float) $item->revenue,
                    'commission' => (float) $item->commission,
                ];
            });

        return $this->success([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'by_event' => $sales,
        ]);
    }

    /**
     * Get sales by tenant
     */
    public function salesByTenant(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $fromDate = $request->input('from_date', now()->subDays(30)->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $sales = Order::where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->with('tenant:id,name,slug')
            ->selectRaw('tenant_id')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('SUM(commission_amount) as commission')
            ->groupBy('tenant_id')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($item) {
                return [
                    'tenant' => [
                        'id' => $item->tenant->id,
                        'name' => $item->tenant->name,
                        'slug' => $item->tenant->slug,
                    ],
                    'orders' => $item->orders,
                    'revenue' => (float) $item->revenue,
                    'commission' => (float) $item->commission,
                ];
            });

        return $this->success([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'by_tenant' => $sales,
        ]);
    }

    /**
     * Get commission report for billing
     */
    public function commissionReport(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $month = $request->input('month', now()->format('Y-m'));

        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $orders = Order::where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->with(['event:id,title', 'tenant:id,name'])
            ->orderBy('paid_at')
            ->get();

        $summary = [
            'month' => $month,
            'total_orders' => $orders->count(),
            'gross_sales' => (float) $orders->sum('total'),
            'net_sales' => (float) $orders->sum('subtotal'),
            'total_commission' => (float) $orders->sum('commission_amount'),
            'average_commission_rate' => $orders->count() > 0
                ? round($orders->avg('commission_rate'), 2)
                : 0,
        ];

        $byTenant = $orders->groupBy('tenant_id')->map(function ($tenantOrders, $tenantId) {
            $tenant = $tenantOrders->first()->tenant;
            return [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->name,
                'orders' => $tenantOrders->count(),
                'gross_sales' => (float) $tenantOrders->sum('total'),
                'net_sales' => (float) $tenantOrders->sum('subtotal'),
                'commission' => (float) $tenantOrders->sum('commission_amount'),
            ];
        })->values();

        return $this->success([
            'report' => [
                'summary' => $summary,
                'by_tenant' => $byTenant,
                'generated_at' => now()->toIso8601String(),
            ],
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'event' => $order->event->title,
                    'tenant' => $order->tenant->name,
                    'total' => (float) $order->total,
                    'subtotal' => (float) $order->subtotal,
                    'commission_rate' => (float) $order->commission_rate,
                    'commission_amount' => (float) $order->commission_amount,
                    'paid_at' => $order->paid_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Export commission report as CSV
     */
    public function exportCommissionReport(Request $request)
    {
        $client = $this->requireClient($request);

        $month = $request->input('month', now()->format('Y-m'));

        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $orders = Order::where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->with(['event:id,title', 'tenant:id,name'])
            ->orderBy('paid_at')
            ->get();

        $filename = "commission-report-{$client->slug}-{$month}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Order Number',
                'Event',
                'Tenant',
                'Total',
                'Subtotal',
                'Commission Rate (%)',
                'Commission Amount',
                'Paid At',
            ]);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->event->title,
                    $order->tenant->name,
                    $order->total,
                    $order->subtotal,
                    $order->commission_rate,
                    $order->commission_amount,
                    $order->paid_at->format('Y-m-d H:i:s'),
                ]);
            }

            // Summary row
            fputcsv($file, []);
            fputcsv($file, ['TOTALS', '', '', $orders->sum('total'), $orders->sum('subtotal'), '', $orders->sum('commission_amount'), '']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
