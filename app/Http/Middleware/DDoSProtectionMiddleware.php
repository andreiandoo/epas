<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * DDoS Protection Middleware
 *
 * Provides multi-layer protection against DDoS attacks:
 * - Per-second and per-minute rate limiting
 * - Suspicious behavior detection
 * - Progressive IP blocking
 * - Connection limiting
 */
class DDoSProtectionMiddleware
{
    /**
     * Maximum requests per second per IP
     */
    protected int $maxRequestsPerSecond = 10;

    /**
     * Maximum requests per minute per IP
     */
    protected int $maxRequestsPerMinute = 120;

    /**
     * Number of violations before temporary block
     */
    protected int $violationsBeforeBlock = 3;

    /**
     * Temporary block duration in seconds
     */
    protected int $blockDuration = 3600; // 1 hour

    /**
     * Paths that are more strictly rate limited
     */
    protected array $strictPaths = [
        'api/login',
        'api/register',
        'api/password/reset',
        'api/checkout',
        'api/payment',
    ];

    /**
     * Paths that are exempted from DDoS protection (webhooks, health checks)
     */
    protected array $exemptPaths = [
        'api/webhooks/',
        'api/health',
        'api/status',
    ];

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $this->getClientIp($request);

        // Check exemptions
        if ($this->isExemptPath($request->path())) {
            return $next($request);
        }

        // Check if IP is already blocked
        if ($this->isBlocked($ip)) {
            Log::warning('Blocked IP attempted access', [
                'ip' => $ip,
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->blockedResponse($ip);
        }

        // Check for suspicious headers/patterns
        if ($this->isSuspiciousRequest($request)) {
            $this->recordViolation($ip, 'suspicious_request');

            Log::alert('Suspicious request pattern detected', [
                'ip' => $ip,
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
                'headers' => $request->headers->all(),
            ]);

            return $this->tooManyRequestsResponse('Suspicious activity detected');
        }

        // Apply stricter limits for sensitive paths
        $isStrictPath = $this->isStrictPath($request->path());
        $perSecondLimit = $isStrictPath ? 2 : $this->maxRequestsPerSecond;
        $perMinuteLimit = $isStrictPath ? 20 : $this->maxRequestsPerMinute;

        // Per-second rate limiting
        if (!$this->checkRateLimit($ip, 'sec', $perSecondLimit, 1)) {
            $this->recordViolation($ip, 'per_second_exceeded');

            return $this->tooManyRequestsResponse('Rate limit exceeded. Please slow down.');
        }

        // Per-minute rate limiting
        if (!$this->checkRateLimit($ip, 'min', $perMinuteLimit, 60)) {
            $this->recordViolation($ip, 'per_minute_exceeded');

            return $this->tooManyRequestsResponse('Too many requests. Please wait a moment.');
        }

        // Track concurrent connections (optional, requires Redis)
        if ($this->hasTooManyConcurrentConnections($ip)) {
            $this->recordViolation($ip, 'concurrent_connections');

            return $this->tooManyRequestsResponse('Too many concurrent connections.');
        }

        $response = $next($request);

        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $ip);
    }

    /**
     * Get the real client IP, accounting for proxies
     */
    protected function getClientIp(Request $request): string
    {
        // If behind Cloudflare
        if ($request->header('CF-Connecting-IP')) {
            return $request->header('CF-Connecting-IP');
        }

        // If behind load balancer with X-Forwarded-For
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor) {
            // Get the first (original) IP
            $ips = explode(',', $forwardedFor);
            return trim($ips[0]);
        }

