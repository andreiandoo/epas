<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Secure Tenant Authentication Middleware
 *
 * SECURITY FIX: Properly validates tenant API credentials
 * Replaces the insecure TenantAuthentication middleware
 */
class TenantAuthenticationSecure
{
    /**
     * Maximum authentication attempts per minute per IP
     */
    protected int $maxAttempts = 10;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limit authentication attempts
        $key = 'tenant_auth:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            Log::warning('Tenant authentication rate limit exceeded', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Too many authentication attempts',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        // Get API key from header
        $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');

        if (!$apiKey) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'message' => 'Missing API key. Provide X-API-Key header.',
            ], 401);
        }

        // Strip "Bearer " prefix if present
        if (str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        // Validate API key format (basic check)
        if (strlen($apiKey) < 32 || strlen($apiKey) > 256) {
            RateLimiter::hit($key, 60);
            Log::warning('Invalid API key format', [
                'ip' => $request->ip(),
                'key_length' => strlen($apiKey),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid API key format.',
            ], 401);
        }

        // SECURITY FIX: Properly validate API key against database
        // Use hash comparison to prevent timing attacks
        $hashedKey = hash('sha256', $apiKey);

        $tenant = Tenant::where('api_key_hash', $hashedKey)
            ->where('is_active', true)
            ->first();

        // Alternative: If using plaintext keys (not recommended), use timing-safe comparison
        if (!$tenant) {
            // Try legacy plaintext comparison (should be migrated)
            $tenant = Tenant::where('is_active', true)
                ->get()
                ->first(function ($t) use ($apiKey) {
                    return $t->api_key && hash_equals($t->api_key, $apiKey);
                });
        }

        if (!$tenant) {
            RateLimiter::hit($key, 60);
            Log::warning('Invalid tenant API key attempt', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
            ], 401);
        }

        // Check if tenant is suspended
        if ($tenant->suspended_at !== null) {
            Log::info('Suspended tenant access attempt', [
                'tenant_id' => $tenant->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tenant account is suspended.',
            ], 403);
        }

        // Clear rate limit on successful auth
        RateLimiter::clear($key);

        // Attach tenant to request for downstream use
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);

        // Update last access timestamp (async to not slow down request)
        dispatch(function () use ($tenant, $request) {
            $tenant->update([
                'last_api_access_at' => now(),
                'last_api_access_ip' => $request->ip(),
            ]);
        })->afterResponse();

        // Log API request for audit purposes
        Log::info('Tenant API request authenticated', [
            'tenant_id' => $tenant->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
