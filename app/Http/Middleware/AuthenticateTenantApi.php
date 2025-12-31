<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate Tenant API Middleware
 *
 * Validates tenant API keys for microservices API endpoints with:
 * - API key validation
 * - Rate limiting
 * - Usage tracking
 * - IP whitelisting (optional)
 */
class AuthenticateTenantApi
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header or query parameter
        $apiKey = $request->header('X-API-Key')
            ?? $request->header('Authorization')
            ?? $request->query('api_key');

        // Strip "Bearer " prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        if (!$apiKey) {
            return response()->json([
                'error' => 'API key required',
                'message' => 'Please provide an API key via X-API-Key header or api_key parameter',
            ], 401);
        }

        // Validate API key
        $tenantApiKey = DB::table('tenant_api_keys')
            ->where('api_key', hash('sha256', $apiKey))
            ->first();

        if (!$tenantApiKey) {
            Log::warning('Invalid API key attempt', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], 401);
        }

        // Check if API key is active
        if ($tenantApiKey->status !== 'active') {
            return response()->json([
                'error' => 'API key inactive',
                'message' => 'This API key has been disabled',
            ], 403);
        }

        // Check expiration
        if ($tenantApiKey->expires_at && now()->isAfter($tenantApiKey->expires_at)) {
            return response()->json([
                'error' => 'API key expired',
                'message' => 'This API key has expired',
            ], 403);
        }

        // Check IP whitelist if configured
        if ($tenantApiKey->allowed_ips) {
            $allowedIps = json_decode($tenantApiKey->allowed_ips, true) ?? [];
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                Log::warning('API key used from unauthorized IP', [
                    'tenant_id' => $tenantApiKey->tenant_id,
                    'ip' => $request->ip(),
                    'allowed_ips' => $allowedIps,
                ]);

                return response()->json([
                    'error' => 'IP not allowed',
                    'message' => 'Your IP address is not authorized to use this API key',
                ], 403);
            }
        }

        // Check scope/permissions
        $requiredScope = $this->getRequiredScope($request);
        if ($requiredScope && !$this->hasScope($tenantApiKey, $requiredScope)) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => "This API key does not have permission to access this endpoint (requires: {$requiredScope})",
            ], 403);
        }

        // Rate limiting
        $rateLimit = $tenantApiKey->rate_limit ?? 1000; // requests per hour
        $rateLimitKey = "api_rate_limit:{$tenantApiKey->id}";
        $requests = cache()->get($rateLimitKey, 0);

        if ($requests >= $rateLimit) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "You have exceeded the rate limit of {$rateLimit} requests per hour",
                'retry_after' => 3600, // seconds
            ], 429);
        }

        // Increment rate limit counter
        cache()->put($rateLimitKey, $requests + 1, now()->addHour());

        // Update usage statistics
        $this->trackUsage($tenantApiKey->id, $request);

        // Attach tenant and API key info to request
        $request->attributes->add([
            'tenant_id' => $tenantApiKey->tenant_id,
            'api_key_id' => $tenantApiKey->id,
            'api_key_name' => $tenantApiKey->name,
            'api_scopes' => json_decode($tenantApiKey->scopes, true) ?? [],
        ]);

        return $next($request);
    }

    /**
     * Get required scope for the current request
     *
     * @param Request $request
     * @return string|null
     */
    protected function getRequiredScope(Request $request): ?string
    {
        $path = $request->path();

        // Map routes to required scopes
        $scopeMap = [
            'api/microservices/whatsapp' => 'whatsapp:send',
            'api/microservices/efactura' => 'efactura:submit',
            'api/microservices/accounting' => 'accounting:manage',
            'api/microservices/insurance' => 'insurance:quote',
            'api/webhooks' => 'webhooks:manage',
            'api/metrics' => 'metrics:read',
        ];

        foreach ($scopeMap as $pathPrefix => $scope) {
            if (str_starts_with($path, $pathPrefix)) {
                return $scope;
            }
        }

        return null;
    }

    /**
     * Check if API key has required scope
     *
     * @param object $tenantApiKey
     * @param string $requiredScope
     * @return bool
     */
    protected function hasScope(object $tenantApiKey, string $requiredScope): bool
    {
        $scopes = json_decode($tenantApiKey->scopes, true) ?? [];

        // Check for wildcard scope
        if (in_array('*', $scopes)) {
            return true;
        }

        // Check for exact scope match
        if (in_array($requiredScope, $scopes)) {
            return true;
        }

        // Check for wildcard within namespace (e.g., "whatsapp:*" matches "whatsapp:send")
        $namespace = explode(':', $requiredScope)[0] ?? '';
        if (in_array("{$namespace}:*", $scopes)) {
            return true;
        }

        return false;
    }

    /**
     * Track API key usage
     *
     * @param string $apiKeyId
     * @param Request $request
     * @return void
     */
    protected function trackUsage(string $apiKeyId, Request $request): void
    {
        // Update last used timestamp
        DB::table('tenant_api_keys')
            ->where('id', $apiKeyId)
            ->update([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
                'total_requests' => DB::raw('total_requests + 1'),
            ]);

        // Optionally log detailed usage to separate table
        if (config('microservices.api.track_detailed_usage', false)) {
            DB::table('tenant_api_usage')->insert([
                'api_key_id' => $apiKeyId,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }
    }
}
