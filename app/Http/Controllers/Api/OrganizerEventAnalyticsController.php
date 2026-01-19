<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMilestone;
use App\Models\EventGoal;
use App\Models\EventReportSchedule;
use App\Models\Order;
use App\Services\Analytics\EventAnalyticsService;
use App\Services\Analytics\MilestoneAttributionService;
use App\Services\Analytics\BuyerJourneyService;
use App\Services\Analytics\EventExportService;
use App\Services\Analytics\ScheduledReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OrganizerEventAnalyticsController extends Controller
{
    public function __construct(
        protected EventAnalyticsService $analyticsService,
        protected MilestoneAttributionService $attributionService,
        protected BuyerJourneyService $journeyService,
    ) {}

    /**
     * Get complete analytics dashboard data for an event
     *
     * GET /api/organizer/events/{event}/analytics
     */
    public function dashboard(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $data = $this->analyticsService->getDashboardData($event, $period);

        return response()->json([
            'success' => true,
            'data' => $data,
            'event' => [
                'id' => $event->id,
                'name' => $event->title,
                'date' => $event->start_date?->format('d M Y'),
                'venue' => $event->venue?->name,
                'is_past' => $event->isPast(),
            ],
        ]);
    }

    /**
     * Get overview stats only
     *
     * GET /api/organizer/events/{event}/analytics/overview
     */
    public function overview(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getOverviewStats($event, $dateRange),
        ]);
    }

    /**
     * Get chart data for performance overview
     *
     * GET /api/organizer/events/{event}/analytics/chart
     */
    public function chartData(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getChartData($event, $dateRange),
        ]);
    }

    /**
     * Get real-time / live metrics
     *
     * GET /api/organizer/events/{event}/analytics/realtime
     */
    public function realtime(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getLiveVisitors($event),
        ]);
    }

    /**
     * Get live visitors for globe modal
     *
     * GET /api/organizer/events/{event}/analytics/globe
     */
    public function globeData(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getLiveVisitorsForGlobe($event),
        ]);
    }

    /**
     * Get ticket performance breakdown
     *
     * GET /api/organizer/events/{event}/analytics/tickets
     */
    public function ticketPerformance(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getTicketPerformance($event, $dateRange),
        ]);
    }

    /**
     * Get traffic sources breakdown
     *
     * GET /api/organizer/events/{event}/analytics/traffic
     */
    public function trafficSources(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getTrafficSources($event, $dateRange),
        ]);
    }

    /**
     * Get top locations
     *
     * GET /api/organizer/events/{event}/analytics/locations
     */
    public function topLocations(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $limit = $request->input('limit', 10);
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getTopLocations($event, $dateRange, $limit),
        ]);
    }

    /**
     * Get recent sales
     *
     * GET /api/organizer/events/{event}/analytics/sales
     */
    public function recentSales(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $limit = $request->input('limit', 20);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getRecentSales($event, $limit),
        ]);
    }

    /**
     * Get buyer journey for a specific order
     *
     * GET /api/organizer/events/{event}/analytics/journey/{order}
     */
    public function buyerJourney(Event $event, Order $order): JsonResponse
    {
        $this->authorizeEvent($event);

        // Verify order belongs to event
        if ($order->marketplace_event_id !== $event->id) {
            return response()->json(['error' => 'Order not found for this event'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->journeyService->getOrderJourney($order),
        ]);
    }

    /**
     * Get funnel metrics
     *
     * GET /api/organizer/events/{event}/analytics/funnel
     */
    public function funnel(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getFunnelMetrics($event, $dateRange),
        ]);
    }

    /* Milestone endpoints */

    /**
     * List all milestones for an event
     *
     * GET /api/organizer/events/{event}/milestones
     */
    public function milestones(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        return response()->json([
            'success' => true,
            'data' => $this->analyticsService->getMilestonesWithMetrics($event),
        ]);
    }

    /**
     * Create a new milestone
     *
     * POST /api/organizer/events/{event}/milestones
     */
    public function createMilestone(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(EventMilestone::TYPE_LABELS)),
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'targeting' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
        ]);

        $milestone = new EventMilestone($validated);
        $milestone->event_id = $event->id;
        $milestone->tenant_id = $event->tenant_id;
        $milestone->created_by = auth()->id();
        $milestone->is_active = true;

        // Auto-generate UTM parameters if not provided
        $milestone->autoGenerateUtmParameters();

        $milestone->save();

        // Calculate initial impact if not an ad campaign
        if (!$milestone->isAdCampaign()) {
            $this->attributionService->calculateMilestoneImpact($milestone);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $milestone->id,
                'type' => $milestone->type,
                'title' => $milestone->title,
                'start_date' => $milestone->start_date->format('M d, Y'),
                'icon' => $milestone->getTypeIcon(),
                'tracking_url' => $milestone->generateTrackingUrl(
                    route('marketplace.event', ['event' => $event->slug ?? $event->id])
                ),
            ],
            'message' => 'Milestone created successfully',
        ], 201);
    }

    /**
     * Update a milestone
     *
     * PUT /api/organizer/events/{event}/milestones/{milestone}
     */
    public function updateMilestone(Event $event, EventMilestone $milestone, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($milestone->event_id !== $event->id) {
            return response()->json(['error' => 'Milestone not found for this event'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'targeting' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $milestone->update($validated);

        // Recalculate metrics
        if ($milestone->isAdCampaign()) {
            $this->attributionService->updateMilestoneMetrics($milestone);
        } else {
            $this->attributionService->calculateMilestoneImpact($milestone);
        }

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Milestone updated successfully',
        ]);
    }

    /**
     * Delete a milestone
     *
     * DELETE /api/organizer/events/{event}/milestones/{milestone}
     */
    public function deleteMilestone(Event $event, EventMilestone $milestone): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($milestone->event_id !== $event->id) {
            return response()->json(['error' => 'Milestone not found for this event'], 404);
        }

        $milestone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Milestone deleted successfully',
        ]);
    }

    /**
     * Get milestone details with full metrics
     *
     * GET /api/organizer/events/{event}/milestones/{milestone}
     */
    public function milestoneDetails(Event $event, EventMilestone $milestone): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($milestone->event_id !== $event->id) {
            return response()->json(['error' => 'Milestone not found for this event'], 404);
        }

        // Refresh metrics
        if ($milestone->isAdCampaign()) {
            $this->attributionService->updateMilestoneMetrics($milestone);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $milestone->id,
                'type' => $milestone->type,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'start_date' => $milestone->start_date->format('M d, Y'),
                'end_date' => $milestone->end_date?->format('M d, Y'),
                'budget' => $milestone->budget,
                'currency' => $milestone->currency,
                'targeting' => $milestone->targeting,
                'attributed_revenue' => $milestone->attributed_revenue,
                'conversions' => $milestone->conversions,
                'cac' => $milestone->cac,
                'roi' => $milestone->roi,
                'roas' => $milestone->roas,
                'impact_metric' => $milestone->impact_metric,
                'is_active' => $milestone->is_active,
                'icon' => $milestone->getTypeIcon(),
                'color' => $milestone->getTypeColor(),
                'label' => $milestone->getTypeLabel(),
                'utm_parameters' => $milestone->getUtmParameters(),
                'metrics_updated_at' => $milestone->metrics_updated_at?->format('M d, Y H:i'),
            ],
        ]);
    }

    /**
     * Get campaign ROI comparison
     *
     * GET /api/organizer/events/{event}/analytics/campaigns
     */
    public function campaignComparison(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        return response()->json([
            'success' => true,
            'data' => [
                'campaigns' => $this->attributionService->getCampaignComparison($event),
                'totals' => [
                    'ad_spend' => $this->attributionService->getTotalAdSpend($event),
                    'attributed_revenue' => $this->attributionService->getTotalAttributedRevenue($event),
                    'blended_roi' => $this->attributionService->getBlendedROI($event),
                ],
            ],
        ]);
    }

    /**
     * Recalculate all metrics for an event
     *
     * POST /api/organizer/events/{event}/analytics/recalculate
     */
    public function recalculate(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        // Run attribution for unattributed purchases
        $attributed = $this->attributionService->attributeUnattributedPurchases($event->id);

        // Recalculate all milestone metrics
        $this->attributionService->recalculateEventMilestones($event);

        // Invalidate cache
        $this->analyticsService->invalidateCache($event->id);

        return response()->json([
            'success' => true,
            'message' => "Recalculated metrics. {$attributed} purchases attributed.",
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Export Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Export analytics to CSV
     *
     * GET /api/organizer/events/{event}/analytics/export/csv
     */
    public function exportCsv(Event $event, Request $request, EventExportService $exportService): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $sections = $request->input('sections', ['daily', 'traffic', 'milestones', 'sales']);

        $filepath = $exportService->exportToCsv($event, [
            'period' => $period,
            'sections' => $sections,
        ]);

        return response()->json([
            'success' => true,
            'download_url' => $exportService->getDownloadUrl($filepath),
            'filename' => basename($filepath),
        ]);
    }

    /**
     * Export analytics to PDF
     *
     * GET /api/organizer/events/{event}/analytics/export/pdf
     */
    public function exportPdf(Event $event, Request $request, EventExportService $exportService): JsonResponse
    {
        $this->authorizeEvent($event);

        $period = $request->input('period', '30d');
        $sections = $request->input('sections', ['overview', 'chart', 'traffic', 'milestones', 'goals']);

        $filepath = $exportService->exportToPdf($event, [
            'period' => $period,
            'sections' => $sections,
            'include_comparison' => $request->boolean('include_comparison', true),
        ]);

        return response()->json([
            'success' => true,
            'download_url' => $exportService->getDownloadUrl($filepath),
            'filename' => basename($filepath),
        ]);
    }

    /**
     * Export sales to CSV
     *
     * GET /api/organizer/events/{event}/analytics/export/sales
     */
    public function exportSales(Event $event, Request $request, EventExportService $exportService): JsonResponse
    {
        $this->authorizeEvent($event);

        $dateRange = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateRange = [
                'start' => now()->parse($request->input('start_date'))->startOfDay(),
                'end' => now()->parse($request->input('end_date'))->endOfDay(),
            ];
        }

        $filepath = $exportService->exportSalesToCsv($event, $dateRange);

        return response()->json([
            'success' => true,
            'download_url' => $exportService->getDownloadUrl($filepath),
            'filename' => basename($filepath),
        ]);
    }

    /**
     * Download export file
     *
     * GET /api/organizer/analytics/download/{filename}
     */
    public function download(string $filename)
    {
        $filepath = "exports/{$filename}";

        if (!Storage::disk('local')->exists($filepath)) {
            abort(404, 'File not found');
        }

        return response()->download(
            Storage::disk('local')->path($filepath),
            $filename,
            ['Content-Type' => $this->getContentType($filename)]
        )->deleteFileAfterSend(true);
    }

    /*
    |--------------------------------------------------------------------------
    | Goals Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Get all goals for an event
     *
     * GET /api/organizer/events/{event}/goals
     */
    public function goals(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        $goals = EventGoal::forEvent($event->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($goal) => $this->formatGoal($goal));

        return response()->json([
            'success' => true,
            'data' => $goals,
        ]);
    }

    /**
     * Create a new goal
     *
     * POST /api/organizer/events/{event}/goals
     */
    public function createGoal(Event $event, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'type' => 'required|in:revenue,tickets,visitors,conversion_rate',
            'name' => 'nullable|string|max:255',
            'target_value' => 'required|numeric|min:1',
            'deadline' => 'nullable|date|after:today',
            'alert_thresholds' => 'nullable|array',
            'alert_thresholds.*' => 'integer|min:1|max:100',
            'email_alerts' => 'boolean',
            'in_app_alerts' => 'boolean',
            'alert_email' => 'nullable|email',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Convert target value to cents for revenue type
        if ($validated['type'] === EventGoal::TYPE_REVENUE) {
            $validated['target_value'] = (int) ($validated['target_value'] * 100);
        }

        // Convert conversion rate to basis points (x100)
        if ($validated['type'] === EventGoal::TYPE_CONVERSION) {
            $validated['target_value'] = (int) ($validated['target_value'] * 100);
        }

        $goal = EventGoal::create([
            'event_id' => $event->id,
            ...$validated,
            'alert_thresholds' => $validated['alert_thresholds'] ?? EventGoal::DEFAULT_THRESHOLDS,
        ]);

        // Update progress immediately
        $goal->updateProgress();

        return response()->json([
            'success' => true,
            'data' => $this->formatGoal($goal),
            'message' => 'Goal created successfully',
        ], 201);
    }

    /**
     * Update a goal
     *
     * PUT /api/organizer/events/{event}/goals/{goal}
     */
    public function updateGoal(Event $event, EventGoal $goal, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($goal->event_id !== $event->id) {
            return response()->json(['error' => 'Goal not found for this event'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'target_value' => 'nullable|numeric|min:1',
            'deadline' => 'nullable|date',
            'alert_thresholds' => 'nullable|array',
            'email_alerts' => 'boolean',
            'in_app_alerts' => 'boolean',
            'alert_email' => 'nullable|email',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,cancelled',
        ]);

        // Convert target value if provided
        if (isset($validated['target_value'])) {
            if ($goal->type === EventGoal::TYPE_REVENUE) {
                $validated['target_value'] = (int) ($validated['target_value'] * 100);
            } elseif ($goal->type === EventGoal::TYPE_CONVERSION) {
                $validated['target_value'] = (int) ($validated['target_value'] * 100);
            }
        }

        $goal->update($validated);
        $goal->updateProgress();

        return response()->json([
            'success' => true,
            'data' => $this->formatGoal($goal),
            'message' => 'Goal updated successfully',
        ]);
    }

    /**
     * Delete a goal
     *
     * DELETE /api/organizer/events/{event}/goals/{goal}
     */
    public function deleteGoal(Event $event, EventGoal $goal): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($goal->event_id !== $event->id) {
            return response()->json(['error' => 'Goal not found for this event'], 404);
        }

        $goal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Goal deleted successfully',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Report Schedule Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * Get report schedules for an event
     *
     * GET /api/organizer/events/{event}/report-schedules
     */
    public function reportSchedules(Event $event): JsonResponse
    {
        $this->authorizeEvent($event);

        $schedules = EventReportSchedule::forEvent($event->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($schedule) => $this->formatSchedule($schedule));

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Create a report schedule
     *
     * POST /api/organizer/events/{event}/report-schedules
     */
    public function createReportSchedule(Event $event, Request $request, ScheduledReportService $reportService): JsonResponse
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'frequency' => 'required|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'send_at' => 'required|date_format:H:i',
            'timezone' => 'nullable|string|max:50',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'sections' => 'nullable|array',
            'format' => 'nullable|in:email,pdf,csv',
            'include_comparison' => 'boolean',
        ]);

        $schedule = EventReportSchedule::create([
            'event_id' => $event->id,
            'marketplace_organizer_id' => $event->marketplace_organizer_id,
            ...$validated,
            'timezone' => $validated['timezone'] ?? 'Europe/Bucharest',
            'sections' => $validated['sections'] ?? EventReportSchedule::DEFAULT_SECTIONS,
            'format' => $validated['format'] ?? EventReportSchedule::FORMAT_EMAIL,
            'is_active' => true,
        ]);

        $schedule->scheduleNext();

        return response()->json([
            'success' => true,
            'data' => $this->formatSchedule($schedule),
            'message' => 'Report schedule created successfully',
        ], 201);
    }

    /**
     * Update a report schedule
     *
     * PUT /api/organizer/events/{event}/report-schedules/{schedule}
     */
    public function updateReportSchedule(Event $event, EventReportSchedule $schedule, Request $request): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($schedule->event_id !== $event->id) {
            return response()->json(['error' => 'Schedule not found for this event'], 404);
        }

        $validated = $request->validate([
            'frequency' => 'nullable|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'send_at' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string|max:50',
            'recipients' => 'nullable|array|min:1',
            'recipients.*' => 'email',
            'sections' => 'nullable|array',
            'format' => 'nullable|in:email,pdf,csv',
            'include_comparison' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $schedule->update($validated);

        if (isset($validated['frequency']) || isset($validated['day_of_week']) || isset($validated['day_of_month']) || isset($validated['send_at'])) {
            $schedule->scheduleNext();
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSchedule($schedule),
            'message' => 'Schedule updated successfully',
        ]);
    }

    /**
     * Delete a report schedule
     *
     * DELETE /api/organizer/events/{event}/report-schedules/{schedule}
     */
    public function deleteReportSchedule(Event $event, EventReportSchedule $schedule): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($schedule->event_id !== $event->id) {
            return response()->json(['error' => 'Schedule not found for this event'], 404);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully',
        ]);
    }

    /**
     * Send a test report
     *
     * POST /api/organizer/events/{event}/report-schedules/{schedule}/test
     */
    public function sendTestReport(Event $event, EventReportSchedule $schedule, ScheduledReportService $reportService): JsonResponse
    {
        $this->authorizeEvent($event);

        if ($schedule->event_id !== $event->id) {
            return response()->json(['error' => 'Schedule not found for this event'], 404);
        }

        try {
            $reportService->sendScheduledReport($schedule);

            return response()->json([
                'success' => true,
                'message' => 'Test report sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send test report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /* Helper methods */

    protected function formatGoal(EventGoal $goal): array
    {
        return [
            'id' => $goal->id,
            'type' => $goal->type,
            'type_label' => $goal->type_label,
            'type_icon' => $goal->type_icon,
            'type_color' => $goal->type_color,
            'name' => $goal->name,
            'target_value' => $goal->type === EventGoal::TYPE_REVENUE
                ? $goal->target_value / 100
                : ($goal->type === EventGoal::TYPE_CONVERSION ? $goal->target_value / 100 : $goal->target_value),
            'current_value' => $goal->type === EventGoal::TYPE_REVENUE
                ? $goal->current_value / 100
                : ($goal->type === EventGoal::TYPE_CONVERSION ? $goal->current_value / 100 : $goal->current_value),
            'formatted_target' => $goal->formatted_target,
            'formatted_current' => $goal->formatted_current,
            'progress_percent' => $goal->progress_percent,
            'progress_status' => $goal->progress_status,
            'remaining' => $goal->remaining,
            'deadline' => $goal->deadline?->format('Y-m-d'),
            'days_remaining' => $goal->days_remaining,
            'alert_thresholds' => $goal->alert_thresholds,
            'alerts_sent' => $goal->alerts_sent,
            'email_alerts' => $goal->email_alerts,
            'in_app_alerts' => $goal->in_app_alerts,
            'status' => $goal->status,
            'is_achieved' => $goal->isAchieved(),
            'is_overdue' => $goal->isOverdue(),
            'achieved_at' => $goal->achieved_at?->format('Y-m-d H:i'),
            'notes' => $goal->notes,
        ];
    }

    protected function formatSchedule(EventReportSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'frequency' => $schedule->frequency,
            'frequency_label' => $schedule->frequency_label,
            'schedule_description' => $schedule->schedule_description,
            'day_of_week' => $schedule->day_of_week,
            'day_of_month' => $schedule->day_of_month,
            'send_at' => $schedule->send_at,
            'timezone' => $schedule->timezone,
            'recipients' => $schedule->recipients,
            'sections' => $schedule->sections,
            'format' => $schedule->format,
            'include_comparison' => $schedule->include_comparison,
            'is_active' => $schedule->is_active,
            'last_sent_at' => $schedule->last_sent_at?->format('Y-m-d H:i'),
            'next_send_at' => $schedule->next_send_at?->format('Y-m-d H:i'),
        ];
    }

    protected function getContentType(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return match ($extension) {
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    protected function authorizeEvent(Event $event): void
    {
        // Check if user has access to this event's organizer
        // This should be customized based on your auth system
        $user = auth()->user();

        if (!$user) {
            abort(401, 'Unauthorized');
        }

        // Check if user belongs to the organizer or tenant
        $organizerId = $event->marketplace_organizer_id;
        $tenantId = $event->tenant_id;

        // Add your authorization logic here
        // For now, we'll check if user has access to the tenant
        if ($user->tenant_id && $user->tenant_id !== $tenantId) {
            // Check if user is associated with the organizer
            if (!$user->marketplaceOrganizers?->contains('id', $organizerId)) {
                abort(403, 'You do not have access to this event');
            }
        }
    }

    protected function getDateRange(string $period): array
    {
        return match ($period) {
            '7d' => ['start' => now()->subDays(7)->startOfDay(), 'end' => now()->endOfDay()],
            '30d' => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
            '90d' => ['start' => now()->subDays(90)->startOfDay(), 'end' => now()->endOfDay()],
            'all' => ['start' => now()->subYear()->startOfDay(), 'end' => now()->endOfDay()],
            default => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
        };
    }
}
