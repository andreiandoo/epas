<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try session-based auth first
        if (Auth::guard('vendor')->check()) {
            return $next($request);
        }

        // Try API token auth (Bearer token)
        $token = $request->bearerToken();
        if ($token) {
            $vendor = \App\Models\Vendor::where('api_token', hash('sha256', $token))
                ->where('status', 'active')
                ->first();

            if ($vendor) {
                Auth::guard('vendor')->setUser($vendor);
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
