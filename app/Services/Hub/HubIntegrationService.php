<?php

namespace App\Services\Hub;

use App\Models\HubConnection;
use App\Models\HubConnector;
use App\Models\HubEvent;
use App\Models\HubWebhookEndpoint;
use App\Models\HubSyncJob;
use App\Services\Hub\Adapters\ConnectorAdapterInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Hub Integration Service
 *
 * Manages third-party integrations for tenants including:
 * - Connector catalog and configuration
 * - OAuth flows and credential management
 * - Event processing (inbound/outbound)
 * - Webhook management
 * - Data synchronization
 */
class HubIntegrationService
{
    protected array $adapters = [];

    /**
     * Register a connector adapter
     */
    public function registerAdapter(string $slug, ConnectorAdapterInterface $adapter): void
    {
        $this->adapters[$slug] = $adapter;
    }

    /**
     * Get adapter for a connector
     */
    public function getAdapter(string $slug): ?ConnectorAdapterInterface
    {
        return $this->adapters[$slug] ?? null;
    }

    /**
     * Get all available connectors
     */
    public function getConnectors(array $filters = []): array
    {
        $query = HubConnector::active();

        if (isset($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (isset($filters['premium']) && $filters['premium'] === false) {
            $query->free();
        }

        return $query->get()->toArray();
    }

    /**
     * Get connector by slug
     */
    public function getConnector(string $slug): ?HubConnector
    {
        return HubConnector::where('slug', $slug)->first();
    }

    /**
     * Get tenant's active connections
     */
    public function getTenantConnections(string $tenantId): array
    {
        return HubConnection::forTenant($tenantId)
            ->with('connector')
            ->get()
            ->toArray();
    }

    /**
     * Initiate a new connection for a tenant
     */
    public function initiateConnection(string $tenantId, string $connectorSlug, ?int $userId = null): array
    {
        $connector = $this->getConnector($connectorSlug);

        if (!$connector) {
            throw new \Exception("Connector '{$connectorSlug}' not found");
        }

        // Check if connection already exists
        $existing = HubConnection::forTenant($tenantId)
            ->where('connector_id', $connector->id)
            ->first();

        if ($existing && $existing->status === 'active') {
            return [
                'success' => false,
                'message' => 'Connection already exists',
                'connection_id' => $existing->id,
            ];
        }

        // Create pending connection
        $connection = HubConnection::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'connector_id' => $connector->id,
            'status' => 'pending',
            'created_by' => $userId,
        ]);

        // If OAuth, generate authorization URL
        if ($connector->auth_type === 'oauth2') {
            $adapter = $this->getAdapter($connectorSlug);
            if ($adapter) {
                $authUrl = $adapter->getAuthorizationUrl($connection->id, $connector->oauth_config ?? []);
                return [
                    'success' => true,
                    'connection_id' => $connection->id,
                    'auth_type' => 'oauth2',
                    'auth_url' => $authUrl,
                ];
            }
        }

        return [
            'success' => true,
            'connection_id' => $connection->id,
            'auth_type' => $connector->auth_type,
            'message' => 'Connection initiated, provide credentials',
        ];
    }

