<?php

namespace App\Services\AudienceTargeting\Providers;

use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TikTokAudienceProvider implements AudienceProviderInterface
{
    protected const BASE_URL = 'https://business-api.tiktok.com/open_api/v1.3';

    protected ?string $advertiserId = null;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $settings = Setting::first();

        $this->advertiserId = $settings?->tiktok_ads_advertiser_id;
        $this->accessToken = $settings?->tiktok_ads_access_token;
    }

    public function getName(): string
    {
        return 'TikTok Ads';
    }

    public function getPlatform(): string
    {
        return AudienceExport::PLATFORM_TIKTOK;
    }

    public function isConfigured(): bool
    {
        return !empty($this->advertiserId) && !empty($this->accessToken);
    }

    public function createCustomAudience(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): array {
        $this->ensureConfigured();

        // Step 1: Create custom audience
        $response = $this->apiRequest('POST', 'dmp/custom_audience/create/', [
            'advertiser_id' => $this->advertiserId,
            'custom_audience_name' => $audienceName,
            'is_auto_refresh' => false,
            'retention_in_days' => 365,
            'calculate_type' => 'INCLUDE',
        ]);

        if (!$response['success']) {
            throw new \RuntimeException(
                'Failed to create TikTok audience: ' . ($response['error'] ?? 'Unknown error')
            );
        }

        $audienceId = $response['data']['custom_audience_id'] ?? null;

        if (!$audienceId) {
            throw new \RuntimeException('Audience created but no ID returned');
        }

        // Step 2: Upload customer file
        $customers = $segment->customers()->with('customer')->get()->pluck('customer');
        $result = $this->uploadCustomers($audienceId, $customers);

        return [
            'external_id' => $audienceId,
            'name' => $audienceName,
            'matched_count' => $result['matched_count'] ?? null,
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

        if (empty($preparedData['file_paths'])) {
            return ['matched_count' => 0];
        }

        // TikTok requires uploading a file first, then associating with audience
        // For simplicity, we'll use the direct data upload method

        $response = $this->apiRequest('POST', 'dmp/custom_audience/file/upload/', [
            'advertiser_id' => $this->advertiserId,
            'custom_audience_id' => $audienceId,
            'file_signature' => $preparedData['file_signature'],
            'calculate_type' => 'INCLUDE',
            'id_type' => 'EMAIL_SHA256', // Primary identifier type
        ], $preparedData['file_content']);

        if (!$response['success']) {
            Log::warning('TikTok audience upload failed', [
                'error' => $response['error'],
            ]);
            return ['matched_count' => 0];
        }

        return [
            'matched_count' => count($customers),
        ];
    }

    public function deleteCustomAudience(string $externalAudienceId): bool
    {
        $this->ensureConfigured();

        $response = $this->apiRequest('POST', 'dmp/custom_audience/delete/', [
            'advertiser_id' => $this->advertiserId,
            'custom_audience_ids' => [$externalAudienceId],
        ]);

        return $response['success'];
    }

    public function createLookalikeAudience(
        string $sourceAudienceId,
        string $audienceName,
        array $options = []
    ): array {
        $this->ensureConfigured();

        $lookalikeRatio = $options['ratio'] ?? '0.01'; // 1% by default
        $placements = $options['placements'] ?? ['PLACEMENT_TIKTOK'];

        $response = $this->apiRequest('POST', 'dmp/lookalike/create/', [
            'advertiser_id' => $this->advertiserId,
            'lookalike_audience_name' => $audienceName,
            'custom_audience_ids' => [$sourceAudienceId],
            'lookalike_ratio' => $lookalikeRatio,
            'placements' => $placements,
            'audience_type' => 'SIMILAR',
        ]);

        if (!$response['success']) {
            throw new \RuntimeException(
                'Failed to create lookalike audience: ' . ($response['error'] ?? 'Unknown error')
            );
        }

        return [
            'external_id' => $response['data']['lookalike_audience_id'] ?? '',
            'name' => $audienceName,
        ];
    }

    public function getAudienceDetails(string $externalAudienceId): ?array
    {
        $this->ensureConfigured();

        $response = $this->apiRequest('GET', 'dmp/custom_audience/get/', [
            'advertiser_id' => $this->advertiserId,
            'custom_audience_ids' => json_encode([$externalAudienceId]),
        ]);

        if (!$response['success'] || empty($response['data']['custom_audiences'])) {
            return null;
        }

        return $response['data']['custom_audiences'][0] ?? null;
    }

    public function prepareCustomerData(Collection $customers): array
    {
        // TikTok accepts multiple identifier types
        // We'll prepare both email and phone hashes
        $lines = [];

        foreach ($customers as $customer) {
            if (!$customer || !$customer->email) {
                continue;
            }

            // Each line contains one identifier
            $lines[] = $this->hashForTikTok($customer->email);

            if ($customer->phone) {
                $lines[] = $this->hashForTikTok($this->normalizePhone($customer->phone));
            }
        }

        // Create file content (one hash per line)
        $fileContent = implode("\n", $lines);
        $fileSignature = hash('md5', $fileContent);

        return [
            'file_content' => $fileContent,
            'file_signature' => $fileSignature,
            'file_paths' => $lines, // For compatibility
        ];
    }

    /**
     * Hash value for TikTok (SHA256)
     */
    protected function hashForTikTok(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // TikTok requires lowercase, trimmed, SHA256 hashed values
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Normalize phone number for TikTok (E.164 format)
     */
    protected function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^\d]/', '', $phone);

        // Add country code if not present
        if (strlen($phone) < 11) {
            $phone = '40' . ltrim($phone, '0'); // Default to Romania
        }

        return $phone;
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['advertiser_id'] ?? $this->advertiserId)) {
            $errors['advertiser_id'] = 'TikTok Advertiser ID is required';
        }

        if (empty($settings['access_token'] ?? $this->accessToken)) {
            $errors['access_token'] = 'TikTok Access Token is required';
        }

        return $errors;
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('TikTok Ads provider is not configured');
        }
    }

    protected function apiRequest(
        string $method,
        string $endpoint,
        array $params,
        ?string $fileContent = null
    ): array {
        $url = self::BASE_URL . '/' . $endpoint;

        $request = Http::withHeaders([
            'Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ]);

        if ($method === 'GET') {
            $response = $request->get($url, $params);
        } else {
            if ($fileContent) {
                // File upload requires multipart
                $response = Http::withHeaders([
                    'Access-Token' => $this->accessToken,
                ])->attach(
                    'file',
                    $fileContent,
                    'audience.txt'
                )->post($url, $params);
            } else {
                $response = $request->post($url, $params);
            }
        }

        $data = $response->json();

        if (!$response->successful() || ($data['code'] ?? 0) !== 0) {
            return [
                'success' => false,
                'error' => $data['message'] ?? 'Unknown error',
            ];
        }

        return [
            'success' => true,
            'data' => $data['data'] ?? [],
        ];
    }

    /**
     * Send TikTok Events API event
     */
    public function sendEvent(
        string $pixelId,
        string $eventName,
        array $userData,
        array $properties = []
    ): bool {
        $this->ensureConfigured();

        $eventData = [
            'pixel_code' => $pixelId,
            'event' => $eventName,
            'event_id' => uniqid('ev_'),
            'timestamp' => (string) time(),
            'context' => [
                'user' => $this->prepareUserContext($userData),
            ],
            'properties' => $properties,
        ];

        $response = $this->apiRequest('POST', 'pixel/track/', [
            'pixel_code' => $pixelId,
            'event' => json_encode([$eventData]),
        ]);

        return $response['success'];
    }

    protected function prepareUserContext(array $userData): array
    {
        $context = [];

        if (!empty($userData['email'])) {
            $context['email'] = $this->hashForTikTok($userData['email']);
        }

        if (!empty($userData['phone'])) {
            $context['phone'] = $this->hashForTikTok($this->normalizePhone($userData['phone']));
        }

        return $context;
    }
}
