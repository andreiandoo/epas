<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin Authentication Middleware
 *
 * Ensures only authenticated admin users can access admin endpoints
 */
class AuthenticateAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated',
                'message' => 'You must be logged in to access this endpoint',
            ], 401);
        }

        $user = Auth::user();

        // Check if user has admin role
        // Adjust this based on your user model structure
        if (!$this->isAdmin($user)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this endpoint',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user is an admin
     *
     * @param mixed $user
     * @return bool
     */
    protected function isAdmin($user): bool
    {
        // Method 1: Check if user has 'role' column
        if (isset($user->role)) {
            return in_array($user->role, ['admin', 'super_admin']);
        }

        // Method 2: Check if user has 'is_admin' boolean
        if (isset($user->is_admin)) {
            return (bool) $user->is_admin;
        }

        // Method 3: Check email domain (fallback for development)
        if (config('app.env') === 'local' && isset($user->email)) {
            $adminDomains = config('microservices.admin.allowed_domains', []);
            if (!empty($adminDomains)) {
                $domain = substr(strrchr($user->email, "@"), 1);
                return in_array($domain, $adminDomains);
            }
        }

        // Method 4: Check if user ID is in admin list
        $adminIds = config('microservices.admin.user_ids', []);
        if (!empty($adminIds) && isset($user->id)) {
            return in_array($user->id, $adminIds);
        }

        return false;
    }
}