        return $request->ip();
    }

    /**
     * Check if IP is blocked
     */
    protected function isBlocked(string $ip): bool
    {
        return Cache::has('ddos_blocked:' . $ip);
    }

    /**
     * Check if path is exempt from DDoS protection
     */
    protected function isExemptPath(string $path): bool
    {
        foreach ($this->exemptPaths as $exemptPath) {
            if (str_starts_with($path, $exemptPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if path requires strict rate limiting
     */
    protected function isStrictPath(string $path): bool
    {
        foreach ($this->strictPaths as $strictPath) {
            if (str_starts_with($path, $strictPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check rate limit
     */
    protected function checkRateLimit(string $ip, string $suffix, int $maxAttempts, int $decaySeconds): bool
    {
        $key = "ddos:{$ip}:{$suffix}";

        return RateLimiter::attempt(
            $key,
            $maxAttempts,
            fn() => true,
            $decaySeconds
        );
    }

    /**
     * Record a rate limit violation
     */
    protected function recordViolation(string $ip, string $type): void
    {
        $key = 'ddos_violations:' . $ip;
        $violations = Cache::increment($key);

        // Set expiry on first violation
        if ($violations === 1) {
            Cache::put($key, 1, 3600); // 1 hour window
        }

        Log::warning('DDoS violation recorded', [
            'ip' => $ip,
            'type' => $type,
            'total_violations' => $violations,
        ]);

        // Block IP if too many violations
        if ($violations >= $this->violationsBeforeBlock) {
            $this->blockIp($ip);
        }
    }

    /**
     * Block an IP address
     */
    protected function blockIp(string $ip): void
    {
        Cache::put('ddos_blocked:' . $ip, true, $this->blockDuration);

        Log::alert('IP blocked due to DDoS violations', [
            'ip' => $ip,
            'duration' => $this->blockDuration,
        ]);

        // Optionally notify administrators
        // Notification::route('slack', config('logging.slack_webhook_url'))
        //     ->notify(new DDoSBlockNotification($ip));
    }

    /**
     * Check for suspicious request patterns
     */
    protected function isSuspiciousRequest(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';

        // Check for known attack tools
        $suspiciousAgents = [
            'sqlmap',
            'nikto',
            'nessus',
            'openvas',
            'masscan',
            'nmap',
            'curl/',      // Unmodified curl
            'wget/',      // Unmodified wget
            'python-requests/', // Generic Python requests
            'Go-http-client',
        ];

        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return true;
            }
        }

        // Check for missing/empty user agent (common in bots)
        if (empty($userAgent) && !$this->isApiRequest($request)) {
            return true;
        }

        // Check for abnormally large headers
        $headerSize = strlen(implode('', $request->headers->all()));
        if ($headerSize > 8192) { // 8KB header limit
            return true;
        }

        // Check for common attack patterns in URL
        $suspiciousPatterns = [
            '/\.\.\//',           // Path traversal
            '/\x00/',             // Null byte
            '/<script/i',         // XSS
            '/UNION\s+SELECT/i',  // SQL injection
        ];

        $fullUrl = $request->fullUrl();
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $fullUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request is an API request
     */
    protected function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/') ||
               $request->expectsJson() ||
               $request->header('X-API-Key');
    }

    /**
     * Check concurrent connections (requires Redis)
     */
    protected function hasTooManyConcurrentConnections(string $ip): bool
    {
        // This is a simplified check. For production, use Redis INCR with TTL
        $key = 'ddos_concurrent:' . $ip;
        $current = Cache::get($key, 0);

        if ($current >= 50) { // Max 50 concurrent connections per IP
            return true;
        }

        // Increment and set expiry
        Cache::put($key, $current + 1, 30); // 30 second window

        return false;
    }

    /**
     * Return a 429 Too Many Requests response
     */
    protected function tooManyRequestsResponse(string $message): Response
    {
        return response()->json([
            'error' => 'Too Many Requests',
            'message' => $message,
        ], 429)->withHeaders([
            'Retry-After' => 60,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Return a blocked response
     */
    protected function blockedResponse(string $ip): Response
    {
        $remainingTime = Cache::get('ddos_blocked:' . $ip . ':ttl', $this->blockDuration);

        return response()->json([
            'error' => 'Access Denied',
            'message' => 'Your IP has been temporarily blocked due to suspicious activity.',
            'retry_after' => $remainingTime,
        ], 403)->withHeaders([
            'Retry-After' => $remainingTime,
        ]);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(Response $response, string $ip): Response
    {
        $remaining = RateLimiter::remaining("ddos:{$ip}:min", $this->maxRequestsPerMinute);

        $response->headers->set('X-RateLimit-Limit', (string) $this->maxRequestsPerMinute);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', (string) (time() + 60));

        return $response;
    }
}
