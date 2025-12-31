<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TrackingIntegration;
use App\Services\Tracking\ConsentServiceInterface;
use App\Services\Tracking\TrackingScriptInjector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller
{
    public function __construct(
        private ConsentServiceInterface $consentService,
        private TrackingScriptInjector $injector
    ) {}

    /**
     * Get tracking configuration for a tenant
     *
     * GET /api/tracking/config?tenant={id}
     */
    public function getConfig(Request $request)
    {
        $tenantId = $request->query('tenant');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID is required'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $integrations = TrackingIntegration::where('tenant_id', $tenant->id)
            ->where('enabled', true)
            ->get();

        $config = [];

        foreach ($integrations as $integration) {
            // Check consent
            if (!$this->consentService->hasConsent($integration->consent_category)) {
                continue;
            }

            $config[] = [
                'provider' => $integration->provider,
                'provider_id' => $integration->getProviderId(),
                'consent_category' => $integration->consent_category,
                'page_scope' => $integration->getPageScope(),
            ];
        }

        return response()->json([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'integrations' => $config,
            'consent_status' => $this->consentService->getConsentedCategories(),
        ]);
    }

    /**
     * Update consent preferences
     *
     * POST /api/tracking/consent
     * Body: { analytics: true, marketing: false, ... }
     */
    public function updateConsent(Request $request)
    {
        $consents = $request->all();

        $allowedCategories = ['analytics', 'marketing', 'necessary', 'preferences'];

        foreach ($consents as $category => $granted) {
            if (!in_array($category, $allowedCategories)) {
                continue;
            }

            $this->consentService->setConsent($category, (bool) $granted);
        }

        return response()->json([
            'success' => true,
            'consents' => $this->consentService->getConsentedCategories(),
        ]);
    }

    /**
     * Get current consent status
     *
     * GET /api/tracking/consent
     */
    public function getConsent()
    {
        return response()->json([
            'analytics' => $this->consentService->hasConsent('analytics'),
            'marketing' => $this->consentService->hasConsent('marketing'),
            'necessary' => $this->consentService->hasConsent('necessary'),
            'preferences' => $this->consentService->hasConsent('preferences'),
            'consented_categories' => $this->consentService->getConsentedCategories(),
            'mode' => $this->consentService->getMode(),
        ]);
    }

    /**
     * Revoke all consents
     *
     * POST /api/tracking/consent/revoke
     */
    public function revokeConsent()
    {
        $this->consentService->revokeAll();

        return response()->json([
            'success' => true,
            'message' => 'All consents have been revoked',
        ]);
    }

    /**
     * Debug: Preview what scripts would be injected
     *
     * GET /api/tracking/debug/preview?tenant={id}&page_scope={scope}
     */
    public function debugPreview(Request $request)
    {
        $tenantId = $request->query('tenant');
        $pageScope = $request->query('page_scope', 'public');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant ID is required'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $preview = $this->injector->getInjectionPreview($tenant, $pageScope);

        return response()->json([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'page_scope' => $pageScope,
            'preview' => $preview,
            'current_consents' => $this->consentService->getConsentedCategories(),
        ]);
    }

    /**
     * Admin: Create or update tracking integration
     *
     * POST /api/tracking/integrations
     */
    public function storeIntegration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'provider' => 'required|in:ga4,gtm,meta,tiktok',
            'enabled' => 'boolean',
            'consent_category' => 'required|in:analytics,marketing',
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Check if integration already exists
        $integration = TrackingIntegration::updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'provider' => $data['provider'],
            ],
            [
                'enabled' => $data['enabled'] ?? false,
                'consent_category' => $data['consent_category'],
                'settings' => $data['settings'],
            ]
        );

        return response()->json([
            'success' => true,
            'integration' => $integration,
        ]);
    }
}
