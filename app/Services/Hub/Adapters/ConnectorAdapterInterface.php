<?php

namespace App\Services\Hub\Adapters;

/**
 * Interface for Hub Integration connector adapters
 */
interface ConnectorAdapterInterface
{
    /**
     * Get the connector slug
     */
    public function getSlug(): string;

    /**
     * Get the authorization URL for OAuth2 flow
     */
    public function getAuthorizationUrl(string $connectionId, array $config): string;

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code, array $config): array;

    /**
     * Refresh access token using refresh token
     */
    public function refreshTokens(string $refreshToken, array $config): array;

    /**
     * Revoke tokens
     */
    public function revokeTokens(array $credentials): bool;

    /**
     * Test the connection
     */
    public function testConnection(array $credentials): array;

    /**
     * Execute an action
     */
    public function executeAction(string $action, array $data, array $credentials): array;

    /**
     * Parse webhook event type from payload
     */
    public function parseWebhookEventType(array $payload): string;

    /**
     * Get supported actions
     */
    public function getSupportedActions(): array;

    /**
     * Get supported events
     */
    public function getSupportedEvents(): array;
}
