<?php

namespace App\Services\AudienceTargeting\Providers;

use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAudienceProvider implements AudienceProviderInterface
{
    protected const API_VERSION = 'v18.0';
    protected const BASE_URL = 'https://graph.facebook.com';

    protected ?string $appId = null;
    protected ?string $appSecret = null;
    protected ?string $accessToken = null;
    protected ?string $adAccountId = null;

    public function __construct()
    {
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $settings = Setting::first();

        $this->appId = $settings?->facebook_app_id;
        $this->appSecret = $settings?->facebook_app_secret;
        $this->accessToken = $settings?->facebook_access_token;
        // Ad account ID would typically be stored per-tenant or in settings
    }

    public function getName(): string
    {
        return 'Meta (Facebook/Instagram)';
    }

    public function getPlatform(): string
    {
        return AudienceExport::PLATFORM_META;
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->appId);
    }

    public function createCustomAudience(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): array {
        $this->ensureConfigured();

        // Get ad account ID from tenant settings or segment's tenant
        $adAccountId = $this->getAdAccountId($segment->tenant_id);

        // Step 1: Create the audience container
        $response = Http::post($this->buildUrl("act_{$adAccountId}/customaudiences"), [
            'access_token' => $this->accessToken,
            'name' => $audienceName,
            'description' => $description ?? "Audience from segment: {$segment->name}",
            'subtype' => 'CUSTOM',
            'customer_file_source' => 'USER_PROVIDED_ONLY',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Failed to create Meta audience: ' . $response->json('error.message', 'Unknown error')
            );
        }

        $audienceId = $response->json('id');

        // Step 2: Upload customer data
        $customers = $segment->customers()->with('customer')->get()->pluck('customer');
        $result = $this->uploadCustomers($audienceId, $customers);

        return [
            'external_id' => $audienceId,
            'name' => $audienceName,
            'matched_count' => $result['num_received'] ?? null,
        ];
    }

    public function updateCustomAudience(
        string $externalAudienceId,
        Collection $customers
    ): array {
        $this->ensureConfigured();

        return $this->uploadCustomers($externalAudienceId, $customers);
    }

    protected function uploadCustomers(string $audienceId, Collection $customers): array
    {
        $preparedData = $this->prepareCustomerData($customers);

        if (empty($preparedData['data'])) {
            return ['num_received' => 0];
        }

        // Meta accepts batches of up to 10,000 records
        $batches = array_chunk($preparedData['data'], 10000);
        $totalReceived = 0;

        foreach ($batches as $index => $batch) {
            $payload = [
                'access_token' => $this->accessToken,
                'payload' => json_encode([
                    'schema' => $preparedData['schema'],
                    'data' => $batch,
                ]),
            ];

            // For first batch, use POST. For subsequent, use POST with session
            $response = Http::post($this->buildUrl("{$audienceId}/users"), $payload);

            if (!$response->successful()) {
                Log::warning('Meta audience upload batch failed', [
                    'batch' => $index,
                    'error' => $response->json('error'),
                ]);
                continue;
            }

            $totalReceived += $response->json('num_received', 0);
        }

        return ['matched_count' => $totalReceived];
    }

    public function deleteCustomAudience(string $externalAudienceId): bool
    {
        $this->ensureConfigured();

        $response = Http::delete($this->buildUrl($externalAudienceId), [
            'access_token' => $this->accessToken,
        ]);

        return $response->successful();
    }

    public function createLookalikeAudience(
        string $sourceAudienceId,
        string $audienceName,
        array $options = []
    ): array {
        $this->ensureConfigured();

        $adAccountId = $options['ad_account_id'] ?? $this->adAccountId;
        $ratio = $options['ratio'] ?? 0.01; // Default 1% lookalike
        $country = $options['country'] ?? 'RO'; // Default Romania

        $response = Http::post($this->buildUrl("act_{$adAccountId}/customaudiences"), [
            'access_token' => $this->accessToken,
            'name' => $audienceName,
            'subtype' => 'LOOKALIKE',
            'origin_audience_id' => $sourceAudienceId,
            'lookalike_spec' => json_encode([
                'type' => 'similarity',
                'ratio' => $ratio,
                'country' => $country,
            ]),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Failed to create lookalike audience: ' . $response->json('error.message', 'Unknown error')
            );
        }

        return [
            'external_id' => $response->json('id'),
            'name' => $audienceName,
        ];
    }

    public function getAudienceDetails(string $externalAudienceId): ?array
    {
        $this->ensureConfigured();

        $response = Http::get($this->buildUrl($externalAudienceId), [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,approximate_count,delivery_status,operation_status',
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function prepareCustomerData(Collection $customers): array
    {
        // Meta requires specific schema for customer matching
        // Supported keys: EMAIL, PHONE, FN, LN, CT, ST, COUNTRY, ZIP, DOBY, DOBM, DOBD
        $schema = ['EMAIL', 'PHONE', 'FN', 'LN', 'CT', 'COUNTRY'];
        $data = [];

        foreach ($customers as $customer) {
            if (!$customer || !$customer->email) {
                continue;
            }

            $row = [
                $this->hashForMeta($customer->email), // EMAIL
                $this->hashForMeta($this->normalizePhone($customer->phone)), // PHONE
                $this->hashForMeta(strtolower(trim($customer->first_name ?? ''))), // FN
                $this->hashForMeta(strtolower(trim($customer->last_name ?? ''))), // LN
                $this->hashForMeta(strtolower(trim($customer->city ?? ''))), // CT
                $this->hashForMeta(strtolower(trim($customer->country ?? ''))), // COUNTRY
            ];

            $data[] = $row;
        }

        return [
            'schema' => $schema,
            'data' => $data,
        ];
    }

    /**
     * Hash value for Meta Custom Audiences (SHA256)
     */
    protected function hashForMeta(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Meta requires lowercase, trimmed, SHA256 hashed values
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Normalize phone number for Meta
     */
    protected function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure it starts with country code
        if (!str_starts_with($phone, '+')) {
            // Default to Romania if no country code
            $phone = '+40' . ltrim($phone, '0');
        }

        return $phone;
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['access_token'] ?? $this->accessToken)) {
            $errors['access_token'] = 'Facebook access token is required';
        }

        if (empty($settings['ad_account_id'])) {
            $errors['ad_account_id'] = 'Ad Account ID is required';
        }

        return $errors;
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Meta Audience provider is not configured');
        }
    }

    protected function buildUrl(string $endpoint): string
    {
        return self::BASE_URL . '/' . self::API_VERSION . '/' . $endpoint;
    }

    protected function getAdAccountId(int $tenantId): string
    {
        // In production, this would fetch from tenant settings
        // For now, use the global setting or throw
        if ($this->adAccountId) {
            return $this->adAccountId;
        }

        throw new \RuntimeException('Ad Account ID not configured for tenant');
    }

    /**
     * Send server-side conversion event
     */
    public function sendConversionEvent(
        string $pixelId,
        string $eventName,
        array $userData,
        array $customData = []
    ): bool {
        $this->ensureConfigured();

        $eventData = [
            'event_name' => $eventName,
            'event_time' => time(),
            'user_data' => $this->prepareUserDataForConversion($userData),
            'custom_data' => $customData,
            'action_source' => 'website',
        ];

        $response = Http::post($this->buildUrl("{$pixelId}/events"), [
            'access_token' => $this->accessToken,
            'data' => json_encode([$eventData]),
        ]);

        return $response->successful();
    }

    protected function prepareUserDataForConversion(array $userData): array
    {
        $prepared = [];

        if (!empty($userData['email'])) {
            $prepared['em'] = [$this->hashForMeta($userData['email'])];
        }

        if (!empty($userData['phone'])) {
            $prepared['ph'] = [$this->hashForMeta($this->normalizePhone($userData['phone']))];
        }

        if (!empty($userData['first_name'])) {
            $prepared['fn'] = [$this->hashForMeta($userData['first_name'])];
        }

        if (!empty($userData['last_name'])) {
            $prepared['ln'] = [$this->hashForMeta($userData['last_name'])];
        }

        if (!empty($userData['city'])) {
            $prepared['ct'] = [$this->hashForMeta($userData['city'])];
        }

        if (!empty($userData['country'])) {
            $prepared['country'] = [$this->hashForMeta($userData['country'])];
        }

        return $prepared;
    }
}
