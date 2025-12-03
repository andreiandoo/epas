<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudienceCampaign;
use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\CustomerProfile;
use App\Models\Event;
use App\Models\EventRecommendation;
use App\Models\Tenant;
use App\Services\AudienceTargeting\AudienceExportService;
use App\Services\AudienceTargeting\CampaignOrchestrationService;
use App\Services\AudienceTargeting\CustomerProfileService;
use App\Services\AudienceTargeting\EventMatchingService;
use App\Services\AudienceTargeting\SegmentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AudienceTargetingController extends Controller
{
    public function __construct(
        protected CustomerProfileService $profileService,
        protected SegmentationService $segmentationService,
        protected EventMatchingService $eventMatchingService,
        protected AudienceExportService $exportService,
        protected CampaignOrchestrationService $campaignService
    ) {}

    // ========== PROFILES ==========

    /**
     * List customer profiles
     */
    public function listProfiles(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $query = CustomerProfile::forTenant($tenantId)
            ->with('customer:id,email,first_name,last_name');

        // Apply filters
        if ($request->has('min_engagement')) {
            $query->highEngagement($request->input('min_engagement'));
        }

        if ($request->has('min_spent')) {
            $query->highValue($request->input('min_spent') * 100);
        }

        if ($request->has('recent_days')) {
            $query->recentPurchasers($request->input('recent_days'));
        }

        $profiles = $query->paginate($request->input('per_page', 20));

        return response()->json($profiles);
    }

    /**
     * Get a single customer profile
     */
    public function getProfile(Request $request, int $customerId): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $profile = CustomerProfile::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->with('customer')
            ->first();

        if (!$profile) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        return response()->json($profile);
    }

    /**
     * Rebuild profiles for a tenant
     */
    public function rebuildProfiles(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $tenant = Tenant::findOrFail($tenantId);
        $count = $this->profileService->rebuildAllProfiles($tenant);

        return response()->json([
            'success' => true,
            'profiles_rebuilt' => $count,
        ]);
    }

    // ========== SEGMENTS ==========

    /**
     * List segments
     */
    public function listSegments(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $segments = AudienceSegment::forTenant($tenantId)
            ->withCount('customers')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($segments);
    }

    /**
     * Get a segment
     */
    public function getSegment(int $id): JsonResponse
    {
        $segment = AudienceSegment::with(['customers.customer:id,email,first_name,last_name'])
            ->findOrFail($id);

        return response()->json($segment);
    }

    /**
     * Create a segment
     */
    public function createSegment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'segment_type' => 'required|in:dynamic,static,lookalike',
            'criteria' => 'nullable|array',
            'criteria.match' => 'nullable|in:all,any',
            'criteria.rules' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = Tenant::findOrFail($request->input('tenant_id'));

        $segment = $this->segmentationService->createSegment(
            $tenant,
            $request->input('name'),
            $request->input('segment_type', 'dynamic'),
            $request->input('criteria'),
            $request->input('description')
        );

        return response()->json($segment, 201);
    }

    /**
     * Preview segment criteria
     */
    public function previewSegment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'criteria' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = Tenant::findOrFail($request->input('tenant_id'));
        $matches = $this->segmentationService->previewSegment($tenant, $request->input('criteria'));

        return response()->json([
            'count' => $matches->count(),
            'customers' => $matches->take(20)->map(fn ($m) => [
                'customer_id' => $m['customer_id'],
                'score' => $m['score'],
                'email' => $m['profile']->customer?->email,
            ]),
        ]);
    }

    /**
     * Refresh a segment
     */
    public function refreshSegment(int $id): JsonResponse
    {
        $segment = AudienceSegment::findOrFail($id);
        $count = $this->segmentationService->refreshSegment($segment);

        return response()->json([
            'success' => true,
            'customer_count' => $count,
        ]);
    }

    /**
     * Get available criteria fields
     */
    public function getCriteriaFields(): JsonResponse
    {
        return response()->json($this->segmentationService->getAvailableCriteriaFields());
    }

    // ========== EVENT TARGETING ==========

    /**
     * Get best customers for an event
     */
    public function getEventRecommendations(Request $request, int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);

        $limit = $request->input('limit', 100);
        $minScore = $request->input('min_score', 50);

        $recommendations = $this->eventMatchingService->findBestCustomersForEvent(
            $event,
            $limit,
            $minScore
        );

        return response()->json([
            'event_id' => $eventId,
            'count' => $recommendations->count(),
            'customers' => $recommendations->map(fn ($r) => [
                'customer_id' => $r['customer_id'],
                'email' => $r['customer']?->email,
                'name' => $r['customer']?->full_name,
                'score' => $r['score'],
                'reasons' => $r['reasons'],
            ]),
        ]);
    }

    /**
     * Create a segment from event recommendations
     */
    public function createEventSegment(Request $request, int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);

        $minScore = $request->input('min_score', 60);
        $name = $request->input('name');

        $segment = $this->eventMatchingService->createEventTargetSegment($event, $minScore, $name);

        return response()->json($segment, 201);
    }

    /**
     * Generate recommendations for an event
     */
    public function generateRecommendations(Request $request, int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);

        $limit = $request->input('limit', 500);
        $minScore = $request->input('min_score', 50);

        $count = $this->eventMatchingService->generateRecommendations($event, $limit, $minScore);

        return response()->json([
            'success' => true,
            'recommendations_created' => $count,
        ]);
    }

    /**
     * Get recommendation stats for an event
     */
    public function getEventRecommendationStats(int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        $stats = $this->eventMatchingService->getEventRecommendationStats($event);

        return response()->json($stats);
    }

    /**
     * Track recommendation click
     */
    public function trackRecommendationClick(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => 'required|exists:events,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->eventMatchingService->trackClick(
            $request->input('event_id'),
            $request->input('customer_id')
        );

        return response()->json(['success' => true]);
    }

    // ========== EXPORTS ==========

    /**
     * Export segment to Meta
     */
    public function exportToMeta(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'segment_id' => 'required|exists:audience_segments,id',
            'audience_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $segment = AudienceSegment::findOrFail($request->input('segment_id'));

        try {
            $export = $this->exportService->exportToMeta(
                $segment,
                $request->input('audience_name'),
                $request->input('description')
            );

            return response()->json($export, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export segment to Google Ads
     */
    public function exportToGoogle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'segment_id' => 'required|exists:audience_segments,id',
            'audience_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $segment = AudienceSegment::findOrFail($request->input('segment_id'));

        try {
            $export = $this->exportService->exportToGoogle(
                $segment,
                $request->input('audience_name'),
                $request->input('description')
            );

            return response()->json($export, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export segment to TikTok
     */
    public function exportToTikTok(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'segment_id' => 'required|exists:audience_segments,id',
            'audience_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $segment = AudienceSegment::findOrFail($request->input('segment_id'));

        try {
            $export = $this->exportService->exportToTikTok(
                $segment,
                $request->input('audience_name'),
                $request->input('description')
            );

            return response()->json($export, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export segment to Brevo (email list)
     */
    public function exportToBrevo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'segment_id' => 'required|exists:audience_segments,id',
            'list_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $segment = AudienceSegment::findOrFail($request->input('segment_id'));

        try {
            $export = $this->exportService->exportToBrevo(
                $segment,
                $request->input('list_name')
            );

            return response()->json($export, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get available export platforms
     */
    public function getExportPlatforms(): JsonResponse
    {
        return response()->json($this->exportService->getAvailableProviders());
    }

    /**
     * List exports for a tenant
     */
    public function listExports(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $exports = AudienceExport::forTenant($tenantId)
            ->with('segment:id,name')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($exports);
    }

    // ========== CAMPAIGNS ==========

    /**
     * Create a campaign
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'segment_id' => 'required|exists:audience_segments,id',
            'event_id' => 'nullable|exists:events,id',
            'name' => 'required|string|max:255',
            'campaign_type' => 'required|in:email,meta_ads,google_ads,tiktok_ads,multi_channel',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = Tenant::findOrFail($request->input('tenant_id'));
        $segment = AudienceSegment::findOrFail($request->input('segment_id'));
        $event = $request->input('event_id') ? Event::find($request->input('event_id')) : null;

        $campaign = $this->campaignService->createCampaign(
            $tenant,
            $request->input('name'),
            $request->input('campaign_type'),
            $segment,
            $event,
            $request->input('settings', [])
        );

        return response()->json($campaign, 201);
    }

    /**
     * Get a campaign
     */
    public function getCampaign(int $id): JsonResponse
    {
        $campaign = AudienceCampaign::with(['segment', 'event', 'exports'])
            ->findOrFail($id);

        return response()->json($campaign);
    }

    /**
     * List campaigns
     */
    public function listCampaigns(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $tenant = Tenant::findOrFail($tenantId);
        $campaigns = $this->campaignService->getTenantCampaigns(
            $tenant,
            $request->input('status')
        );

        return response()->json($campaigns);
    }

    /**
     * Launch a campaign
     */
    public function launchCampaign(int $id): JsonResponse
    {
        $campaign = AudienceCampaign::findOrFail($id);

        try {
            $campaign = $this->campaignService->launchCampaign($campaign);
            return response()->json($campaign);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get campaign stats
     */
    public function getCampaignStats(int $id): JsonResponse
    {
        $campaign = AudienceCampaign::findOrFail($id);
        $stats = $this->campaignService->getCampaignStats($campaign);

        return response()->json($stats);
    }

    /**
     * Get dashboard summary
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id is required'], 400);
        }

        $tenant = Tenant::findOrFail($tenantId);

        return response()->json([
            'campaigns' => $this->campaignService->getDashboardSummary($tenant),
            'exports' => $this->exportService->getExportStats($tenant),
        ]);
    }
}
