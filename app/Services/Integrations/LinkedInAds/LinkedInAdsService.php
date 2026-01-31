<?php

namespace App\Services\Integrations\LinkedInAds;

use App\Models\Integrations\LinkedInAds\LinkedInAdsConnection;
use App\Models\Integrations\LinkedInAds\LinkedInAdsConversion;
use App\Models\Integrations\LinkedInAds\LinkedInAdsConversionRule;
use App\Models\Integrations\LinkedInAds\LinkedInAdsAudience;
use App\Models\Integrations\LinkedInAds\LinkedInAdsBatch;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkedInAdsService
{
    protected string $apiVersion = '202401';
    protected string $baseUrl = 'https://api.linkedin.com/rest';
    protected string $oauthUrl = 'https://www.linkedin.com/oauth/v2';

    /**
     * LinkedIn conversion types
     */
    const TYPE_PURCHASE = 'PURCHASE';
    const TYPE_ADD_TO_CART = 'ADD_TO_CART';
    const TYPE_LEAD = 'LEAD';
    const TYPE_SIGN_UP = 'SIGN_UP';
    const TYPE_KEY_PAGE_VIEW = 'KEY_PAGE_VIEW';
    const TYPE_START_CHECKOUT = 'START_CHECKOUT';
    const TYPE_OTHER = 'OTHER';

    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl(int $tenantId, string $redirectUri): string
    {
        $settings = Setting::current();
        $clientId = $settings->linkedin_ads_client_id;

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => base64_encode(json_encode(['tenant_id' => $tenantId])),
            'scope' => 'r_ads rw_ads r_ads_reporting r_organization_social',
        ]);

        return "{$this->oauthUrl}/authorization?{$params}";
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code, string $redirectUri): array
    {
        $settings = Setting::current();

        $response = Http::asForm()->post("{$this->oauthUrl}/accessToken", [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $settings->linkedin_ads_client_id,
            'client_secret' => $settings->linkedin_ads_client_secret,
            'redirect_uri' => $redirectUri,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken(LinkedInAdsConnection $connection): string
    {
        $settings = Setting::current();

        $response = Http::asForm()->post("{$this->oauthUrl}/accessToken", [
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
            'client_id' => $settings->linkedin_ads_client_id,
            'client_secret' => $settings->linkedin_ads_client_secret,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to refresh token: ' . $response->body());
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => now()->addSeconds($data['expires_in'] - 60),
        ]);

        return $data['access_token'];
    }

    /**
     * Get valid access token
     */
    protected function getAccessToken(LinkedInAdsConnection $connection): string
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isFuture()) {
            return $connection->access_token;
        }

        if ($connection->refresh_token) {
            return $this->refreshAccessToken($connection);
        }

        return $connection->access_token;
    }

    /**
     * Create a new connection
     */
    public function createConnection(
        int $tenantId,
        string $adAccountId,
        string $accessToken,
        ?string $refreshToken = null,
        int $expiresIn = 3600
    ): LinkedInAdsConnection {
        $connection = LinkedInAdsConnection::create([
            'tenant_id' => $tenantId,
            'ad_account_id' => $adAccountId,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_expires_at' => now()->addSeconds($expiresIn - 60),
            'status' => 'active',
            'enabled_conversions' => [
                self::TYPE_PURCHASE,
                self::TYPE_LEAD,
                self::TYPE_SIGN_UP,
            ],
        ]);

        // Verify connection and fetch conversion rules
        if (!$this->testConnection($connection)) {
            $connection->delete();
            throw new \Exception('Invalid credentials or insufficient permissions');
        }

        try {
            $this->syncConversionRules($connection);
        } catch (\Exception $e) {
            Log::warning('Failed to sync LinkedIn conversion rules', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $connection->fresh();
    }

    /**
     * Test connection
     */
    public function testConnection(LinkedInAdsConnection $connection): bool
    {
        try {
            $accessToken = $this->getAccessToken($connection);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'LinkedIn-Version' => $this->apiVersion,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->get("{$this->baseUrl}/adAccounts/{$connection->ad_account_id}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('LinkedIn connection test failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync conversion rules from LinkedIn
     */
    public function syncConversionRules(LinkedInAdsConnection $connection): array
    {
        $accessToken = $this->getAccessToken($connection);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => $this->apiVersion,
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->get("{$this->baseUrl}/conversions", [
            'q' => 'account',
            'account' => "urn:li:sponsoredAccount:{$connection->ad_account_id}",
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch conversion rules: ' . $response->body());
        }

        $rules = [];
        $elements = $response->json('elements') ?? [];

        foreach ($elements as $element) {
            $ruleId = basename($element['id'] ?? '');

            $rule = LinkedInAdsConversionRule::updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'conversion_rule_id' => $ruleId,
                ],
                [
                    'name' => $element['name'] ?? 'Unknown',
                    'conversion_type' => $element['type'] ?? self::TYPE_OTHER,
                    'attribution_type' => $element['attributionType'] ?? 'LAST_TOUCH_BY_CAMPAIGN',
                ]
            );
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * Create conversion rule in LinkedIn
     */
    public function createConversionRule(
        LinkedInAdsConnection $connection,
        string $name,
        string $type = self::TYPE_PURCHASE,
        ?float $defaultValue = null,
        string $defaultCurrency = 'EUR'
    ): LinkedInAdsConversionRule {
        $accessToken = $this->getAccessToken($connection);

        $conversionData = [
            'name' => $name,
            'account' => "urn:li:sponsoredAccount:{$connection->ad_account_id}",
            'type' => $type,
            'attributionType' => 'LAST_TOUCH_BY_CAMPAIGN',
            'postClickAttributionWindowSize' => 30,
            'viewThroughAttributionWindowSize' => 7,
            'enabled' => true,
        ];

        if ($defaultValue !== null) {
            $conversionData['value'] = [
                'currencyCode' => $defaultCurrency,
                'amount' => (string) $defaultValue,
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => $this->apiVersion,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/conversions", $conversionData);

        if (!$response->successful()) {
            throw new \Exception('Failed to create conversion rule: ' . $response->body());
        }

        $ruleId = basename($response->header('x-restli-id') ?? '');

        return LinkedInAdsConversionRule::create([
            'connection_id' => $connection->id,
            'conversion_rule_id' => $ruleId,
            'name' => $name,
            'conversion_type' => $type,
            'is_enabled' => true,
        ]);
    }

    /**
     * Send conversion event
     */
    public function sendConversion(
        LinkedInAdsConnection $connection,
        LinkedInAdsConversionRule $rule,
        array $data
    ): LinkedInAdsConversion {
        $conversionId = $data['conversion_id'] ?? $this->generateConversionId();

        $conversion = LinkedInAdsConversion::create([
            'connection_id' => $connection->id,
            'conversion_rule_id' => $rule->id,
            'conversion_id' => $conversionId,
            'conversion_time' => $data['conversion_time'] ?? now(),
            'conversion_value' => $data['value'] ?? null,
            'currency_code' => $data['currency'] ?? 'EUR',
            'user_data' => isset($data['user_data']) ? $this->hashUserData($data['user_data']) : null,
            'li_fat_id' => $data['li_fat_id'] ?? null,
            'click_id' => $data['click_id'] ?? null,
            'status' => 'pending',
            'correlation_type' => $data['correlation_type'] ?? null,
            'correlation_id' => $data['correlation_id'] ?? null,
        ]);

        try {
            $this->uploadConversion($connection, $rule, $conversion);
        } catch (\Exception $e) {
            $conversion->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $conversion->fresh();
    }

    /**
     * Upload conversion to LinkedIn API
     */
    protected function uploadConversion(
        LinkedInAdsConnection $connection,
        LinkedInAdsConversionRule $rule,
        LinkedInAdsConversion $conversion
    ): void {
        $accessToken = $this->getAccessToken($connection);

        $conversionEvent = [
            'conversion' => "urn:lla:llaPartnerConversion:{$rule->conversion_rule_id}",
            'conversionHappenedAt' => $conversion->conversion_time->getTimestampMs(),
            'conversionValue' => [
                'currencyCode' => $conversion->currency_code,
                'amount' => (string) ($conversion->conversion_value ?? 0),
            ],
        ];

        // Add user identifiers
        $userIdentifiers = [];

        if ($conversion->li_fat_id) {
            $userIdentifiers['linkedInFirstPartyAdsTrackingUUID'] = $conversion->li_fat_id;
        }

        if ($conversion->user_data) {
            if (isset($conversion->user_data['email'])) {
                $userIdentifiers['sha256Email'] = $conversion->user_data['email'];
            }

            if (isset($conversion->user_data['linkedin_member_id'])) {
                $userIdentifiers['linkedInMemberId'] = $conversion->user_data['linkedin_member_id'];
            }
        }

        if (!empty($userIdentifiers)) {
            $conversionEvent['userIds'] = [$userIdentifiers];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => $this->apiVersion,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/conversionEvents", [
            'elements' => [$conversionEvent],
        ]);

        $result = $response->json();

        if (!$response->successful()) {
            throw new \Exception($result['message'] ?? 'Upload failed');
        }

        $conversion->update([
            'status' => 'sent',
            'sent_at' => now(),
            'api_response' => $result,
        ]);

        $connection->update(['last_event_at' => now()]);
    }

    /**
     * Upload batch of conversions
     */
    public function uploadConversionBatch(
        LinkedInAdsConnection $connection,
        LinkedInAdsConversionRule $rule,
        array $conversions
    ): LinkedInAdsBatch {
        $batch = LinkedInAdsBatch::create([
            'connection_id' => $connection->id,
            'conversion_count' => count($conversions),
            'status' => 'pending',
        ]);

        $accessToken = $this->getAccessToken($connection);
        $conversionEvents = [];
        $conversionRecords = [];

        foreach ($conversions as $data) {
            $conversionId = $data['conversion_id'] ?? $this->generateConversionId();

            $record = LinkedInAdsConversion::create([
                'connection_id' => $connection->id,
                'conversion_rule_id' => $rule->id,
                'conversion_id' => $conversionId,
                'conversion_time' => $data['conversion_time'] ?? now(),
                'conversion_value' => $data['value'] ?? null,
                'currency_code' => $data['currency'] ?? 'EUR',
                'user_data' => isset($data['user_data']) ? $this->hashUserData($data['user_data']) : null,
                'li_fat_id' => $data['li_fat_id'] ?? null,
                'click_id' => $data['click_id'] ?? null,
                'status' => 'pending',
                'correlation_type' => $data['correlation_type'] ?? null,
                'correlation_id' => $data['correlation_id'] ?? null,
            ]);
            $conversionRecords[] = $record;

            $event = [
                'conversion' => "urn:lla:llaPartnerConversion:{$rule->conversion_rule_id}",
                'conversionHappenedAt' => $record->conversion_time->getTimestampMs(),
                'conversionValue' => [
                    'currencyCode' => $record->currency_code,
                    'amount' => (string) ($record->conversion_value ?? 0),
                ],
            ];

            $userIdentifiers = [];
            if ($record->li_fat_id) {
                $userIdentifiers['linkedInFirstPartyAdsTrackingUUID'] = $record->li_fat_id;
            }
            if ($record->user_data && isset($record->user_data['email'])) {
                $userIdentifiers['sha256Email'] = $record->user_data['email'];
            }
            if (!empty($userIdentifiers)) {
                $event['userIds'] = [$userIdentifiers];
            }

            $conversionEvents[] = $event;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'LinkedIn-Version' => $this->apiVersion,
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/conversionEvents", [
                'elements' => $conversionEvents,
            ]);

            $result = $response->json();

            $successCount = count($conversions);
            $failCount = 0;
            $errors = [];

            if (isset($result['errors'])) {
                $failCount = count($result['errors']);
                $successCount = count($conversions) - $failCount;
                $errors = $result['errors'];
            }

            foreach ($conversionRecords as $i => $record) {
                $hasError = isset($errors[$i]);
                $record->update([
                    'status' => $hasError ? 'failed' : 'sent',
                    'sent_at' => $hasError ? null : now(),
                    'error_message' => $hasError ? ($errors[$i]['message'] ?? 'Unknown error') : null,
                ]);
            }

            $batch->update([
                'status' => 'completed',
                'successful_count' => $successCount,
                'failed_count' => $failCount,
                'errors' => $errors ?: null,
                'completed_at' => now(),
            ]);

            $connection->update(['last_event_at' => now()]);
        } catch (\Exception $e) {
            $batch->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage()]],
            ]);

            foreach ($conversionRecords as $record) {
                $record->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $batch->fresh();
    }

    /**
     * Track purchase
     */
    public function trackPurchase(
        LinkedInAdsConnection $connection,
        float $value,
        string $currency,
        array $customerData,
        ?string $orderId = null,
        ?string $liFatId = null
    ): LinkedInAdsConversion {
        $rule = $connection->conversionRules()
            ->where('conversion_type', self::TYPE_PURCHASE)
            ->where('is_enabled', true)
            ->first();

        if (!$rule) {
            throw new \Exception('No purchase conversion rule configured');
        }

        return $this->sendConversion($connection, $rule, [
            'value' => $value,
            'currency' => $currency,
            'user_data' => $customerData,
            'li_fat_id' => $liFatId,
            'correlation_type' => 'order',
            'correlation_id' => $orderId,
        ]);
    }

    /**
     * Track lead
     */
    public function trackLead(
        LinkedInAdsConnection $connection,
        array $leadData,
        ?string $liFatId = null,
        ?float $value = null
    ): LinkedInAdsConversion {
        $rule = $connection->conversionRules()
            ->where('conversion_type', self::TYPE_LEAD)
            ->where('is_enabled', true)
            ->first();

        if (!$rule) {
            throw new \Exception('No lead conversion rule configured');
        }

        return $this->sendConversion($connection, $rule, [
            'value' => $value,
            'user_data' => $leadData,
            'li_fat_id' => $liFatId,
            'correlation_type' => 'lead',
        ]);
    }

    /**
     * Hash user data
     */
    protected function hashUserData(array $userData): array
    {
        $hashed = [];

        if (!empty($userData['email'])) {
            $email = trim(strtolower($userData['email']));
            $hashed['email'] = hash('sha256', $email);
        }

        if (!empty($userData['linkedin_member_id'])) {
            $hashed['linkedin_member_id'] = $userData['linkedin_member_id'];
        }

        return $hashed;
    }

    /**
     * Generate unique conversion ID
     */
    protected function generateConversionId(): string
    {
        return 'li_' . Str::uuid()->toString();
    }

    /**
     * Create matched audience
     */
    public function createAudience(
        LinkedInAdsConnection $connection,
        string $name
    ): LinkedInAdsAudience {
        $accessToken = $this->getAccessToken($connection);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => $this->apiVersion,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/dmpSegments", [
            'name' => $name,
            'account' => "urn:li:sponsoredAccount:{$connection->ad_account_id}",
            'type' => 'COMPANY_CONTACTS',
            'destinations' => [
                'linkedInAds' => true,
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create audience: ' . $response->body());
        }

        $segmentId = basename($response->header('x-restli-id') ?? '');

        return LinkedInAdsAudience::create([
            'connection_id' => $connection->id,
            'dmp_segment_id' => $segmentId,
            'name' => $name,
            'audience_type' => 'COMPANY_CONTACTS',
        ]);
    }

    /**
     * Add users to matched audience
     */
    public function addUsersToAudience(
        LinkedInAdsConnection $connection,
        LinkedInAdsAudience $audience,
        array $users
    ): array {
        $accessToken = $this->getAccessToken($connection);

        $emails = [];
        foreach ($users as $user) {
            if (!empty($user['email'])) {
                $emails[] = hash('sha256', trim(strtolower($user['email'])));
            }
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => $this->apiVersion,
            'X-Restli-Protocol-Version' => '2.0.0',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/dmpSegments/{$audience->dmp_segment_id}/contacts", [
            'elements' => array_map(function ($email) {
                return ['sha256Email' => $email];
            }, $emails),
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to add users to audience: ' . $response->body());
        }

        $audience->update(['last_synced_at' => now()]);

        return $response->json();
    }
}
