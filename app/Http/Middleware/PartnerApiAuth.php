<?php

namespace App\Http\Middleware;

use App\Models\MarketplacePartner;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates /api/partner/v1 calls with a media partner's own key.
 *
 * Usage: partner.auth:events:read  (several scopes: partner.auth:artists:read,events:read)
 *
 * Accepts "Authorization: Bearer <key>" or "X-API-Key: <key>". Rate limiting is
 * per partner (rate_limit_per_minute); failed key lookups are limited per IP.
 */
class PartnerApiAuth
{
    private const FAILED_ATTEMPTS_PER_MINUTE = 30;

    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $failKey = 'partner-api-fail:' . $request->ip();
        if (RateLimiter::tooManyAttempts($failKey, self::FAILED_ATTEMPTS_PER_MINUTE)) {
            return $this->deny('rate_limited', 'Too many failed attempts.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($failKey));
        }

        $key = $request->bearerToken() ?: $request->header('X-API-Key');
        if (!$key) {
            return $this->deny('missing_api_key', 'API key is required.', 401);
        }

        $partner = MarketplacePartner::with('marketplaceClient')->where('api_key_hash', hash('sha256', $key))->first();
        if (!$partner) {
            RateLimiter::hit($failKey, 60);

            return $this->deny('invalid_api_key', 'Invalid API key.', 401);
        }

        $client = $partner->marketplaceClient;
        if (!$partner->isActive() || !$client || !$client->isActive() || !$this->microserviceActive($client)) {
            return $this->deny('partner_inactive', 'Partner access is not active.', 403);
        }

        if (!$partner->allowsIp($request->ip())) {
            return $this->deny('ip_not_allowed', 'Access denied from this IP address.', 403);
        }

        foreach ($scopes as $scope) {
            if (!$partner->hasScope($scope)) {
                return $this->deny('missing_scope', "This API key does not have the {$scope} scope.", 403);
            }
        }

        $limitKey = 'partner-api:' . $partner->id;
        $limit = max(1, (int) $partner->rate_limit_per_minute);
        if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
            return $this->deny('rate_limited', 'Too many requests.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($limitKey))
                ->header('X-RateLimit-Limit', (string) $limit)
                ->header('X-RateLimit-Remaining', '0');
        }
        RateLimiter::hit($limitKey, 60);

        // At most one write a minute per partner.
        if (!$partner->last_used_at || $partner->last_used_at->lt(now()->subMinute())) {
            $partner->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        $request->attributes->set('marketplace_partner', $partner);
        $request->attributes->set('marketplace_client', $client);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($limitKey, $limit));

        return $response;
    }

    private function microserviceActive($client): bool
    {
        return Cache::remember(
            "partner_api_microservice_{$client->id}",
            now()->addMinute(),
            fn () => $client->hasMicroservice(MarketplacePartner::MICROSERVICE)
        );
    }

    private function deny(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
