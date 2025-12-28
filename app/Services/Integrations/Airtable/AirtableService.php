<?php

namespace App\Services\Integrations\Airtable;

use App\Models\Integrations\Airtable\AirtableConnection;
use App\Models\Integrations\Airtable\AirtableBase;
use App\Models\Integrations\Airtable\AirtableTableSync;
use App\Models\Integrations\Airtable\AirtableSyncJob;
use App\Models\Integrations\Airtable\AirtableRecordMapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class AirtableService
{
    protected string $apiBaseUrl = 'https://api.airtable.com/v0';
    protected string $metaApiUrl = 'https://api.airtable.com/v0/meta';
    protected string $oauthUrl = 'https://airtable.com/oauth2/v1';

    // ==========================================
    // OAUTH FLOW
    // ==========================================

    public function getAuthorizationUrl(int $tenantId, array $scopes = []): string
    {
        $defaultScopes = [
            'data.records:read',
            'data.records:write',
            'schema.bases:read',
        ];
        $scopes = array_unique(array_merge($defaultScopes, $scopes));

        $params = [
            'client_id' => config('services.airtable.client_id'),
            'redirect_uri' => config('services.airtable.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => encrypt(['tenant_id' => $tenantId]),
        ];

        return $this->oauthUrl . '/authorize?' . http_build_query($params);
    }

    public function handleCallback(string $code, string $state): AirtableConnection
    {
        $stateData = decrypt($state);
        $tenantId = $stateData['tenant_id'];

        $response = Http::asForm()
            ->withBasicAuth(
                config('services.airtable.client_id'),
                config('services.airtable.client_secret')
            )
            ->post($this->oauthUrl . '/token', [
                'code' => $code,
                'redirect_uri' => config('services.airtable.redirect_uri'),
                'grant_type' => 'authorization_code',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $data = $response->json();

        return AirtableConnection::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'auth_type' => 'oauth',
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds($data['expires_in'])
                    : null,
                'scopes' => explode(' ', $data['scope'] ?? ''),
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function createConnectionWithPAT(int $tenantId, string $personalAccessToken): AirtableConnection
    {
        $connection = AirtableConnection::create([
            'tenant_id' => $tenantId,
            'auth_type' => 'pat',
            'access_token' => $personalAccessToken,
            'status' => 'active',
            'connected_at' => now(),
        ]);

        // Verify by listing bases
        try {
            $this->syncBases($connection);
        } catch (\Exception $e) {
            $connection->delete();
            throw new \Exception('Invalid Personal Access Token');
        }

        return $connection;
    }

    public function refreshToken(AirtableConnection $connection): bool
    {
        if ($connection->auth_type !== 'oauth' || !$connection->refresh_token) {
            return false;
        }

        $response = Http::asForm()
            ->withBasicAuth(
                config('services.airtable.client_id'),
                config('services.airtable.client_secret')
            )
            ->post($this->oauthUrl . '/token', [
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if (!$response->successful()) {
            return false;
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => isset($data['expires_in'])
                ? now()->addSeconds($data['expires_in'])
                : null,
        ]);

        return true;
    }

    // ==========================================
    // BASES & TABLES
    // ==========================================

    public function syncBases(AirtableConnection $connection): Collection
    {
        $response = $this->makeRequest($connection, 'GET', '/bases', [], true);

        $bases = collect($response['bases'] ?? []);

        foreach ($bases as $baseData) {
            AirtableBase::updateOrCreate(
                ['connection_id' => $connection->id, 'base_id' => $baseData['id']],
                [
                    'name' => $baseData['name'],
                    'permission_level' => $baseData['permissionLevel'] ?? null,
                ]
            );
        }

        return $connection->bases()->get();
    }

    public function syncTables(AirtableBase $base): array
    {
        $connection = $base->connection;

        $response = $this->makeRequest(
            $connection,
            'GET',
            "/bases/{$base->base_id}/tables",
            [],
            true
        );

        $tables = $response['tables'] ?? [];

        $base->update([
            'tables' => $tables,
            'tables_synced_at' => now(),
        ]);

        return $tables;
    }

    // ==========================================
    // RECORDS
    // ==========================================

    public function listRecords(
        AirtableConnection $connection,
        string $baseId,
        string $tableIdOrName,
        array $options = []
    ): array {
        $params = array_filter([
            'pageSize' => $options['page_size'] ?? 100,
            'offset' => $options['offset'] ?? null,
            'view' => $options['view'] ?? null,
            'filterByFormula' => $options['filter'] ?? null,
            'sort' => $options['sort'] ?? null,
            'fields' => $options['fields'] ?? null,
        ]);

        return $this->makeRequest(
            $connection,
            'GET',
            "/{$baseId}/{$tableIdOrName}",
            $params
        );
    }

    public function getRecord(
        AirtableConnection $connection,
        string $baseId,
        string $tableIdOrName,
        string $recordId
    ): array {
        return $this->makeRequest(
            $connection,
            'GET',
            "/{$baseId}/{$tableIdOrName}/{$recordId}"
        );
    }

    public function createRecords(
        AirtableConnection $connection,
        string $baseId,
        string $tableIdOrName,
        array $records
    ): array {
        return $this->makeRequest(
            $connection,
            'POST',
            "/{$baseId}/{$tableIdOrName}",
            ['records' => array_map(fn($r) => ['fields' => $r], $records)]
        );
    }

    public function updateRecords(
        AirtableConnection $connection,
        string $baseId,
        string $tableIdOrName,
        array $records
    ): array {
        return $this->makeRequest(
            $connection,
            'PATCH',
            "/{$baseId}/{$tableIdOrName}",
            ['records' => $records]
        );
    }

    public function deleteRecords(
        AirtableConnection $connection,
        string $baseId,
        string $tableIdOrName,
        array $recordIds
    ): array {
        $params = ['records' => $recordIds];

        return $this->makeRequest(
            $connection,
            'DELETE',
            "/{$baseId}/{$tableIdOrName}",
            $params
        );
    }

    // ==========================================
    // SYNC OPERATIONS
    // ==========================================

    public function setupTableSync(
        AirtableBase $base,
        string $tableId,
        string $tableName,
        string $localDataType,
        array $fieldMappings,
        string $direction = 'push'
    ): AirtableTableSync {
        return AirtableTableSync::create([
            'base_id' => $base->id,
            'table_id' => $tableId,
            'table_name' => $tableName,
            'sync_direction' => $direction,
            'local_data_type' => $localDataType,
            'field_mappings' => $fieldMappings,
        ]);
    }

    public function pushRecords(AirtableTableSync $tableSync, array $localRecords): AirtableSyncJob
    {
        $base = $tableSync->base;
        $connection = $base->connection;

        $job = AirtableSyncJob::create([
            'table_sync_id' => $tableSync->id,
            'sync_type' => 'incremental',
            'direction' => 'push',
            'status' => 'pending',
            'triggered_by' => 'manual',
        ]);

        $job->markAsRunning();

        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach (array_chunk($localRecords, 10) as $batch) {
            $toCreate = [];
            $toUpdate = [];

            foreach ($batch as $record) {
                $localId = $record['id'];
                $localType = $tableSync->local_data_type;

                // Map fields
                $airtableFields = $this->mapFieldsToAirtable($record, $tableSync->field_mappings);

                // Check if record exists in Airtable
                $mapping = AirtableRecordMapping::where('table_sync_id', $tableSync->id)
                    ->where('local_type', $localType)
                    ->where('local_id', $localId)
                    ->first();

                if ($mapping) {
                    $toUpdate[] = [
                        'id' => $mapping->airtable_record_id,
                        'fields' => $airtableFields,
                        'local_id' => $localId,
                    ];
                } else {
                    $toCreate[] = [
                        'fields' => $airtableFields,
                        'local_id' => $localId,
                    ];
                }
            }

            // Create new records
            if (!empty($toCreate)) {
                try {
                    $response = $this->createRecords(
                        $connection,
                        $base->base_id,
                        $tableSync->table_id,
                        array_column($toCreate, 'fields')
                    );

                    foreach (($response['records'] ?? []) as $index => $record) {
                        AirtableRecordMapping::create([
                            'table_sync_id' => $tableSync->id,
                            'local_type' => $tableSync->local_data_type,
                            'local_id' => $toCreate[$index]['local_id'],
                            'airtable_record_id' => $record['id'],
                            'last_synced_at' => now(),
                            'sync_hash' => md5(json_encode($toCreate[$index]['fields'])),
                        ]);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $failed += count($toCreate);
                    $errors[] = "Create batch failed: {$e->getMessage()}";
                }
            }

            // Update existing records
            if (!empty($toUpdate)) {
                try {
                    $updatePayload = array_map(fn($r) => [
                        'id' => $r['id'],
                        'fields' => $r['fields'],
                    ], $toUpdate);

                    $this->updateRecords($connection, $base->base_id, $tableSync->table_id, $updatePayload);

                    foreach ($toUpdate as $record) {
                        AirtableRecordMapping::where('airtable_record_id', $record['id'])
                            ->update([
                                'last_synced_at' => now(),
                                'sync_hash' => md5(json_encode($record['fields'])),
                            ]);
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $failed += count($toUpdate);
                    $errors[] = "Update batch failed: {$e->getMessage()}";
                }
            }
        }

        $job->update([
            'records_processed' => $created + $updated + $failed,
            'records_created' => $created,
            'records_updated' => $updated,
            'records_failed' => $failed,
        ]);

        if (empty($errors)) {
            $job->markAsCompleted();
        } else {
            $job->markAsFailed($errors);
        }

        $tableSync->update(['last_synced_at' => now()]);

        return $job->fresh();
    }

    protected function mapFieldsToAirtable(array $record, array $mappings): array
    {
        $result = [];

        foreach ($mappings as $localField => $airtableField) {
            if (isset($record[$localField])) {
                $result[$airtableField] = $record[$localField];
            }
        }

        return $result;
    }

    // ==========================================
    // BUSINESS USE CASES
    // ==========================================

    public function pushOrdersToAirtable(AirtableTableSync $tableSync, Collection $orders): AirtableSyncJob
    {
        $records = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer?->name,
                'customer_email' => $order->customer?->email,
                'total' => $order->total,
                'status' => $order->status,
                'created_at' => $order->created_at->toDateTimeString(),
            ];
        })->toArray();

        return $this->pushRecords($tableSync, $records);
    }

    public function pushTicketsToAirtable(AirtableTableSync $tableSync, Collection $tickets): AirtableSyncJob
    {
        $records = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_code' => $ticket->code,
                'event_name' => $ticket->event?->name,
                'ticket_type' => $ticket->ticketType?->name,
                'attendee_name' => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toDateTimeString(),
            ];
        })->toArray();

        return $this->pushRecords($tableSync, $records);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function makeRequest(
        AirtableConnection $connection,
        string $method,
        string $endpoint,
        array $params = [],
        bool $useMeta = false
    ): array {
        // Check and refresh token if needed
        if ($connection->isTokenExpired()) {
            $this->refreshToken($connection);
            $connection->refresh();
        }

        $baseUrl = $useMeta ? $this->metaApiUrl : $this->apiBaseUrl;
        $url = $baseUrl . $endpoint;

        $request = Http::withToken($connection->access_token)
            ->withHeaders(['Content-Type' => 'application/json']);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $params),
            'POST' => $request->post($url, $params),
            'PATCH' => $request->patch($url, $params),
            'DELETE' => $request->delete($url, $params),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            $error = $response->json('error') ?? [];
            throw new \Exception(
                $error['message'] ?? 'Airtable API request failed',
                $response->status()
            );
        }

        $connection->update(['last_used_at' => now()]);

        return $response->json() ?? [];
    }

    public function getConnection(int $tenantId): ?AirtableConnection
    {
        return AirtableConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }
}
