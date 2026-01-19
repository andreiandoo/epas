<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Authentication Middleware
 *
 * Validates tenant API credentials and attaches tenant context to the request
 */
class TenantAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Missing API key. Provide X-API-Key header.',
            ], 401);
        }

        // Strip "Bearer " prefix if present
        if (str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        // Validate API key and get tenant
        // TODO: Replace with actual tenant lookup from database
        // $tenant = DB::table('tenants')->where('api_key', $apiKey)->first();

        // For now, extract tenant_id from the request body or use a default
        // This is a temporary solution until proper tenant authentication is implemented
        $tenantId = $request->input('tenant') ?? $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing tenant ID. Provide tenant in request body or X-Tenant-ID header.',
            ], 401);
        }

        // Attach tenant to request for downstream use
        $request->merge(['tenant_id' => $tenantId]);
        $request->attributes->set('tenant_id', $tenantId);

        // Log API request for audit purposes
        \Log::info('API request', [
            'tenant_id' => $tenantId,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
