<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MarketplaceClientAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key from header
        $apiKey = $request->header('X-API-Key')
            ?? $request->header('Authorization');

        // Remove "Bearer " prefix if present
        if ($apiKey && str_starts_with($apiKey, 'Bearer ')) {
            $apiKey = substr($apiKey, 7);
        }

        if (empty($apiKey)) {
            $this->logFailedAttempt($request, 'missing_api_key');
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        // Find marketplace client by API key
        $client = MarketplaceClient::where('api_key', $apiKey)->first();

        if (!$client) {
            $this->logFailedAttempt($request, 'invalid_api_key');
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
            ], 401);
        }

        if (!$client->isActive()) {
            $this->logFailedAttempt($request, 'inactive_client', $client);
            return response()->json([
                'success' => false,
                'message' => 'Marketplace client account is not active',
            ], 403);
        }

        // Validate IP restriction (if configured)
        if (!$this->validateIpRestriction($request, $client)) {
            $this->logFailedAttempt($request, 'ip_not_allowed', $client);
            return response()->json([
                'success' => false,
                'message' => 'Access denied from this IP address',
            ], 403);
        }

        // Validate domain restriction (if configured)
        if (!$this->validateDomainRestriction($request, $client)) {
            $this->logFailedAttempt($request, 'domain_not_allowed', $client);
            return response()->json([
                'success' => false,
                'message' => 'Access denied from this domain',
            ], 403);
        }

        // Attach client to request for use in controllers
        $request->attributes->set('marketplace_client', $client);

        // Log successful API call
        $this->logApiCall($request, $client);

        // Update API call stats (async)
        dispatch(function () use ($client) {
            $client->touchApiCall();
            $client->incrementApiCalls();
        })->afterResponse();

        $response = $next($request);

        // Add CORS headers for the client's allowed domains
        $this->addCorsHeaders($request, $response, $client);

        return $response;
    }

    /**
     * Validate IP address restriction.
     */
    protected function validateIpRestriction(Request $request, MarketplaceClient $client): bool
    {
        $allowedIps = $client->settings['allowed_ips'] ?? null;

        // If no IP restriction is set, allow all
        if (empty($allowedIps)) {
            return true;
        }

        $clientIp = $request->ip();

        foreach ($allowedIps as $allowedIp) {
            // Support CIDR notation (e.g., 192.168.1.0/24)
            if (str_contains($allowedIp, '/')) {
                if ($this->ipInCidr($clientIp, $allowedIp)) {
                    return true;
                }
            } elseif ($clientIp === $allowedIp) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate domain/origin restriction.
     */
    protected function validateDomainRestriction(Request $request, MarketplaceClient $client): bool
    {
        $allowedDomains = $client->settings['allowed_domains'] ?? null;

        // If no domain restriction is set, allow all
        if (empty($allowedDomains)) {
            return true;
        }

        $origin = $request->header('Origin');
        $referer = $request->header('Referer');

        // For server-to-server requests (no Origin header), allow if IP is validated
        if (empty($origin) && empty($referer)) {
            return true;
        }

        // Extract domain from Origin or Referer
        $requestDomain = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        if (!$requestDomain && $referer) {
            $requestDomain = parse_url($referer, PHP_URL_HOST);
        }

        if (!$requestDomain) {
            return true; // No domain info, allow (server-to-server)
        }

        foreach ($allowedDomains as $allowedDomain) {
            // Support wildcard subdomains (e.g., *.ambilet.ro)
            if (str_starts_with($allowedDomain, '*.')) {
                $baseDomain = substr($allowedDomain, 2);
                if ($requestDomain === $baseDomain || str_ends_with($requestDomain, '.' . $baseDomain)) {
                    return true;
                }
            } elseif ($requestDomain === $allowedDomain) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }

    /**
     * Add CORS headers to the response.
     */
    protected function addCorsHeaders(Request $request, Response $response, MarketplaceClient $client): void
    {
        $origin = $request->header('Origin');

        if (!$origin) {
            return;
        }

        $allowedDomains = $client->settings['allowed_domains'] ?? null;

        // If domain restriction is configured, validate origin
        if (!empty($allowedDomains)) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $isAllowed = false;

            foreach ($allowedDomains as $allowedDomain) {
                if (str_starts_with($allowedDomain, '*.')) {
                    $baseDomain = substr($allowedDomain, 2);
                    if ($originHost === $baseDomain || str_ends_with($originHost, '.' . $baseDomain)) {
                        $isAllowed = true;
                        break;
                    }
                } elseif ($originHost === $allowedDomain) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return; // Don't add CORS headers for non-allowed origins
            }
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
    }

    /**
     * Log a failed authentication attempt.
     */
    protected function logFailedAttempt(Request $request, string $reason, ?MarketplaceClient $client = null): void
    {
        Log::warning('Marketplace API authentication failed', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'origin' => $request->header('Origin'),
            'referer' => $request->header('Referer'),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'client_id' => $client?->id,
            'client_name' => $client?->name,
        ]);
    }

    /**
     * Log a successful API call.
     */
    protected function logApiCall(Request $request, MarketplaceClient $client): void
    {
        Log::channel('marketplace')->info('Marketplace API call', [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'ip' => $request->ip(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
        ]);
    }
}
