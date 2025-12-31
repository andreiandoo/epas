<?php

namespace App\Services\Integrations\TikTokAds;

use App\Models\Integrations\TikTokAds\TikTokAdsConnection;
use App\Models\Integrations\TikTokAds\TikTokAdsEvent;
use App\Models\Integrations\TikTokAds\TikTokAdsBatch;
use App\Models\Integrations\TikTokAds\TikTokAdsAudience;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokAdsService
{
    protected string $eventsApiUrl = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
    protected string $audienceApiUrl = 'https://business-api.tiktok.com/open_api/v1.3/dmp/custom_audience/';
    protected string $oauthUrl = 'https://business-api.tiktok.com/open_api/v1.3/oauth2/';

    /**
     * Standard TikTok event names
     */
    const EVENT_VIEW_CONTENT = 'ViewContent';
    const EVENT_ADD_TO_CART = 'AddToCart';
    const EVENT_INITIATE_CHECKOUT = 'InitiateCheckout';
    const EVENT_COMPLETE_PAYMENT = 'CompletePayment';
    const EVENT_PLACE_ORDER = 'PlaceAnOrder';
    const EVENT_COMPLETE_REGISTRATION = 'CompleteRegistration';
    const EVENT_CONTACT = 'Contact';
    const EVENT_SUBSCRIBE = 'Subscribe';
    const EVENT_SUBMIT_FORM = 'SubmitForm';

    /**
     * Create a new connection for a tenant
     */
    public function createConnection(
        int $tenantId,
        string $pixelId,
        string $accessToken,
        ?string $advertiserId = null,
        bool $testMode = false
    ): TikTokAdsConnection {
        $connection = TikTokAdsConnection::create([
            'tenant_id' => $tenantId,
            'pixel_id' => $pixelId,
            'access_token' => $accessToken,
            'advertiser_id' => $advertiserId,
            'test_mode' => $testMode,
            'test_event_code' => $testMode ? 'TEST' . random_int(10000, 99999) : null,
            'status' => 'active',
            'enabled_events' => [
                self::EVENT_COMPLETE_PAYMENT,
                self::EVENT_ADD_TO_CART,
                self::EVENT_COMPLETE_REGISTRATION,
                self::EVENT_VIEW_CONTENT,
            ],
        ]);

        // Verify connection
        if (!$this->testConnection($connection)) {
            $connection->delete();
            throw new \Exception('Invalid Pixel ID or Access Token');
        }

        // Setup default event configs
        $this->setupDefaultEventConfigs($connection);

        return $connection->fresh();
    }

    /**
     * Test connection to TikTok Events API
     */
    public function testConnection(TikTokAdsConnection $connection): bool
    {
        try {
            // Send a test event to verify credentials
            $response = Http::withHeaders([
                'Access-Token' => $connection->access_token,
                'Content-Type' => 'application/json',
            ])->post($this->eventsApiUrl, [
                'pixel_code' => $connection->pixel_id,
                'event' => 'Test',
                'event_id' => 'test_' . time(),
                'timestamp' => now()->toIso8601String(),
                'test_event_code' => 'TEST00000',
            ]);

            // TikTok returns success even for test events
            return $response->successful() || $response->status() === 200;
        } catch (\Exception $e) {
            Log::error('TikTok connection test failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Setup default event configurations
     */
    protected function setupDefaultEventConfigs(TikTokAdsConnection $connection): void
    {
        $defaultConfigs = [
            [
                'event_name' => self::EVENT_COMPLETE_PAYMENT,
                'trigger_on' => 'order_completed',
                'is_enabled' => true,
            ],
            [
                'event_name' => self::EVENT_ADD_TO_CART,
                'trigger_on' => 'add_to_cart',
                'is_enabled' => true,
            ],
            [
                'event_name' => self::EVENT_INITIATE_CHECKOUT,
                'trigger_on' => 'begin_checkout',
                'is_enabled' => true,
            ],
            [
                'event_name' => self::EVENT_COMPLETE_REGISTRATION,
                'trigger_on' => 'registration_completed',
                'is_enabled' => true,
            ],
            [
                'event_name' => self::EVENT_VIEW_CONTENT,
                'trigger_on' => 'page_view',
                'is_enabled' => true,
            ],
        ];

        foreach ($defaultConfigs as $config) {
            $connection->eventConfigs()->create($config);
        }
    }

    /**
     * Send a single event to TikTok
     */
    public function sendEvent(
        TikTokAdsConnection $connection,
        string $eventName,
        array $userData = [],
        array $properties = [],
        array $options = []
    ): TikTokAdsEvent {
        $eventId = $options['event_id'] ?? $this->generateEventId();

        $event = TikTokAdsEvent::create([
            'connection_id' => $connection->id,
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_time' => $options['event_time'] ?? now(),
            'event_source_url' => $options['event_source_url'] ?? null,
            'user_data' => $this->hashUserData($userData),
            'properties' => $properties,
            'contents' => $options['contents'] ?? null,
            'ttclid' => $options['ttclid'] ?? null,
            'ttp' => $options['ttp'] ?? null,
            'status' => 'pending',
            'correlation_type' => $options['correlation_type'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
            'is_test_event' => $connection->isTestMode(),
        ]);

        try {
            $payload = $this->buildEventPayload($connection, $event);
            $response = $this->sendToApi($connection, [$payload]);

            $event->markAsSent(
                $response['data']['events_received'] ?? 0,
                $response['message'] ?? null
            );

            $connection->update(['last_event_at' => now()]);
        } catch (\Exception $e) {
            $event->markAsFailed($e->getMessage());
        }

        return $event->fresh();
    }

    /**
     * Send batch of events
     */
    public function sendEventBatch(
        TikTokAdsConnection $connection,
        array $events
    ): TikTokAdsBatch {
        $batch = TikTokAdsBatch::create([
            'connection_id' => $connection->id,
            'event_count' => count($events),
            'status' => 'pending',
        ]);

        $payloads = [];
        $eventRecords = [];

        foreach ($events as $eventData) {
            $eventId = $eventData['event_id'] ?? $this->generateEventId();

            $record = TikTokAdsEvent::create([
                'connection_id' => $connection->id,
                'event_id' => $eventId,
                'event_name' => $eventData['event_name'],
                'event_time' => $eventData['event_time'] ?? now(),
                'event_source_url' => $eventData['event_source_url'] ?? null,
                'user_data' => isset($eventData['user_data']) ? $this->hashUserData($eventData['user_data']) : null,
                'properties' => $eventData['properties'] ?? null,
                'contents' => $eventData['contents'] ?? null,
                'ttclid' => $eventData['ttclid'] ?? null,
                'ttp' => $eventData['ttp'] ?? null,
                'status' => 'pending',
                'correlation_type' => $eventData['correlation_type'] ?? null,
                'correlation_id' => $eventData['correlation_id'] ?? null,
                'is_test_event' => $connection->isTestMode(),
            ]);

            $eventRecords[] = $record;
            $payloads[] = $this->buildEventPayload($connection, $record);
        }

        try {
            $response = $this->sendToApi($connection, $payloads);

            $eventsReceived = $response['data']['events_received'] ?? 0;

            $batch->update([
                'status' => 'completed',
                'events_received' => $eventsReceived,
                'messages' => $response['message'] ?? null,
                'completed_at' => now(),
            ]);

            foreach ($eventRecords as $record) {
                $record->markAsSent($eventsReceived, $response['message'] ?? null);
            }

            $connection->update(['last_event_at' => now()]);
        } catch (\Exception $e) {
            $batch->update([
                'status' => 'failed',
                'messages' => $e->getMessage(),
            ]);

            foreach ($eventRecords as $record) {
                $record->markAsFailed($e->getMessage());
            }
        }

        return $batch->fresh();
    }

    /**
     * Build event payload for TikTok API
     */
    protected function buildEventPayload(TikTokAdsConnection $connection, TikTokAdsEvent $event): array
    {
        $payload = [
            'pixel_code' => $connection->pixel_id,
            'event' => $event->event_name,
            'event_id' => $event->event_id,
            'timestamp' => $event->event_time->toIso8601String(),
        ];

        if ($event->event_source_url) {
            $payload['page'] = [
                'url' => $event->event_source_url,
            ];
        }

        // User data
        if ($event->user_data) {
            $payload['user'] = $event->user_data;
        }

        // Click ID
        if ($event->ttclid) {
            $payload['user']['ttclid'] = $event->ttclid;
        }

        // TikTok cookie
        if ($event->ttp) {
            $payload['user']['ttp'] = $event->ttp;
        }

        // Properties (value, currency, etc.)
        if ($event->properties) {
            $payload['properties'] = $event->properties;
        }

        // Content items
        if ($event->contents) {
            $payload['contents'] = $event->contents;
        }

        // Test mode
        if ($connection->isTestMode()) {
            $payload['test_event_code'] = $connection->test_event_code ?? 'TEST00000';
        }

        return $payload;
    }

    /**
     * Send events to TikTok Events API
     */
    protected function sendToApi(TikTokAdsConnection $connection, array $events): array
    {
        $response = Http::withHeaders([
            'Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->post($this->eventsApiUrl, [
            'data' => $events,
        ]);

        $result = $response->json();

        if (!$response->successful() || ($result['code'] ?? 0) !== 0) {
            $errorMsg = $result['message'] ?? 'TikTok Events API request failed';
            throw new \Exception($errorMsg);
        }

        return $result;
    }

    /**
     * Track purchase event
     */
    public function trackPurchase(
        TikTokAdsConnection $connection,
        float $value,
        string $currency,
        array $customerData,
        ?string $orderId = null,
        ?array $contents = null,
        ?string $ttclid = null
    ): TikTokAdsEvent {
        $properties = [
            'value' => $value,
            'currency' => $currency,
        ];

        if ($orderId) {
            $properties['order_id'] = $orderId;
        }

        return $this->sendEvent($connection, self::EVENT_COMPLETE_PAYMENT, $customerData, $properties, [
            'contents' => $contents,
            'ttclid' => $ttclid,
            'correlation_type' => 'order',
            'correlation_id' => $orderId,
        ]);
    }

    /**
     * Track add to cart
     */
    public function trackAddToCart(
        TikTokAdsConnection $connection,
        float $value,
        string $currency,
        array $contents,
        array $customerData = [],
        ?string $ttclid = null
    ): TikTokAdsEvent {
        return $this->sendEvent($connection, self::EVENT_ADD_TO_CART, $customerData, [
            'value' => $value,
            'currency' => $currency,
        ], [
            'contents' => $contents,
            'ttclid' => $ttclid,
        ]);
    }

    /**
     * Track registration
     */
    public function trackRegistration(
        TikTokAdsConnection $connection,
        array $userData,
        ?string $ttclid = null
    ): TikTokAdsEvent {
        return $this->sendEvent($connection, self::EVENT_COMPLETE_REGISTRATION, $userData, [], [
            'ttclid' => $ttclid,
            'correlation_type' => 'registration',
        ]);
    }

    /**
     * Hash user data for privacy
     */
    protected function hashUserData(array $userData): array
    {
        $hashed = [];
        $hashFields = ['email', 'phone', 'external_id'];

        foreach ($userData as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (in_array($key, $hashFields)) {
                $normalized = $this->normalizeValue($key, $value);
                $hashed[$key] = hash('sha256', $normalized);
            } else {
                $hashed[$key] = $value;
            }
        }

        return $hashed;
    }

    /**
     * Normalize value before hashing
     */
    protected function normalizeValue(string $key, string $value): string
    {
        $value = trim(strtolower($value));

        if ($key === 'phone') {
            $value = preg_replace('/[^0-9+]/', '', $value);
        }

        return $value;
    }

    /**
     * Generate unique event ID
     */
    protected function generateEventId(): string
    {
        return 'tt_' . Str::uuid()->toString();
    }

    /**
     * Create custom audience
     */
    public function createAudience(
        TikTokAdsConnection $connection,
        string $name,
        string $audienceType = 'CUSTOMER_FILE'
    ): TikTokAdsAudience {
        if (!$connection->advertiser_id) {
            throw new \Exception('Advertiser ID required for audience creation');
        }

        $response = Http::withHeaders([
            'Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->post($this->audienceApiUrl . 'create/', [
            'advertiser_id' => $connection->advertiser_id,
            'custom_audience_name' => $name,
            'file_paths' => [],
            'calculate_type' => 'INCLUDE',
        ]);

        $result = $response->json();

        if (!$response->successful() || ($result['code'] ?? 0) !== 0) {
            throw new \Exception($result['message'] ?? 'Failed to create audience');
        }

        return TikTokAdsAudience::create([
            'connection_id' => $connection->id,
            'audience_id' => $result['data']['custom_audience_id'],
            'name' => $name,
            'audience_type' => $audienceType,
        ]);
    }

    /**
     * Add users to audience
     */
    public function addUsersToAudience(
        TikTokAdsConnection $connection,
        TikTokAdsAudience $audience,
        array $users,
        string $idType = 'EMAIL_SHA256'
    ): array {
        $hashedIds = [];

        foreach ($users as $user) {
            if ($idType === 'EMAIL_SHA256' && !empty($user['email'])) {
                $hashedIds[] = hash('sha256', $this->normalizeValue('email', $user['email']));
            } elseif ($idType === 'PHONE_SHA256' && !empty($user['phone'])) {
                $hashedIds[] = hash('sha256', $this->normalizeValue('phone', $user['phone']));
            }
        }

        $response = Http::withHeaders([
            'Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->post($this->audienceApiUrl . 'upload/', [
            'advertiser_id' => $connection->advertiser_id,
            'custom_audience_id' => $audience->audience_id,
            'id_type' => $idType,
            'id_list' => $hashedIds,
        ]);

        $result = $response->json();

        if (!$response->successful() || ($result['code'] ?? 0) !== 0) {
            throw new \Exception($result['message'] ?? 'Failed to add users to audience');
        }

        $audience->update(['last_synced_at' => now()]);

        return $result;
    }
}