    /**
     * Complete OAuth callback
     */
    public function handleOAuthCallback(string $connectionId, string $code): array
    {
        $connection = HubConnection::with('connector')->find($connectionId);

        if (!$connection) {
            throw new \Exception('Connection not found');
        }

        $adapter = $this->getAdapter($connection->connector->slug);

        if (!$adapter) {
            throw new \Exception('Adapter not found for connector');
        }

        try {
            $tokens = $adapter->exchangeCodeForTokens($code, $connection->connector->oauth_config ?? []);

            $connection->setCredentials([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_type' => $tokens['token_type'] ?? 'Bearer',
            ]);

            $connection->update([
                'status' => 'active',
                'token_expires_at' => isset($tokens['expires_in'])
                    ? now()->addSeconds($tokens['expires_in'])
                    : null,
            ]);

            Log::info('Hub connection activated', [
                'connection_id' => $connectionId,
                'connector' => $connection->connector->slug,
            ]);

            return [
                'success' => true,
                'connection_id' => $connectionId,
                'status' => 'active',
            ];

        } catch (\Exception $e) {
            $connection->markError($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set API key credentials for a connection
     */
    public function setApiKeyCredentials(string $connectionId, array $credentials): array
    {
        $connection = HubConnection::find($connectionId);

        if (!$connection) {
            throw new \Exception('Connection not found');
        }

        $connection->setCredentials($credentials);
        $connection->activate();

        return [
            'success' => true,
            'connection_id' => $connectionId,
            'status' => 'active',
        ];
    }

    /**
     * Test a connection
     */
    public function testConnection(string $connectionId): array
    {
        $connection = HubConnection::with('connector')->find($connectionId);

        if (!$connection) {
            throw new \Exception('Connection not found');
        }

        $adapter = $this->getAdapter($connection->connector->slug);

        if (!$adapter) {
            return [
                'success' => false,
                'error' => 'Adapter not configured',
            ];
        }

        try {
            $credentials = $connection->getDecryptedCredentials();
            $result = $adapter->testConnection($credentials);

            if ($result['success']) {
                $connection->update([
                    'error_count' => 0,
                    'last_error' => null,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $connection->markError($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect a connection
     */
    public function disconnect(string $connectionId): bool
    {
        $connection = HubConnection::find($connectionId);

        if (!$connection) {
            return false;
        }

        // Try to revoke tokens if adapter supports it
        $adapter = $this->getAdapter($connection->connector->slug ?? '');
        if ($adapter) {
            try {
                $credentials = $connection->getDecryptedCredentials();
                $adapter->revokeTokens($credentials);
            } catch (\Exception $e) {
                Log::warning('Failed to revoke tokens', [
                    'connection_id' => $connectionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $connection->delete();

        return true;
    }

    /**
     * Execute an action on a connection
     */
    public function executeAction(string $connectionId, string $action, array $data): array
    {
        $connection = HubConnection::with('connector')->find($connectionId);

        if (!$connection || !$connection->isActive()) {
            throw new \Exception('Connection not active');
        }

        $adapter = $this->getAdapter($connection->connector->slug);

        if (!$adapter) {
            throw new \Exception('Adapter not found');
        }

        $credentials = $connection->getDecryptedCredentials();

        try {
            $result = $adapter->executeAction($action, $data, $credentials);

            // Log the event
            HubEvent::create([
                'id' => (string) Str::uuid(),
                'connection_id' => $connectionId,
                'tenant_id' => $connection->tenant_id,
                'direction' => 'outbound',
                'event_type' => $action,
                'payload' => $data,
                'status' => 'success',
                'processed_at' => now(),
            ]);

            return $result;

        } catch (\Exception $e) {
            // Log failed event
            HubEvent::create([
                'id' => (string) Str::uuid(),
                'connection_id' => $connectionId,
                'tenant_id' => $connection->tenant_id,
                'direction' => 'outbound',
                'event_type' => $action,
                'payload' => $data,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle incoming webhook from a connector
     */
    public function handleIncomingWebhook(string $connectorSlug, string $tenantId, array $payload): array
    {
        $connection = HubConnection::forTenant($tenantId)
            ->whereHas('connector', fn($q) => $q->where('slug', $connectorSlug))
            ->first();

        if (!$connection) {
            return [
                'success' => false,
                'error' => 'No active connection found',
            ];
        }

        $adapter = $this->getAdapter($connectorSlug);
        $eventType = $adapter ? $adapter->parseWebhookEventType($payload) : 'unknown';

        // Create event record
        $event = HubEvent::create([
            'id' => (string) Str::uuid(),
            'connection_id' => $connection->id,
            'tenant_id' => $tenantId,
            'direction' => 'inbound',
            'event_type' => $eventType,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        // Dispatch to tenant's webhook endpoints
        $this->dispatchToWebhookEndpoints($tenantId, $eventType, $payload);

        return [
            'success' => true,
            'event_id' => $event->id,
        ];
    }

    /**
     * Dispatch event to tenant's configured webhook endpoints
     */
    protected function dispatchToWebhookEndpoints(string $tenantId, string $eventType, array $payload): void
    {
        $endpoints = HubWebhookEndpoint::forTenant($tenantId)
            ->active()
            ->get();

        foreach ($endpoints as $endpoint) {
            if (!$endpoint->isSubscribedTo($eventType)) {
                continue;
            }

            // Queue webhook delivery
            dispatch(function () use ($endpoint, $eventType, $payload) {
                $this->deliverWebhook($endpoint, $eventType, $payload);
            })->onQueue('webhooks');
        }
    }

    /**
     * Deliver webhook to an endpoint
     */
    protected function deliverWebhook(HubWebhookEndpoint $endpoint, string $eventType, array $payload): void
    {
        $body = json_encode([
            'event' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ]);

        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Hub-Signature' => $signature,
                    'X-Hub-Event' => $eventType,
                ])
                ->post($endpoint->url, json_decode($body, true));

            if ($response->successful()) {
                $endpoint->recordSuccess();
            } else {
                $endpoint->recordFailure();
            }

        } catch (\Exception $e) {
            $endpoint->recordFailure();
            Log::error('Webhook delivery failed', [
                'endpoint_id' => $endpoint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a sync job
     */
    public function createSyncJob(string $connectionId, string $type = 'manual'): HubSyncJob
    {
        $connection = HubConnection::find($connectionId);

        return HubSyncJob::create([
            'id' => (string) Str::uuid(),
            'connection_id' => $connectionId,
            'tenant_id' => $connection->tenant_id,
            'job_type' => $type,
            'status' => 'queued',
        ]);
    }

    /**
     * Refresh expired tokens
     */
    public function refreshExpiredTokens(): int
    {
        $connections = HubConnection::where('status', 'active')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addMinutes(10))
            ->with('connector')
            ->get();

        $refreshed = 0;

        foreach ($connections as $connection) {
            try {
                $adapter = $this->getAdapter($connection->connector->slug);
                if (!$adapter) {
                    continue;
                }

                $credentials = $connection->getDecryptedCredentials();
                if (!isset($credentials['refresh_token'])) {
                    continue;
                }

                $tokens = $adapter->refreshTokens($credentials['refresh_token'], $connection->connector->oauth_config ?? []);

                $connection->setCredentials([
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? $credentials['refresh_token'],
                    'token_type' => $tokens['token_type'] ?? 'Bearer',
                ]);

                $connection->update([
                    'token_expires_at' => isset($tokens['expires_in'])
                        ? now()->addSeconds($tokens['expires_in'])
                        : null,
                ]);

                $refreshed++;

            } catch (\Exception $e) {
                $connection->markError('Token refresh failed: ' . $e->getMessage());
                Log::error('Token refresh failed', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $refreshed;
    }
}
