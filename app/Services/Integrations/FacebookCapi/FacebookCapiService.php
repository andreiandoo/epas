<?php

namespace App\Services\Integrations\FacebookCapi;

use App\Models\Integrations\FacebookCapi\FacebookCapiConnection;
use App\Models\Integrations\FacebookCapi\FacebookCapiEventConfig;
use App\Models\Integrations\FacebookCapi\FacebookCapiEvent;
use App\Models\Integrations\FacebookCapi\FacebookCapiBatch;
use App\Models\Integrations\FacebookCapi\FacebookCapiCustomAudience;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class FacebookCapiService
{
    protected string $apiVersion = 'v18.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    // ==========================================
    // CONNECTION MANAGEMENT
    // ==========================================

    public function createConnection(
        int $tenantId,
        string $pixelId,
        string $accessToken,
        ?string $businessId = null,
        ?string $adAccountId = null,
        bool $testMode = false
    ): FacebookCapiConnection {
        $connection = FacebookCapiConnection::create([
            'tenant_id' => $tenantId,
            'pixel_id' => $pixelId,
            'access_token' => $accessToken,
            'business_id' => $businessId,
            'ad_account_id' => $adAccountId,
            'test_mode' => $testMode,
            'test_event_code' => $testMode ? 'TEST' . random_int(10000, 99999) : null,
            'status' => 'active',
            'enabled_events' => ['Purchase', 'AddToCart', 'Lead', 'CompleteRegistration', 'ViewContent'],
        ]);

        // Verify connection by testing pixel
        if (!$this->testConnection($connection)) {
            $connection->delete();
            throw new \Exception('Invalid Pixel ID or Access Token');
        }

        // Setup default event configs
        $this->setupDefaultEventConfigs($connection);

        return $connection->fresh();
    }

    public function testConnection(FacebookCapiConnection $connection): bool
    {
        try {
            // Send a test event
            $testEvent = $this->buildEvent('PageView', [
                'user_data' => ['client_ip_address' => '127.0.0.1'],
                'event_source_url' => 'https://test.example.com',
            ]);

            $response = $this->sendEvents($connection, [$testEvent], true);

            return isset($response['events_received']) && $response['events_received'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function setupDefaultEventConfigs(FacebookCapiConnection $connection): void
    {
        $defaultConfigs = [
            [
                'event_name' => 'Purchase',
                'trigger_on' => 'order_completed',
                'custom_data_mapping' => [
                    'value' => 'total',
                    'currency' => 'currency',
                    'content_ids' => 'item_ids',
                    'content_type' => 'product',
                    'num_items' => 'item_count',
                ],
                'user_data_mapping' => [
                    'em' => 'customer_email',
                    'ph' => 'customer_phone',
                    'fn' => 'customer_first_name',
                    'ln' => 'customer_last_name',
                ],
            ],
            [
                'event_name' => 'Lead',
                'trigger_on' => 'registration_completed',
                'custom_data_mapping' => [
                    'content_name' => 'event_name',
                ],
                'user_data_mapping' => [
                    'em' => 'email',
                    'fn' => 'first_name',
                    'ln' => 'last_name',
                ],
            ],
            [
                'event_name' => 'CompleteRegistration',
                'trigger_on' => 'ticket_purchased',
                'custom_data_mapping' => [
                    'value' => 'ticket_price',
                    'currency' => 'currency',
                    'content_name' => 'event_name',
                ],
                'user_data_mapping' => [
                    'em' => 'attendee_email',
                    'fn' => 'attendee_first_name',
                    'ln' => 'attendee_last_name',
                ],
            ],
        ];

        foreach ($defaultConfigs as $config) {
            FacebookCapiEventConfig::create([
                'connection_id' => $connection->id,
                'event_name' => $config['event_name'],
                'is_enabled' => true,
                'trigger_on' => $config['trigger_on'],
                'custom_data_mapping' => $config['custom_data_mapping'],
                'user_data_mapping' => $config['user_data_mapping'],
            ]);
        }
    }

    // ==========================================
    // SENDING EVENTS
    // ==========================================

    public function sendEvent(
        FacebookCapiConnection $connection,
        string $eventName,
        array $userData,
        array $customData = [],
        array $options = []
    ): FacebookCapiEvent {
        $eventId = $options['event_id'] ?? $this->generateEventId();

        $event = FacebookCapiEvent::create([
            'connection_id' => $connection->id,
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_time' => now(),
            'event_source_url' => $options['event_source_url'] ?? null,
            'action_source' => $options['action_source'] ?? 'website',
            'user_data' => $this->hashUserData($userData),
            'custom_data' => $customData,
            'correlation_type' => $options['correlation_type'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
            'status' => 'pending',
            'is_test_event' => $connection->isTestMode(),
        ]);

        try {
            $eventPayload = $this->buildEvent($eventName, [
                'event_id' => $eventId,
                'event_time' => $event->event_time->timestamp,
                'event_source_url' => $event->event_source_url,
                'action_source' => $event->action_source,
                'user_data' => $event->user_data,
                'custom_data' => $event->custom_data,
            ]);

            $response = $this->sendEvents($connection, [$eventPayload]);

            $event->markAsSent(
                $response['fbtrace_id'] ?? '',
                $response['events_received'] ?? 0,
                $response['messages'] ?? null
            );

            $connection->update(['last_event_at' => now()]);
        } catch (\Exception $e) {
            $event->markAsFailed($e->getMessage());
        }

        return $event->fresh();
    }

    public function sendEventBatch(
        FacebookCapiConnection $connection,
        array $events
    ): FacebookCapiBatch {
        $batch = FacebookCapiBatch::create([
            'connection_id' => $connection->id,
            'event_count' => count($events),
            'status' => 'pending',
        ]);

        $eventPayloads = [];
        $eventRecords = [];

        foreach ($events as $eventData) {
            $eventId = $eventData['event_id'] ?? $this->generateEventId();

            $eventRecord = FacebookCapiEvent::create([
                'connection_id' => $connection->id,
                'event_id' => $eventId,
                'event_name' => $eventData['event_name'],
                'event_time' => $eventData['event_time'] ?? now(),
                'event_source_url' => $eventData['event_source_url'] ?? null,
                'action_source' => $eventData['action_source'] ?? 'website',
                'user_data' => $this->hashUserData($eventData['user_data'] ?? []),
                'custom_data' => $eventData['custom_data'] ?? [],
                'correlation_type' => $eventData['correlation_type'] ?? null,
                'correlation_id' => $eventData['correlation_id'] ?? null,
                'status' => 'pending',
                'is_test_event' => $connection->isTestMode(),
            ]);

            $eventRecords[] = $eventRecord;

            $eventPayloads[] = $this->buildEvent($eventData['event_name'], [
                'event_id' => $eventId,
                'event_time' => $eventRecord->event_time->timestamp,
                'event_source_url' => $eventRecord->event_source_url,
                'action_source' => $eventRecord->action_source,
                'user_data' => $eventRecord->user_data,
                'custom_data' => $eventRecord->custom_data,
            ]);
        }

        try {
            $response = $this->sendEvents($connection, $eventPayloads);

            $batch->markAsSent(
                $response['fbtrace_id'] ?? '',
                $response['events_received'] ?? 0,
                $response['messages'] ?? null
            );

            foreach ($eventRecords as $record) {
                $record->markAsSent(
                    $response['fbtrace_id'] ?? '',
                    1,
                    null
                );
            }

            $connection->update(['last_event_at' => now()]);
        } catch (\Exception $e) {
            $batch->markAsFailed($e->getMessage());

            foreach ($eventRecords as $record) {
                $record->markAsFailed($e->getMessage());
            }
        }

        return $batch->fresh();
    }

    protected function sendEvents(FacebookCapiConnection $connection, array $events, bool $forceTest = false): array
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/{$connection->pixel_id}/events";

        $payload = [
            'data' => $events,
            'access_token' => $connection->access_token,
        ];

        if ($connection->isTestMode() || $forceTest) {
            $payload['test_event_code'] = $connection->test_event_code ?? 'TEST00000';
        }

        $response = Http::post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json('error') ?? [];
            throw new \Exception(
                $error['message'] ?? 'Facebook CAPI request failed',
                $error['code'] ?? $response->status()
            );
        }

        return $response->json() ?? [];
    }

    protected function buildEvent(string $eventName, array $data): array
    {
        return array_filter([
            'event_name' => $eventName,
            'event_id' => $data['event_id'] ?? $this->generateEventId(),
            'event_time' => $data['event_time'] ?? time(),
            'event_source_url' => $data['event_source_url'] ?? null,
            'action_source' => $data['action_source'] ?? 'website',
            'user_data' => $data['user_data'] ?? [],
            'custom_data' => $data['custom_data'] ?? null,
        ]);
    }

    // ==========================================
    // BUSINESS USE CASES
    // ==========================================

    public function trackPurchase(
        FacebookCapiConnection $connection,
        float $value,
        string $currency,
        array $customerData,
        ?int $orderId = null,
        ?string $eventSourceUrl = null
    ): FacebookCapiEvent {
        return $this->sendEvent($connection, 'Purchase', $customerData, [
            'value' => $value,
            'currency' => $currency,
            'content_type' => 'product',
        ], [
            'correlation_type' => 'order',
            'correlation_id' => $orderId,
            'event_source_url' => $eventSourceUrl,
            'action_source' => 'website',
        ]);
    }

    public function trackTicketPurchase(
        FacebookCapiConnection $connection,
        float $value,
        string $currency,
        array $attendeeData,
        string $eventName,
        ?int $ticketId = null
    ): FacebookCapiEvent {
        return $this->sendEvent($connection, 'CompleteRegistration', $attendeeData, [
            'value' => $value,
            'currency' => $currency,
            'content_name' => $eventName,
            'content_category' => 'Event Ticket',
        ], [
            'correlation_type' => 'ticket',
            'correlation_id' => $ticketId,
            'action_source' => 'website',
        ]);
    }

    public function trackLead(
        FacebookCapiConnection $connection,
        array $leadData,
        ?string $contentName = null,
        ?int $registrationId = null
    ): FacebookCapiEvent {
        $customData = [];
        if ($contentName) {
            $customData['content_name'] = $contentName;
        }

        return $this->sendEvent($connection, 'Lead', $leadData, $customData, [
            'correlation_type' => 'registration',
            'correlation_id' => $registrationId,
            'action_source' => 'website',
        ]);
    }

    public function trackViewContent(
        FacebookCapiConnection $connection,
        array $userData,
        string $contentName,
        ?string $contentCategory = null,
        ?float $value = null,
        ?string $currency = null
    ): FacebookCapiEvent {
        return $this->sendEvent($connection, 'ViewContent', $userData, array_filter([
            'content_name' => $contentName,
            'content_category' => $contentCategory,
            'value' => $value,
            'currency' => $currency,
        ]), [
            'action_source' => 'website',
        ]);
    }

    // ==========================================
    // CUSTOM AUDIENCES
    // ==========================================

    public function createCustomAudience(
        FacebookCapiConnection $connection,
        string $name,
        string $description,
        string $dataSource
    ): FacebookCapiCustomAudience {
        if (!$connection->ad_account_id) {
            throw new \Exception('Ad Account ID required for custom audiences');
        }

        $response = Http::withToken($connection->access_token)
            ->post("{$this->baseUrl}/{$this->apiVersion}/act_{$connection->ad_account_id}/customaudiences", [
                'name' => $name,
                'description' => $description,
                'subtype' => 'CUSTOM',
                'customer_file_source' => 'USER_PROVIDED_ONLY',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create custom audience: ' . $response->body());
        }

        $data = $response->json();

        return FacebookCapiCustomAudience::create([
            'connection_id' => $connection->id,
            'audience_id' => $data['id'],
            'name' => $name,
            'description' => $description,
            'subtype' => 'CUSTOM',
            'data_source' => $dataSource,
        ]);
    }

    public function addUsersToAudience(
        FacebookCapiConnection $connection,
        FacebookCapiCustomAudience $audience,
        array $users
    ): array {
        $schema = ['EMAIL', 'PHONE', 'FN', 'LN'];
        $data = [];

        foreach ($users as $user) {
            $data[] = [
                $this->hashValue($user['email'] ?? ''),
                $this->hashValue($this->normalizePhone($user['phone'] ?? '')),
                $this->hashValue(strtolower($user['first_name'] ?? '')),
                $this->hashValue(strtolower($user['last_name'] ?? '')),
            ];
        }

        $response = Http::withToken($connection->access_token)
            ->post("{$this->baseUrl}/{$this->apiVersion}/{$audience->audience_id}/users", [
                'payload' => [
                    'schema' => $schema,
                    'data' => $data,
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to add users to audience: ' . $response->body());
        }

        $result = $response->json();

        $audience->update([
            'last_synced_at' => now(),
            'approximate_count' => ($audience->approximate_count ?? 0) + ($result['num_received'] ?? 0),
        ]);

        return $result;
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function hashUserData(array $userData): array
    {
        $hashedData = [];

        // Fields that should be hashed
        $hashFields = ['em', 'ph', 'fn', 'ln', 'ct', 'st', 'zp', 'country', 'db', 'ge'];

        foreach ($userData as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (in_array($key, $hashFields)) {
                // Normalize and hash
                $normalized = $this->normalizeValue($key, $value);
                $hashedData[$key] = $this->hashValue($normalized);
            } else {
                // Pass through (e.g., client_ip_address, fbc, fbp)
                $hashedData[$key] = $value;
            }
        }

        return $hashedData;
    }

    protected function normalizeValue(string $field, string $value): string
    {
        $value = strtolower(trim($value));

        switch ($field) {
            case 'em':
                // Email - lowercase, trim
                return $value;

            case 'ph':
                // Phone - digits only, remove leading zeros for country code
                return $this->normalizePhone($value);

            case 'fn':
            case 'ln':
                // Names - lowercase, remove special chars
                return preg_replace('/[^a-z]/', '', $value);

            case 'ct':
                // City - lowercase, no special chars/digits
                return preg_replace('/[^a-z]/', '', $value);

            case 'st':
                // State - 2-letter code
                return substr($value, 0, 2);

            case 'zp':
                // Zip - first 5 digits for US
                return substr(preg_replace('/[^0-9]/', '', $value), 0, 5);

            case 'country':
                // Country - 2-letter ISO code
                return substr($value, 0, 2);

            case 'db':
                // Date of birth - YYYYMMDD format
                return preg_replace('/[^0-9]/', '', $value);

            case 'ge':
                // Gender - m or f
                return substr($value, 0, 1);

            default:
                return $value;
        }
    }

    protected function normalizePhone(string $phone): string
    {
        // Remove all non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        return ltrim($phone, '0');
    }

    protected function hashValue(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        return hash('sha256', $value);
    }

    protected function generateEventId(): string
    {
        return uniqid('evt_', true) . '_' . bin2hex(random_bytes(8));
    }

    public function getConnection(int $tenantId): ?FacebookCapiConnection
    {
        return FacebookCapiConnection::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    public function getConnections(int $tenantId): Collection
    {
        return FacebookCapiConnection::where('tenant_id', $tenantId)->get();
    }
}
