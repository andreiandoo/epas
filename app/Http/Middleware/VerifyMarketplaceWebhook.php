<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Marketplace\WebhookSignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify marketplace webhook signatures.
 *
 * Usage in routes:
 *   Route::post('/webhook', ...)->middleware('verify.marketplace.webhook:tenant_id');
 */
class VerifyMarketplaceWebhook
{
    public function __construct(
        protected WebhookSignatureService $signatureService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $identifierParam The request parameter containing the tenant/organizer ID
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $identifierParam = 'tenant_id'): Response
    {
        // Get identifier from route parameter or request body
        $identifier = $request->route($identifierParam) ?? $request->input($identifierParam);

        if (empty($identifier)) {
            Log::warning('Webhook request missing identifier', [
                'param' => $identifierParam,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Missing tenant identifier',
            ], 400);
        }

        // Find the tenant
        $tenant = Tenant::find($identifier);

        if (!$tenant) {
            Log::warning('Webhook request for unknown tenant', [
                'tenant_id' => $identifier,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Tenant not found',
            ], 404);
        }

        if (!$tenant->isMarketplace()) {
            Log::warning('Webhook request for non-marketplace tenant', [
                'tenant_id' => $identifier,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid tenant type',
            ], 400);
        }

        // Get webhook secret
        $secret = $this->signatureService->getOrCreateTenantSecret($tenant);

        // Validate signature
        $result = $this->signatureService->validateRequest($request, $secret);

        if (!$result['valid']) {
            Log::warning('Webhook signature validation failed', [
                'tenant_id' => $identifier,
                'error' => $result['error'] ?? 'Unknown error',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid webhook signature',
                'details' => $result['error'] ?? null,
            ], 401);
        }

        // Add tenant to request for downstream handlers
        $request->attributes->set('marketplace_tenant', $tenant);

        Log::info('Webhook signature validated', [
            'tenant_id' => $tenant->id,
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
