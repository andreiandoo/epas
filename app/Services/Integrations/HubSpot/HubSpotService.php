<?php

namespace App\Services\Integrations\HubSpot;

use App\Models\Integrations\HubSpot\HubSpotConnection;
use App\Models\Integrations\HubSpot\HubSpotSyncLog;
use Illuminate\Support\Facades\Http;

class HubSpotService
{
    protected string $authorizeUrl = 'https://app.hubspot.com/oauth/authorize';
    protected string $tokenUrl = 'https://api.hubapi.com/oauth/v1/token';
    protected string $apiUrl = 'https://api.hubapi.com';

    public function getAuthorizationUrl(int $tenantId, array $scopes = []): string
    {
        $defaultScopes = [
            'crm.objects.contacts.read', 'crm.objects.contacts.write',
            'crm.objects.deals.read', 'crm.objects.deals.write',
            'crm.objects.companies.read', 'crm.objects.companies.write',
        ];

        $params = [
            'client_id' => config('services.hubspot.client_id'),
            'redirect_uri' => config('services.hubspot.redirect_uri'),
            'scope' => implode(' ', array_unique(array_merge($defaultScopes, $scopes))),
            'state' => encrypt(['tenant_id' => $tenantId]),
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): HubSpotConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.hubspot.client_id'),
            'client_secret' => config('services.hubspot.client_secret'),
            'redirect_uri' => config('services.hubspot.redirect_uri'),
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        // Get account info
        $accountInfo = Http::withToken($data['access_token'])
            ->get("{$this->apiUrl}/account-info/v3/details")
            ->json();

        return HubSpotConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'hub_id' => $accountInfo['portalId'] ?? null],
            [
                'hub_domain' => $accountInfo['uiDomain'] ?? null,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function createContact(HubSpotConnection $connection, array $properties): HubSpotSyncLog
    {
        return $this->createRecord($connection, 'contacts', $properties);
    }

    public function createDeal(HubSpotConnection $connection, array $properties): HubSpotSyncLog
    {
        return $this->createRecord($connection, 'deals', $properties);
    }

    public function createCompany(HubSpotConnection $connection, array $properties): HubSpotSyncLog
    {
        return $this->createRecord($connection, 'companies', $properties);
    }

    public function createRecord(HubSpotConnection $connection, string $objectType, array $properties): HubSpotSyncLog
    {
        $this->ensureValidToken($connection);

        $log = HubSpotSyncLog::create([
            'connection_id' => $connection->id,
            'object_type' => $objectType,
            'operation' => 'create',
            'direction' => 'outbound',
            'status' => 'pending',
            'payload' => $properties,
            'correlation_ref' => $properties['correlation_ref'] ?? null,
        ]);

        unset($properties['correlation_ref']);

        $response = Http::withToken($connection->access_token)
            ->post("{$this->apiUrl}/crm/v3/objects/{$objectType}", [
                'properties' => $properties,
            ]);

        if ($response->successful()) {
            $result = $response->json();
            $log->update([
                'hubspot_id' => $result['id'],
                'status' => 'completed',
                'response' => $result,
            ]);
        } else {
            $log->update([
                'status' => 'failed',
                'response' => $response->json(),
            ]);
        }

        $connection->update(['last_used_at' => now()]);

        return $log->fresh();
    }

    public function updateRecord(HubSpotConnection $connection, string $objectType, string $hubspotId, array $properties): HubSpotSyncLog
    {
        $this->ensureValidToken($connection);

        $log = HubSpotSyncLog::create([
            'connection_id' => $connection->id,
            'object_type' => $objectType,
            'operation' => 'update',
            'hubspot_id' => $hubspotId,
            'direction' => 'outbound',
            'status' => 'pending',
            'payload' => $properties,
        ]);

        $response = Http::withToken($connection->access_token)
            ->patch("{$this->apiUrl}/crm/v3/objects/{$objectType}/{$hubspotId}", [
                'properties' => $properties,
            ]);

        $log->update([
            'status' => $response->successful() ? 'completed' : 'failed',
            'response' => $response->json(),
        ]);

        return $log->fresh();
    }

    public function searchRecords(HubSpotConnection $connection, string $objectType, array $filters): array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->post("{$this->apiUrl}/crm/v3/objects/{$objectType}/search", [
                'filterGroups' => [['filters' => $filters]],
                'limit' => 100,
            ]);

        return $response->json('results') ?? [];
    }

    public function getRecord(HubSpotConnection $connection, string $objectType, string $hubspotId): ?array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get("{$this->apiUrl}/crm/v3/objects/{$objectType}/{$hubspotId}");

        return $response->successful() ? $response->json() : null;
    }

    protected function ensureValidToken(HubSpotConnection $connection): void
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $this->refreshToken($connection);
        }
    }

    protected function refreshToken(HubSpotConnection $connection): void
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.hubspot.client_id'),
            'client_secret' => config('services.hubspot.client_secret'),
            'refresh_token' => $connection->refresh_token,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $connection->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
            ]);
        }
    }

    public function getConnection(int $tenantId): ?HubSpotConnection
    {
        return HubSpotConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}
