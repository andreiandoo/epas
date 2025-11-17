<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugAdminAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only debug admin routes
        if (str_starts_with($request->path(), 'admin')) {
            Log::channel('single')->info('=== ADMIN ACCESS DEBUG ===', [
                'timestamp' => now(),
                'path' => $request->path(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'user_role' => auth()->user()?->role,
                'session_id' => session()->getId(),
                'has_auth_session' => session()->has('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $response = $next($request);

        // Log response status for admin routes
        if (str_starts_with($request->path(), 'admin')) {
            Log::channel('single')->info('=== ADMIN RESPONSE ===', [
                'status' => $response->getStatusCode(),
                'path' => $request->path(),
            ]);
        }

        return $response;
    }
}
