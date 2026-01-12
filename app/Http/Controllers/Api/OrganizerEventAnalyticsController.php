<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMilestone;
use App\Models\Order;
use App\Services\Analytics\EventAnalyticsService;
use App\Services\Analytics\MilestoneAttributionService;
use App\Services\Analytics\BuyerJourneyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

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

    /* Helper methods */

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
