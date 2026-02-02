<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Admin Authentication Middleware
 *
 * SECURITY FIX: Properly authenticates tenant admin users
 * This middleware must be applied to all admin routes under /api/tenant-client/admin
 *
 * Fixes:
 * - CRITICAL-001: Missing authentication on admin endpoints
 * - Validates Bearer token
 * - Verifies user has admin role for the tenant
 * - Rate limits authentication attempts
 */
class TenantAdminAuth
{
    /**
     * Maximum authentication attempts per minute per IP
     */
    protected int $maxAttempts = 10;

    /**
     * Admin roles that are allowed access
     */
    protected array $allowedRoles = ['admin', 'super_admin', 'owner', 'manager'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $key = 'tenant_admin_auth:' . $ip;

        // Rate limit authentication attempts
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            Log::warning('Tenant admin auth rate limit exceeded', [
                'ip' => $ip,
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Too many authentication attempts',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        // Get tenant from request (should be set by TenantClientCors or similar middleware)
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'error' => 'Tenant not found',
            ], 404);
        }

        // Get authorization header
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'error' => 'Authorization required',
                'message' => 'Please provide a valid Bearer token',
            ], 401);
        }

        $token = substr($authHeader, 7);

        // Validate token format
        if (strlen($token) < 32 || strlen($token) > 256) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'error' => 'Invalid token format',
            ], 401);
        }

        // Look up the admin session by token
        $adminSession = $this->validateAdminToken($token, $tenant);

        if (!$adminSession) {
            RateLimiter::hit($key, 60);
            Log::warning('Invalid tenant admin token attempt', [
                'ip' => $ip,
                'tenant_id' => $tenant->id,
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired token',
            ], 401);
        }

        // Verify admin has appropriate role
        if (!$this->hasAdminRole($adminSession['user'])) {
            Log::warning('Non-admin user attempted admin access', [
                'ip' => $ip,
                'tenant_id' => $tenant->id,
                'user_id' => $adminSession['user']->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Insufficient permissions',
            ], 403);
        }

        // Clear rate limit on successful auth
        RateLimiter::clear($key);

        // Attach admin user and tenant to request
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);
        $request->attributes->set('admin_user', $adminSession['user']);
        $request->attributes->set('admin_role', $adminSession['role']);

        // Log successful admin access
        Log::info('Tenant admin access granted', [
            'tenant_id' => $tenant->id,
            'admin_id' => $adminSession['user']->id ?? 'unknown',
            'role' => $adminSession['role'],
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $ip,
        ]);

        return $next($request);
    }

    /**
     * Resolve tenant from request
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Check if tenant was already resolved by previous middleware
        if ($tenant = $request->attributes->get('tenant')) {
            return $tenant;
        }

        // Resolve by hostname
        $hostname = $request->query('hostname') ?? $request->header('X-Tenant-Domain');

        if ($hostname) {
            $domain = \App\Models\Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();

            if ($domain && $domain->tenant) {
                return $domain->tenant;
            }
        }

        // SECURITY: Do NOT accept tenant_id directly from query params
        // This prevents tenant resolution bypass attacks

        return null;
    }

    /**
     * Validate admin token and return session data
     */
    protected function validateAdminToken(string $token, Tenant $tenant): ?array
    {
        // Option 1: Check against cache/session store
        $cacheKey = "tenant_admin_session:{$tenant->id}:" . hash('sha256', $token);
        $sessionData = cache()->get($cacheKey);

        if ($sessionData) {
            // Validate session hasn't expired
            if (isset($sessionData['expires_at']) && now()->gt($sessionData['expires_at'])) {
                cache()->forget($cacheKey);
                return null;
            }

            // Refresh session expiry (sliding expiration)
            $sessionData['expires_at'] = now()->addHours(2);
            cache()->put($cacheKey, $sessionData, now()->addHours(2));

            // Load fresh user data
            if (isset($sessionData['user_id'])) {
                $user = Customer::where('id', $sessionData['user_id'])
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if ($user) {
                    return [
                        'user' => $user,
                        'role' => $sessionData['role'] ?? 'admin',
                    ];
                }
            }
        }

        // Option 2: Check against database tokens (if using personal access tokens)
        // This would be for Sanctum-like token storage
        $personalToken = \DB::table('personal_access_tokens')
            ->where('token', hash('sha256', $token))
            ->where('tokenable_type', Customer::class)
            ->whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->first();

        if ($personalToken) {
            $user = Customer::where('id', $personalToken->tokenable_id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($user) {
                return [
                    'user' => $user,
                    'role' => $this->getUserRole($user, $tenant),
                ];
            }
        }

        return null;
    }

    /**
     * Check if user has admin role
     */
    protected function hasAdminRole($user): bool
    {
        if (!$user) {
            return false;
        }

        // Check role from user model
        $role = $user->role ?? $user->meta['role'] ?? null;

        if ($role && in_array($role, $this->allowedRoles)) {
            return true;
        }

        // Check if user is tenant owner
        if (method_exists($user, 'isOwner') && $user->isOwner()) {
            return true;
        }

        return false;
    }

    /**
     * Get user role for tenant
     */
    protected function getUserRole($user, Tenant $tenant): string
    {
        // Check if user is tenant owner
        if ($tenant->owner_id === $user->id) {
            return 'owner';
        }

        // Check user's role
        return $user->role ?? $user->meta['role'] ?? 'customer';
    }
}
