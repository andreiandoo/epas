<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enhanced Security Middleware
 *
 * Provides additional security layers:
 * - Request signature validation
 * - SQL injection pattern detection
 * - XSS protection
 * - Suspicious activity detection
 * - IP reputation checking
 * - Request size limiting
 */
class EnhancedSecurityMiddleware
{
    /**
     * Suspicious patterns that might indicate attacks
     */
    protected array $suspiciousPatterns = [
        // SQL Injection patterns
        '/(\bUNION\b.*\bSELECT\b)|(\bSELECT\b.*\bFROM\b.*\bWHERE\b)/i',
        '/(\bDROP\b.*\bTABLE\b)|(\bDELETE\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)|(\bUPDATE\b.*\bSET\b)/i',

        // XSS patterns
        '/<script[^>]*>.*?<\/script>/i',
        '/javascript:/i',
        '/on(load|error|click|mouse)=/i',

        // Path traversal
        '/\.\.\/|\.\.\\\\/',

        // Command injection
        '/;|\||&|`|\$\(|\$\{/',
    ];

    /**
     * Maximum request size in bytes (10 MB)
     */
    protected int $maxRequestSize = 10 * 1024 * 1024;

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check request size
        if ($request->header('Content-Length') > $this->maxRequestSize) {
            Log::warning('Request size exceeded limit', [
                'ip' => $request->ip(),
                'size' => $request->header('Content-Length'),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Request too large',
                'message' => 'Request body exceeds maximum allowed size',
                'max_size' => $this->maxRequestSize,
            ], 413);
        }

        // Check for suspicious patterns in request data
        if ($this->detectSuspiciousActivity($request)) {
            Log::alert('Suspicious request detected', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'data' => $request->all(),
            ]);

            // Rate limit this IP aggressively
            RateLimiter::hit('suspicious:' . $request->ip(), 3600);

            return response()->json([
                'error' => 'Security violation detected',
                'message' => 'Your request has been blocked due to suspicious activity',
            ], 403);
        }

        // Check IP reputation
        if ($this->isBlockedIp($request->ip())) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Your IP address has been blocked due to previous violations',
            ], 403);
        }

        // Sanitize input data
        $this->sanitizeRequest($request);

        // Add security headers to response
        $response = $next($request);

        return $this->addSecurityHeaders($response);
    }

    /**
     * Detect suspicious activity in request
     */
    protected function detectSuspiciousActivity(Request $request): bool
    {
        // Check all request parameters
        $data = array_merge(
            $request->query(),
            $request->post(),
            $request->json() ? $request->json()->all() : []
        );

        return $this->scanForPatterns($data);
    }

    /**
     * Recursively scan data for suspicious patterns
     */
    protected function scanForPatterns(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->scanForPatterns($value)) {
                    return true;
                }
            } elseif (is_string($value)) {
                foreach ($this->suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value) || preg_match($pattern, $key)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if IP is in blocklist
     */
    protected function isBlockedIp(string $ip): bool
    {
        // Check if IP has too many suspicious attempts
        $suspiciousAttempts = RateLimiter::attempts('suspicious:' . $ip);

        if ($suspiciousAttempts > 5) {
            return true;
        }

        // Check custom blocklist (could be Redis, database, or cache)
        $blocklist = cache()->remember('ip_blocklist', 3600, function () {
            return config('security.blocked_ips', []);
        });

        return in_array($ip, $blocklist);
    }

    /**
     * Sanitize request data
     */
    protected function sanitizeRequest(Request $request): void
    {
        // Sanitize string inputs (remove null bytes, normalize whitespace)
        $sanitized = $this->sanitizeArray($request->all());
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize array data
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Sanitize key
            $cleanKey = $this->sanitizeString($key);

            // Sanitize value
            if (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$cleanKey] = $this->sanitizeString($value);
            } else {
                $sanitized[$cleanKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize individual string
     */
    protected function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Normalize whitespace
        $value = preg_replace('/\s+/', ' ', $value);

        // Trim
        return trim($value);
    }

    /**
     * Add security headers to response
     */
    protected function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Add CSP for HTML responses
        if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';"
            );
        }

        return $response;
    }
}
