<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SeatingSessionMiddleware
 *
 * Manages session UID for seat hold operations across stateless API requests
 */
class SeatingSessionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUid = $this->resolveSessionUid($request);

        // Store in request attributes for access in controllers
        $request->attributes->set('seating_session_uid', $sessionUid);

        $response = $next($request);

        // Set session cookie if not present
        if (!$request->hasCookie($this->getCookieName()) && $response instanceof \Illuminate\Http\Response) {
            $response->cookie(
                $this->getCookieName(),
                $sessionUid,
                config('seating.session.cookie_lifetime_minutes', 20),
                '/',
                null,
                true, // Secure
                true, // HttpOnly
                false,
                'lax'
            );
        }

        // Add session info to response headers
        $response->headers->set('X-Seating-Session', $sessionUid);

        return $response;
    }

    /**
     * Resolve session UID from various sources
     */
    private function resolveSessionUid(Request $request): string
    {
        // Priority 1: Header (for API clients)
        $headerName = config('seating.session.header_name', 'X-Session-Id');
        if ($request->hasHeader($headerName)) {
            return $request->header($headerName);
        }

        // Priority 2: Cookie (for web clients)
        $cookieName = $this->getCookieName();
        if ($request->hasCookie($cookieName)) {
            return $request->cookie($cookieName);
        }

        // Priority 3: Laravel session (for authenticated users)
        if ($request->hasSession()) {
            $key = 'seating_session_uid';
            if ($request->session()->has($key)) {
                return $request->session()->get($key);
            }

            // Generate and store in session
            $uid = $this->generateSessionUid();
            $request->session()->put($key, $uid);
            return $uid;
        }

        // Priority 4: Generate new (stateless mode)
        return $this->generateSessionUid();
    }

    /**
     * Generate a unique session UID
     */
    private function generateSessionUid(): string
    {
        $length = config('seating.session.session_id_length', 32);
        return Str::random($length);
    }

    /**
     * Get cookie name from config
     */
    private function getCookieName(): string
    {
        return config('seating.session.cookie_name', 'epas_seating_session');
    }
}
