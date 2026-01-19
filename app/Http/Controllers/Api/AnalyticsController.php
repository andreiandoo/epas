<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsReport;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $service) {}

    /**
     * Get dashboard summary
     * GET /api/analytics/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->service->getDashboardSummary(
            $request->tenant_id,
            $request->event_id
        );

        return response()->json(['success' => true, 'summary' => $summary]);
    }

    /**
     * Get real-time metrics
     * GET /api/analytics/realtime
     */
    public function realtime(Request $request): JsonResponse
    {
        $metrics = $this->service->getRealTimeMetrics(
            $request->tenant_id,
            $request->event_id
        );

        return response()->json(['success' => true, 'metrics' => $metrics]);
    }

    /**
     * Create dashboard
     * POST /api/analytics/dashboards
     */
    public function createDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
        ]);

        $dashboard = $this->service->createDashboard($request->all());
        return response()->json(['success' => true, 'dashboard' => $dashboard], 201);
    }

    /**
     * Get user dashboards
     * GET /api/analytics/dashboards
     */
    public function dashboards(Request $request): JsonResponse
    {
        $dashboards = AnalyticsDashboard::forTenant($request->tenant_id)
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user_id)
                  ->orWhere('is_shared', true);
            })
            ->with('widgets')
            ->get();

        return response()->json(['success' => true, 'dashboards' => $dashboards]);
    }

    /**
     * Add widget to dashboard
     * POST /api/analytics/dashboards/{id}/widgets
     */
    public function addWidget(Request $request, int $dashboardId): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:chart,metric,table,map',
            'title' => 'required|string',
            'data_source' => 'required|string',
        ]);

        $dashboard = AnalyticsDashboard::findOrFail($dashboardId);
        $widget = $this->service->addWidget($dashboard, $request->all());

        return response()->json(['success' => true, 'widget' => $widget], 201);
    }

    /**
     * Get widget data
     * GET /api/analytics/widgets/{id}/data
     */
    public function widgetData(Request $request, int $widgetId): JsonResponse
    {
        $widget = \App\Models\AnalyticsWidget::findOrFail($widgetId);
        $data = $this->service->getWidgetData($widget, $request->all());

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Track event
     * POST /api/analytics/track
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'event_type' => 'required|string',
        ]);

        $event = $this->service->trackEvent(
            $request->tenant_id,
            $request->event_type,
            $request->properties ?? [],
            $request->event_id
        );

        return response()->json(['success' => true, 'event' => $event], 201);
    }

    /**
     * Create report
     * POST /api/analytics/reports
     */
    public function createReport(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string',
            'type' => 'required|in:sales,attendance,financial,custom',
        ]);

        $report = AnalyticsReport::create($request->all());
        return response()->json(['success' => true, 'report' => $report], 201);
    }

    /**
     * Generate report
     * POST /api/analytics/reports/{id}/generate
     */
    public function generateReport(int $reportId): JsonResponse
    {
        $report = AnalyticsReport::findOrFail($reportId);
        $result = $this->service->generateReport($report);

        return response()->json($result);
    }

    /**
     * Get sales data
     * GET /api/analytics/sales
     */
    public function sales(Request $request): JsonResponse
    {
        $widget = new \App\Models\AnalyticsWidget([
            'data_source' => 'sales',
            'config' => $request->all(),
        ]);
        $widget->setRelation('dashboard', new AnalyticsDashboard(['tenant_id' => $request->tenant_id]));

        $data = $this->service->getWidgetData($widget, $request->all());
        return response()->json(['success' => true, 'data' => $data]);
    }
}
