<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Stripe\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StripeConnectController extends Controller
{
    public function __construct(protected StripeConnectService $service) {}

    /**
     * Get tenant's Stripe Connect status
     * GET /api/stripe/connect/status
     */
    public function status(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        return response()->json([
            'success' => true,
            'status' => [
                'has_account' => !empty($tenant->stripe_connect_id),
                'onboarding_complete' => $tenant->stripe_onboarding_complete,
                'charges_enabled' => $tenant->stripe_charges_enabled,
                'payouts_enabled' => $tenant->stripe_payouts_enabled,
                'platform_fee_percentage' => $tenant->platform_fee_percentage,
            ],
        ]);
    }

    /**
     * Start Stripe Connect onboarding
     * POST /api/stripe/connect/onboard
     */
    public function onboard(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        $result = $this->service->createOnboardingLink($tenant);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'onboarding_url' => $result['url'],
                'expires_at' => $result['expires_at'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 400);
    }

    /**
     * Refresh account status after onboarding
     * POST /api/stripe/connect/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        $result = $this->service->refreshAccountStatus($tenant);

        return response()->json($result);
    }

    /**
     * Get Stripe dashboard link for tenant
     * GET /api/stripe/connect/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        $result = $this->service->getDashboardLink($tenant);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'dashboard_url' => $result['url'],
            ]);
        }

        return response()->json($result, 400);
    }

    /**
     * Get earnings summary
     * GET /api/stripe/connect/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        $summary = $this->service->getEarningsSummary(
            $tenant,
            $request->period ?? 'month'
        );

        return response()->json([
            'success' => true,
            'earnings' => $summary,
        ]);
    }

    /**
     * Create Terminal connection token
     * POST /api/stripe/terminal/connection-token
     */
    public function connectionToken(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->tenant_id);

        $result = $this->service->createConnectionToken($tenant);

        if ($result['success']) {
            return response()->json([
                'secret' => $result['secret'],
            ]);
        }

        return response()->json($result, 400);
    }

    /**
     * Update tenant platform fee percentage
     * PUT /api/stripe/connect/fee
     */
    public function updateFee(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'platform_fee_percentage' => 'required|numeric|min:0|max:30',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);
        $tenant->update([
            'platform_fee_percentage' => $request->platform_fee_percentage,
        ]);

        return response()->json([
            'success' => true,
            'platform_fee_percentage' => $tenant->platform_fee_percentage,
        ]);
    }

    /**
     * Handle Stripe Connect webhooks
     * POST /api/stripe/connect/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = $payload['type'] ?? null;

        switch ($event) {
            case 'account.updated':
                $accountId = $payload['data']['object']['id'];
                $tenant = Tenant::where('stripe_connect_id', $accountId)->first();
                if ($tenant) {
                    $this->service->refreshAccountStatus($tenant);
                }
                break;

            case 'payment_intent.succeeded':
                // Handle successful payment
                break;

            case 'transfer.created':
                // Handle transfer to connected account
                break;
        }

        return response()->json(['received' => true]);
    }
}
