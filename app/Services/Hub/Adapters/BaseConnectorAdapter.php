<?php

namespace App\Services\Hub\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base class for connector adapters with common functionality
 */
abstract class BaseConnectorAdapter implements ConnectorAdapterInterface
{
    protected string $slug;
    protected string $baseUrl;

    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Make an authenticated HTTP request
     */
    protected function makeRequest(string $method, string $url, array $credentials, array $data = []): array
    {
        $headers = $this->getAuthHeaders($credentials);

        try {
            $request = Http::withHeaders($headers)->timeout(30);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url, $data),
                default => throw new \Exception("Unsupported HTTP method: {$method}"),
            };

            if ($response->failed()) {
                throw new \Exception("API request failed: " . $response->body());
            }

            return $response->json() ?? [];

        } catch (\Exception $e) {
            Log::error("Hub connector request failed", [
                'connector' => $this->slug,
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get authentication headers
     */
    protected function getAuthHeaders(array $credentials): array
    {
        $tokenType = $credentials['token_type'] ?? 'Bearer';
        $accessToken = $credentials['access_token'] ?? '';

        return [
            'Authorization' => "{$tokenType} {$accessToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Build OAuth2 authorization URL
     */
    protected function buildAuthorizationUrl(string $authUrl, array $params): string
    {
        return $authUrl . '?' . http_build_query($params);
    }

    /**
     * Exchange code for tokens via OAuth2
     */
    protected function exchangeCodeViaOAuth(string $tokenUrl, array $params): array
    {
        $response = Http::asForm()->post($tokenUrl, $params);

        if ($response->failed()) {
            throw new \Exception("Token exchange failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh tokens via OAuth2
     */
    protected function refreshTokensViaOAuth(string $tokenUrl, string $refreshToken, array $params): array
    {
        $params['refresh_token'] = $refreshToken;
        $params['grant_type'] = 'refresh_token';

        $response = Http::asForm()->post($tokenUrl, $params);

        if ($response->failed()) {
            throw new \Exception("Token refresh failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get redirect URI for OAuth callback
     */
    protected function getRedirectUri(): string
    {
        return config('app.url') . '/api/hub/oauth/callback';
    }

    public function revokeTokens(array $credentials): bool
    {
        // Default implementation - override in specific adapters
        return true;
    }

    public function parseWebhookEventType(array $payload): string
    {
        return $payload['event'] ?? $payload['type'] ?? 'unknown';
    }
}
