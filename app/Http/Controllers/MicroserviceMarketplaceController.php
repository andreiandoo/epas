<?php

namespace App\Http\Controllers;

use App\Models\Microservice;
use App\Models\Tenant;
use App\Services\StripeService;
use Illuminate\Http\Request;

class MicroserviceMarketplaceController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Display the microservices marketplace
     */
    public function index(Request $request)
    {
        // Get current tenant (you might need to adjust this based on your auth system)
        // For now, assuming tenant_id is in session or request
        $tenantId = $request->input('tenant_id') ?? session('tenant_id') ?? 1;
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            abort(404, 'Tenant not found');
        }

        // Get all active microservices
        $microservices = Microservice::active()->get();

        // Get tenant's currently active microservices
        $activeMicroserviceIds = $tenant->microservices()
            ->wherePivot('is_active', true)
            ->pluck('microservices.id')
            ->toArray();

        // Check if Stripe is configured
        $stripeConfigured = $this->stripeService->isConfigured();

        return view('marketplace.index', compact(
            'microservices',
            'tenant',
            'activeMicroserviceIds',
            'stripeConfigured'
        ));
    }

    /**
     * Initiate checkout process
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'microservices' => 'required|array|min:1',
            'microservices.*' => 'exists:microservices,id',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);
        $microserviceIds = $request->microservices;

        try {
            $session = $this->stripeService->createCheckoutSession(
                $tenant,
                $microserviceIds,
                route('micro.payment.success'),
                route('micro.payment.cancel')
            );

            // Store session ID in the session for later retrieval
            session(['stripe_checkout_session_id' => $session->id]);

            return redirect($session->url);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create checkout session: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment success
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect()->route('micro.marketplace')->with('error', 'Invalid session');
        }

        try {
            $session = $this->stripeService->retrieveSession($sessionId);

            // Get tenant and microservices from metadata
            $tenantId = $session->metadata->tenant_id ?? $session->client_reference_id;
            $microserviceIds = explode(',', $session->metadata->microservice_ids ?? '');

            $tenant = Tenant::find($tenantId);
            $microservices = Microservice::whereIn('id', $microserviceIds)->get();

            return view('marketplace.success', compact('session', 'tenant', 'microservices'));
        } catch (\Exception $e) {
            return redirect()->route('micro.marketplace')->with('error', 'Failed to retrieve session: ' . $e->getMessage());
        }
    }

    /**
     * Handle payment cancellation
     */
    public function cancel()
    {
        return redirect()->route('micro.marketplace')->with('warning', 'Payment was cancelled');
    }
}
