<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugCookieSession
{
    /**
     * Handle an incoming request.
     *
     * This middleware logs detailed cookie and session information
     * to help debug why sessions are not persisting between requests.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only debug admin routes
        if (!str_starts_with($request->path(), 'admin')) {
            return $next($request);
        }

        // === BEFORE REQUEST ===
        $sessionIdBefore = session()->getId();

        Log::channel('single')->info('=== COOKIE DEBUG - BEFORE REQUEST ===', [
            'timestamp' => now(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'session_id_before' => $sessionIdBefore,
            'request_cookies' => $request->cookies->all(),
            'cookie_header' => $request->header('Cookie'),
            'has_tixello_session_cookie' => $request->hasCookie('tixello-session'),
            'tixello_session_value' => $request->cookie('tixello-session'),
        ]);

        // Process the request
        $response = $next($request);

        // === AFTER REQUEST ===
        $sessionIdAfter = session()->getId();

        // Get all Set-Cookie headers from response
        $setCookieHeaders = [];
        $allResponseHeaders = [];
        $debugInfo = [];

        // Deep debug of response object
        $debugInfo['has_headers_property'] = property_exists($response, 'headers');
        $debugInfo['has_headers_method'] = method_exists($response, 'headers');

        if (method_exists($response, 'headers')) {
            $headers = $response->headers;
            $debugInfo['headers_type'] = get_class($headers);
            $debugInfo['headers_count'] = $headers->count();

            // Get ALL headers for debugging
            $allResponseHeaders = $headers->all();

            // Try to get cookies via getCookies method
            if (method_exists($headers, 'getCookies')) {
                $cookies = $headers->getCookies();
                $debugInfo['cookies_count'] = count($cookies);

                foreach ($cookies as $cookie) {
                    $setCookieHeaders[] = [
                        'name' => $cookie->getName(),
                        'value' => substr($cookie->getValue(), 0, 50) . '...', // Truncate for readability
                        'domain' => $cookie->getDomain(),
                        'path' => $cookie->getPath(),
                        'secure' => $cookie->isSecure(),
                        'httponly' => $cookie->isHttpOnly(),
                        'samesite' => $cookie->getSameSite(),
                        'expires' => $cookie->getExpiresTime(),
                    ];
                }
            } else {
                $debugInfo['getCookies_exists'] = false;
            }

            // Also check for set-cookie in raw headers
            if (isset($allResponseHeaders['set-cookie'])) {
                $setCookieHeaders['raw_set_cookie'] = $allResponseHeaders['set-cookie'];
            }
        }

        Log::channel('single')->info('=== COOKIE DEBUG - AFTER REQUEST ===', [
            'session_id_after' => $sessionIdAfter,
            'session_changed' => $sessionIdBefore !== $sessionIdAfter,
            'set_cookie_headers_count' => count($setCookieHeaders),
            'set_cookie_headers' => $setCookieHeaders,
            'all_response_headers' => array_keys($allResponseHeaders), // Just the keys to avoid huge log
            'has_set_cookie_header' => isset($allResponseHeaders['set-cookie']),
            'response_status' => $response->getStatusCode(),
            'response_class' => get_class($response),
            'debug_info' => $debugInfo,
            'user_id' => auth()->id(),
            'session_has_auth' => session()->has('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d'),
        ]);

        if ($sessionIdBefore !== $sessionIdAfter) {
            Log::channel('single')->warning('!!! SESSION ID CHANGED DURING REQUEST !!!', [
                'from' => $sessionIdBefore,
                'to' => $sessionIdAfter,
                'path' => $request->path(),
            ]);
        }

        return $response;
    }
}
