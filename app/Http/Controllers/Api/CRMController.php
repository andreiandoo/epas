<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerSegment;
use App\Models\EmailCampaign;
use App\Models\AutomationWorkflow;
use App\Services\CRM\CRMService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CRMController extends Controller
{
    public function __construct(protected CRMService $service) {}

    /**
     * Create segment
     * POST /api/crm/segments
     */
    public function createSegment(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'conditions' => 'required|array',
        ]);

        $segment = $this->service->createSegment($request->all());
        return response()->json(['success' => true, 'segment' => $segment], 201);
    }

    /**
     * List segments
     * GET /api/crm/segments
     */
    public function segments(Request $request): JsonResponse
    {
        $segments = CustomerSegment::forTenant($request->tenant_id)->get();
        return response()->json(['success' => true, 'segments' => $segments]);
    }

    /**
     * Recalculate segment
     * POST /api/crm/segments/{id}/calculate
     */
    public function calculateSegment(int $id): JsonResponse
    {
        $segment = CustomerSegment::findOrFail($id);
        $count = $this->service->calculateSegmentMembers($segment);

        return response()->json(['success' => true, 'member_count' => $count]);
    }

    /**
     * Create campaign
     * POST /api/crm/campaigns
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $campaign = $this->service->createCampaign($request->all());
        return response()->json(['success' => true, 'campaign' => $campaign], 201);
    }

    /**
     * List campaigns
     * GET /api/crm/campaigns
     */
    public function campaigns(Request $request): JsonResponse
    {
        $campaigns = EmailCampaign::forTenant($request->tenant_id)
            ->with('segment')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'campaigns' => $campaigns]);
    }

    /**
     * Schedule campaign
     * POST /api/crm/campaigns/{id}/schedule
     */
    public function scheduleCampaign(Request $request, int $id): JsonResponse
    {
        $request->validate(['scheduled_at' => 'required|date|after:now']);

        $campaign = EmailCampaign::findOrFail($id);
        $result = $this->service->scheduleCampaign($campaign, new \DateTime($request->scheduled_at));

        return response()->json(['success' => true, 'campaign' => $result]);
    }

    /**
     * Send campaign now
     * POST /api/crm/campaigns/{id}/send
     */
    public function sendCampaign(int $id): JsonResponse
    {
        $campaign = EmailCampaign::findOrFail($id);
        $result = $this->service->sendCampaign($campaign);

        return response()->json($result);
    }

    /**
     * Get campaign stats
     * GET /api/crm/campaigns/stats
     */
    public function campaignStats(Request $request): JsonResponse
    {
        $stats = $this->service->getCampaignStats($request->tenant_id);
        return response()->json(['success' => true, 'stats' => $stats]);
    }

    /**
     * Create workflow
     * POST /api/crm/workflows
     */
    public function createWorkflow(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:purchase,signup,event_day,custom',
        ]);

        $workflow = $this->service->createWorkflow($request->all());
        return response()->json(['success' => true, 'workflow' => $workflow], 201);
    }

    /**
     * List workflows
     * GET /api/crm/workflows
     */
    public function workflows(Request $request): JsonResponse
    {
        $workflows = AutomationWorkflow::forTenant($request->tenant_id)
            ->withCount(['steps', 'enrollments'])
            ->get();

        return response()->json(['success' => true, 'workflows' => $workflows]);
    }

    /**
     * Add workflow step
     * POST /api/crm/workflows/{id}/steps
     */
    public function addStep(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:email,wait,condition,action',
            'config' => 'required|array',
        ]);

        $workflow = AutomationWorkflow::findOrFail($id);
        $this->service->addWorkflowStep($workflow, $request->all());

        return response()->json(['success' => true, 'workflow' => $workflow->fresh('steps')]);
    }

    /**
     * Activate/deactivate workflow
     * POST /api/crm/workflows/{id}/toggle
     */
    public function toggleWorkflow(int $id): JsonResponse
    {
        $workflow = AutomationWorkflow::findOrFail($id);
        $workflow->update(['is_active' => !$workflow->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $workflow->is_active,
        ]);
    }

    /**
     * Log activity
     * POST /api/crm/activities
     */
    public function logActivity(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:note,email,call,meeting,purchase',
        ]);

        $activity = $this->service->logActivity($request->all());
        return response()->json(['success' => true, 'activity' => $activity], 201);
    }

    /**
     * Get customer timeline
     * GET /api/crm/customers/{id}/timeline
     */
    public function timeline(int $customerId): JsonResponse
    {
        $timeline = $this->service->getCustomerTimeline($customerId);
        return response()->json(['success' => true, 'timeline' => $timeline]);
    }

    /**
     * Track open (webhook)
     * GET /api/crm/track/open/{recipientId}
     */
    public function trackOpen(int $recipientId): JsonResponse
    {
        $this->service->trackOpen($recipientId);
        return response()->json(['success' => true]);
    }

    /**
     * Track click (webhook)
     * GET /api/crm/track/click/{recipientId}
     */
    public function trackClick(int $recipientId): JsonResponse
    {
        $this->service->trackClick($recipientId);
        return response()->json(['success' => true]);
    }
}
