<?php

namespace App\Services\Integrations\Salesforce;

use App\Models\Integrations\Salesforce\SalesforceConnection;
use App\Models\Integrations\Salesforce\SalesforceSyncLog;
use App\Models\Integrations\Salesforce\SalesforceFieldMapping;
use Illuminate\Support\Facades\Http;

class SalesforceService
{
    protected string $authorizeUrl = 'https://login.salesforce.com/services/oauth2/authorize';
    protected string $tokenUrl = 'https://login.salesforce.com/services/oauth2/token';

    public function getAuthorizationUrl(int $tenantId): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.salesforce.client_id'),
            'redirect_uri' => config('services.salesforce.redirect_uri'),
            'scope' => 'api refresh_token',
            'state' => encrypt(['tenant_id' => $tenantId]),
        ];

        return $this->authorizeUrl . '?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): SalesforceConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.salesforce.client_id'),
            'client_secret' => config('services.salesforce.client_secret'),
            'redirect_uri' => config('services.salesforce.redirect_uri'),
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        // Get org ID from identity URL
        $identity = Http::withToken($data['access_token'])->get($data['id'])->json();

        return SalesforceConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'org_id' => $identity['organization_id']],
            [
                'instance_url' => $data['instance_url'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function createContact(SalesforceConnection $connection, array $data): SalesforceSyncLog
    {
        return $this->createRecord($connection, 'Contact', $data);
    }

    public function createLead(SalesforceConnection $connection, array $data): SalesforceSyncLog
    {
        return $this->createRecord($connection, 'Lead', $data);
    }

    public function createOpportunity(SalesforceConnection $connection, array $data): SalesforceSyncLog
    {
        return $this->createRecord($connection, 'Opportunity', $data);
    }

    public function createRecord(SalesforceConnection $connection, string $objectType, array $data): SalesforceSyncLog
    {
        $this->ensureValidToken($connection);

        $log = SalesforceSyncLog::create([
            'connection_id' => $connection->id,
            'object_type' => $objectType,
            'operation' => 'create',
            'direction' => 'outbound',
            'status' => 'pending',
            'payload' => $data,
            'correlation_ref' => $data['correlation_ref'] ?? null,
        ]);

        $mappedData = $this->mapFields($connection, $objectType, $data, 'outbound');

        $response = Http::withToken($connection->access_token)
            ->post("{$connection->instance_url}/services/data/v58.0/sobjects/{$objectType}", $mappedData);

        if ($response->successful()) {
            $result = $response->json();
            $log->update([
                'salesforce_id' => $result['id'],
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

    public function updateRecord(SalesforceConnection $connection, string $objectType, string $salesforceId, array $data): SalesforceSyncLog
    {
        $this->ensureValidToken($connection);

        $log = SalesforceSyncLog::create([
            'connection_id' => $connection->id,
            'object_type' => $objectType,
            'operation' => 'update',
            'salesforce_id' => $salesforceId,
            'direction' => 'outbound',
            'status' => 'pending',
            'payload' => $data,
        ]);

        $mappedData = $this->mapFields($connection, $objectType, $data, 'outbound');

        $response = Http::withToken($connection->access_token)
            ->patch("{$connection->instance_url}/services/data/v58.0/sobjects/{$objectType}/{$salesforceId}", $mappedData);

        $log->update([
            'status' => $response->successful() ? 'completed' : 'failed',
            'response' => $response->json(),
        ]);

        return $log->fresh();
    }

    public function query(SalesforceConnection $connection, string $soql): array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get("{$connection->instance_url}/services/data/v58.0/query", ['q' => $soql]);

        return $response->json() ?? [];
    }

    public function getRecord(SalesforceConnection $connection, string $objectType, string $salesforceId): ?array
    {
        $this->ensureValidToken($connection);

        $response = Http::withToken($connection->access_token)
            ->get("{$connection->instance_url}/services/data/v58.0/sobjects/{$objectType}/{$salesforceId}");

        return $response->successful() ? $response->json() : null;
    }

    protected function mapFields(SalesforceConnection $connection, string $objectType, array $data, string $direction): array
    {
        $mappings = $connection->fieldMappings()
            ->where('object_type', $objectType)
            ->where('is_active', true)
            ->where(fn($q) => $q->where('direction', $direction)->orWhere('direction', 'bidirectional'))
            ->get();

        if ($mappings->isEmpty()) {
            return $data; // No mappings, return as-is
        }

        $mapped = [];
        foreach ($mappings as $mapping) {
            $sourceField = $direction === 'outbound' ? $mapping->local_field : $mapping->salesforce_field;
            $targetField = $direction === 'outbound' ? $mapping->salesforce_field : $mapping->local_field;

            if (isset($data[$sourceField])) {
                $mapped[$targetField] = $data[$sourceField];
            }
        }

        return $mapped;
    }

    protected function ensureValidToken(SalesforceConnection $connection): void
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $this->refreshToken($connection);
        }
    }

    protected function refreshToken(SalesforceConnection $connection): void
    {
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'refresh_token',
            'client_id' => config('services.salesforce.client_id'),
            'client_secret' => config('services.salesforce.client_secret'),
            'refresh_token' => $connection->refresh_token,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $connection->update([
                'access_token' => $data['access_token'],
                'instance_url' => $data['instance_url'] ?? $connection->instance_url,
            ]);
        }
    }

    public function getConnection(int $tenantId): ?SalesforceConnection
    {
        return SalesforceConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}
