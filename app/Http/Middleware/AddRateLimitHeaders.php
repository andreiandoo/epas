<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Rate Limit Headers Middleware
 *
 * Adds standard rate limit headers to API responses:
 * - X-RateLimit-Limit: Maximum requests allowed
 * - X-RateLimit-Remaining: Requests remaining
 * - X-RateLimit-Reset: Unix timestamp when limit resets
 */
class AddRateLimitHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add headers to API routes
        if (!$request->is('api/*')) {
            return $response;
        }

        // Get rate limit info based on authentication method
        $rateLimit = $this->getRateLimitInfo($request);

        if ($rateLimit) {
            $response->headers->set('X-RateLimit-Limit', $rateLimit['limit']);
            $response->headers->set('X-RateLimit-Remaining', $rateLimit['remaining']);
            $response->headers->set('X-RateLimit-Reset', $rateLimit['reset']);
        }

        return $response;
    }

    /**
     * Get rate limit information for the current request
     *
     * @param Request $request
     * @return array|null
     */
    protected function getRateLimitInfo(Request $request): ?array
    {
        // Check if authenticated with API key
        if ($apiKeyId = $request->attributes->get('api_key_id')) {
            return $this->getApiKeyRateLimit($apiKeyId);
        }

        // Check if using Laravel's default rate limiting
        $key = $this->getRateLimitKey($request);
        if ($key) {
            return $this->getDefaultRateLimit($key);
        }

        return null;
    }

    /**
     * Get rate limit info for API key authentication
     *
     * @param string $apiKeyId
     * @return array
     */
    protected function getApiKeyRateLimit(string $apiKeyId): array
    {
        $cacheKey = "api_rate_limit:{$apiKeyId}";
        $requests = Cache::get($cacheKey, 0);

        // Get the API key's rate limit from database
        $apiKey = \DB::table('tenant_api_keys')
            ->where('id', $apiKeyId)
            ->first();

        $limit = $apiKey->rate_limit ?? config('microservices.api.default_rate_limit', 1000);
        $remaining = max(0, $limit - $requests);

        // Calculate reset time (1 hour from now)
        $reset = now()->addHour()->timestamp;

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }

    /**
     * Get rate limit info for default Laravel rate limiting
     *
     * @param string $key
     * @return array
     */
    protected function getDefaultRateLimit(string $key): array
    {
        // Default Laravel rate limit: 60 per minute
        $limit = 60;
        $window = 60; // seconds

        $attempts = Cache::get($key, 0);
        $remaining = max(0, $limit - $attempts);
        $reset = now()->addSeconds($window)->timestamp;

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }

    /**
     * Get the rate limit cache key for the current request
     *
     * @param Request $request
     * @return string|null
     */
    protected function getRateLimitKey(Request $request): ?string
    {
        // Try to get the key from various sources
        if ($user = $request->user()) {
            return 'rate_limit:user:' . $user->id;
        }

        if ($ip = $request->ip()) {
            return 'rate_limit:ip:' . $ip;
        }

        return null;
    }
}
