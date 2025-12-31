<?php

namespace App\Services\Integrations\GoogleAds;

use App\Models\Integrations\GoogleAds\GoogleAdsConnection;
use App\Models\Integrations\GoogleAds\GoogleAdsConversion;
use App\Models\Integrations\GoogleAds\GoogleAdsConversionAction;
use App\Models\Integrations\GoogleAds\GoogleAdsAudience;
use App\Models\Integrations\GoogleAds\GoogleAdsUploadBatch;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsService
{
    protected string $apiVersion = 'v15';
    protected string $baseUrl = 'https://googleads.googleapis.com';
    protected string $oauthUrl = 'https://oauth2.googleapis.com/token';

    /**
     * Get OAuth authorization URL for tenant to connect
     */
    public function getAuthorizationUrl(int $tenantId, string $redirectUri): string
    {
        $settings = Setting::current();
        $clientId = $settings->google_ads_client_id;

        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/adwords',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => base64_encode(json_encode(['tenant_id' => $tenantId])),
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code, string $redirectUri): array
    {
        $settings = Setting::current();

        $response = Http::asForm()->post($this->oauthUrl, [
            'client_id' => $settings->google_ads_client_id,
            'client_secret' => $settings->google_ads_client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
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
    public function refreshAccessToken(GoogleAdsConnection $connection): string
    {
        $settings = Setting::current();

        $response = Http::asForm()->post($this->oauthUrl, [
            'client_id' => $settings->google_ads_client_id,
            'client_secret' => $settings->google_ads_client_secret,
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to refresh token: ' . $response->body());
        }

        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => now()->addSeconds($data['expires_in'] - 60),
        ]);

        return $data['access_token'];
    }

    /**
     * Get valid access token (refresh if expired)
     */
    protected function getAccessToken(GoogleAdsConnection $connection): string
    {
        if ($connection->token_expires_at && $connection->token_expires_at->isFuture()) {
            return $connection->access_token;
        }

        return $this->refreshAccessToken($connection);
    }

    /**
     * Create a new connection for a tenant
     */
    public function createConnection(
        int $tenantId,
        string $customerId,
        string $refreshToken,
        string $accessToken,
        int $expiresIn
    ): GoogleAdsConnection {
        // Normalize customer ID (remove dashes)
        $customerId = str_replace('-', '', $customerId);

        $connection = GoogleAdsConnection::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'refresh_token' => $refreshToken,
            'access_token' => $accessToken,
            'token_expires_at' => now()->addSeconds($expiresIn - 60),
            'status' => 'active',
            'enabled_conversions' => ['Purchase', 'AddToCart', 'Lead', 'SignUp'],
        ]);

        // Fetch existing conversion actions from Google Ads
        try {
            $this->syncConversionActions($connection);
        } catch (\Exception $e) {
            Log::warning('Failed to sync conversion actions', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $connection->fresh();
    }

    /**
     * Test connection to Google Ads
     */
    public function testConnection(GoogleAdsConnection $connection): bool
    {
        try {
            $accessToken = $this->getAccessToken($connection);
            $settings = Setting::current();

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'developer-token' => $settings->google_ads_developer_token,
                'login-customer-id' => $connection->customer_id,
            ])->get("{$this->baseUrl}/{$this->apiVersion}/customers/{$connection->customer_id}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Google Ads connection test failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync conversion actions from Google Ads account
     */
    public function syncConversionActions(GoogleAdsConnection $connection): array
    {
        $accessToken = $this->getAccessToken($connection);
        $settings = Setting::current();

        $query = "SELECT conversion_action.id, conversion_action.name, conversion_action.category,
                         conversion_action.counting_type, conversion_action.status
                  FROM conversion_action
                  WHERE conversion_action.status = 'ENABLED'";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'developer-token' => $settings->google_ads_developer_token,
            'login-customer-id' => $connection->customer_id,
        ])->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$connection->customer_id}/googleAds:searchStream", [
            'query' => $query,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch conversion actions: ' . $response->body());
        }

        $actions = [];
        $results = $response->json();

        foreach ($results as $batch) {
            foreach ($batch['results'] ?? [] as $result) {
                $convAction = $result['conversionAction'];
                $actionId = basename($convAction['resourceName']);

                $action = GoogleAdsConversionAction::updateOrCreate(
                    [
                        'connection_id' => $connection->id,
                        'conversion_action_id' => $actionId,
                    ],
                    [
                        'name' => $convAction['name'],
                        'category' => $convAction['category'] ?? null,
                        'counting_type' => $convAction['countingType'] ?? 'ONE_PER_CLICK',
                    ]
                );
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Create a conversion action in Google Ads
     */
    public function createConversionAction(
        GoogleAdsConnection $connection,
        string $name,
        string $category = 'PURCHASE',
        string $countingType = 'ONE_PER_CLICK',
        ?float $defaultValue = null,
        string $defaultCurrency = 'EUR'
    ): GoogleAdsConversionAction {
        $accessToken = $this->getAccessToken($connection);
        $settings = Setting::current();

        $conversionAction = [
            'name' => $name,
            'category' => $category,
            'type' => 'UPLOAD_CLICKS',
            'countingType' => $countingType,
            'status' => 'ENABLED',
        ];

        if ($defaultValue !== null) {
            $conversionAction['valueSettings'] = [
                'defaultValue' => $defaultValue,
                'defaultCurrencyCode' => $defaultCurrency,
                'alwaysUseDefaultValue' => false,
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'developer-token' => $settings->google_ads_developer_token,
            'login-customer-id' => $connection->customer_id,
        ])->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$connection->customer_id}/conversionActions:mutate", [
            'operations' => [
                ['create' => $conversionAction],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create conversion action: ' . $response->body());
        }

        $result = $response->json();
        $resourceName = $result['results'][0]['resourceName'] ?? '';
        $actionId = basename($resourceName);

        return GoogleAdsConversionAction::create([
            'connection_id' => $connection->id,
            'conversion_action_id' => $actionId,
            'name' => $name,
            'category' => $category,
            'counting_type' => $countingType,
            'is_primary' => false,
            'is_enabled' => true,
        ]);
    }

    /**
     * Send a single conversion to Google Ads
     */
    public function sendConversion(
        GoogleAdsConnection $connection,
        GoogleAdsConversionAction $action,
        array $data
    ): GoogleAdsConversion {
        $conversionId = $data['conversion_id'] ?? $this->generateConversionId();

        $conversion = GoogleAdsConversion::create([
            'connection_id' => $connection->id,
            'conversion_action_id' => $action->id,
            'conversion_id' => $conversionId,
            'gclid' => $data['gclid'] ?? null,
            'gbraid' => $data['gbraid'] ?? null,
            'wbraid' => $data['wbraid'] ?? null,
            'conversion_time' => $data['conversion_time'] ?? now(),
            'conversion_value' => $data['value'] ?? null,
            'currency_code' => $data['currency'] ?? 'EUR',
            'order_id' => $data['order_id'] ?? null,
            'user_data' => isset($data['user_data']) ? $this->hashUserData($data['user_data']) : null,
            'custom_variables' => $data['custom_variables'] ?? null,
            'status' => 'pending',
            'correlation_type' => $data['correlation_type'] ?? null,
            'correlation_id' => $data['correlation_id'] ?? null,
        ]);

        try {
            $this->uploadConversion($connection, $action, $conversion);
        } catch (\Exception $e) {
            $conversion->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $conversion->fresh();
    }

    /**
     * Upload conversion to Google Ads API
     */
    protected function uploadConversion(
        GoogleAdsConnection $connection,
        GoogleAdsConversionAction $action,
        GoogleAdsConversion $conversion
    ): void {
        $accessToken = $this->getAccessToken($connection);
        $settings = Setting::current();

        $clickConversion = [
            'conversionAction' => "customers/{$connection->customer_id}/conversionActions/{$action->conversion_action_id}",
            'conversionDateTime' => $conversion->conversion_time->format('Y-m-d H:i:sP'),
        ];

        // Add click identifier (GCLID, GBRAID, or WBRAID)
        if ($conversion->gclid) {
            $clickConversion['gclid'] = $conversion->gclid;
        } elseif ($conversion->gbraid) {
            $clickConversion['gbraid'] = $conversion->gbraid;
        } elseif ($conversion->wbraid) {
            $clickConversion['wbraid'] = $conversion->wbraid;
        }

        // Add value if present
        if ($conversion->conversion_value) {
            $clickConversion['conversionValue'] = (float) $conversion->conversion_value;
            $clickConversion['currencyCode'] = $conversion->currency_code;
        }

        // Add order ID for deduplication
        if ($conversion->order_id) {
            $clickConversion['orderId'] = (string) $conversion->order_id;
        }

        // Add user identifiers for enhanced conversions
        if ($conversion->user_data) {
            $clickConversion['userIdentifiers'] = $this->buildUserIdentifiers($conversion->user_data);
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'developer-token' => $settings->google_ads_developer_token,
            'login-customer-id' => $connection->customer_id,
        ])->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$connection->customer_id}:uploadClickConversions", [
            'conversions' => [$clickConversion],
            'partialFailure' => true,
        ]);

        $result = $response->json();

        if (!$response->successful()) {
            throw new \Exception($result['error']['message'] ?? 'Upload failed');
        }

        // Check for partial failures
        if (isset($result['partialFailureError'])) {
            throw new \Exception($result['partialFailureError']['message'] ?? 'Partial failure');
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
        GoogleAdsConnection $connection,
        GoogleAdsConversionAction $action,
        array $conversions
    ): GoogleAdsUploadBatch {
        $batch = GoogleAdsUploadBatch::create([
            'connection_id' => $connection->id,
            'conversion_count' => count($conversions),
            'status' => 'pending',
        ]);

        $accessToken = $this->getAccessToken($connection);
        $settings = Setting::current();

        $clickConversions = [];
        $conversionRecords = [];

        foreach ($conversions as $data) {
            $conversionId = $data['conversion_id'] ?? $this->generateConversionId();

            $record = GoogleAdsConversion::create([
                'connection_id' => $connection->id,
                'conversion_action_id' => $action->id,
                'conversion_id' => $conversionId,
                'gclid' => $data['gclid'] ?? null,
                'gbraid' => $data['gbraid'] ?? null,
                'wbraid' => $data['wbraid'] ?? null,
                'conversion_time' => $data['conversion_time'] ?? now(),
                'conversion_value' => $data['value'] ?? null,
                'currency_code' => $data['currency'] ?? 'EUR',
                'order_id' => $data['order_id'] ?? null,
                'user_data' => isset($data['user_data']) ? $this->hashUserData($data['user_data']) : null,
                'status' => 'pending',
                'correlation_type' => $data['correlation_type'] ?? null,
                'correlation_id' => $data['correlation_id'] ?? null,
            ]);
            $conversionRecords[] = $record;

            $clickConversion = [
                'conversionAction' => "customers/{$connection->customer_id}/conversionActions/{$action->conversion_action_id}",
                'conversionDateTime' => $record->conversion_time->format('Y-m-d H:i:sP'),
            ];

            if ($record->gclid) {
                $clickConversion['gclid'] = $record->gclid;
            } elseif ($record->gbraid) {
                $clickConversion['gbraid'] = $record->gbraid;
            } elseif ($record->wbraid) {
                $clickConversion['wbraid'] = $record->wbraid;
            }

            if ($record->conversion_value) {
                $clickConversion['conversionValue'] = (float) $record->conversion_value;
                $clickConversion['currencyCode'] = $record->currency_code;
            }

            if ($record->order_id) {
                $clickConversion['orderId'] = (string) $record->order_id;
            }

            if ($record->user_data) {
                $clickConversion['userIdentifiers'] = $this->buildUserIdentifiers($record->user_data);
            }

            $clickConversions[] = $clickConversion;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'developer-token' => $settings->google_ads_developer_token,
                'login-customer-id' => $connection->customer_id,
            ])->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$connection->customer_id}:uploadClickConversions", [
                'conversions' => $clickConversions,
                'partialFailure' => true,
            ]);

            $result = $response->json();

            $successCount = 0;
            $failCount = 0;
            $errors = [];

            if (isset($result['partialFailureError'])) {
                // Parse partial failures
                foreach ($result['partialFailureError']['details'] ?? [] as $detail) {
                    $errors[] = $detail;
                    $failCount++;
                }
                $successCount = count($conversions) - $failCount;
            } else {
                $successCount = count($conversions);
            }

            // Update individual conversion records
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
     * Track purchase conversion
     */
    public function trackPurchase(
        GoogleAdsConnection $connection,
        float $value,
        string $currency,
        array $customerData,
        ?string $orderId = null,
        ?string $gclid = null
    ): GoogleAdsConversion {
        $action = $connection->conversionActions()
            ->where('category', 'PURCHASE')
            ->where('is_enabled', true)
            ->first();

        if (!$action) {
            throw new \Exception('No purchase conversion action configured');
        }

        return $this->sendConversion($connection, $action, [
            'gclid' => $gclid,
            'value' => $value,
            'currency' => $currency,
            'order_id' => $orderId,
            'user_data' => $customerData,
            'correlation_type' => 'order',
            'correlation_id' => $orderId,
        ]);
    }

    /**
     * Track lead conversion
     */
    public function trackLead(
        GoogleAdsConnection $connection,
        array $leadData,
        ?string $gclid = null,
        ?float $value = null
    ): GoogleAdsConversion {
        $action = $connection->conversionActions()
            ->where('category', 'SUBMIT_LEAD_FORM')
            ->where('is_enabled', true)
            ->first();

        if (!$action) {
            // Try generic lead category
            $action = $connection->conversionActions()
                ->where('name', 'LIKE', '%lead%')
                ->where('is_enabled', true)
                ->first();
        }

        if (!$action) {
            throw new \Exception('No lead conversion action configured');
        }

        return $this->sendConversion($connection, $action, [
            'gclid' => $gclid,
            'value' => $value,
            'user_data' => $leadData,
            'correlation_type' => 'lead',
        ]);
    }

    /**
     * Hash user data for enhanced conversions
     */
    protected function hashUserData(array $userData): array
    {
        $hashed = [];
        $hashFields = ['email', 'phone', 'first_name', 'last_name', 'street_address', 'city', 'region', 'postal_code', 'country'];

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
            // Remove all non-numeric characters except +
            $value = preg_replace('/[^0-9+]/', '', $value);
        }

        if ($key === 'email') {
            // Remove dots from gmail local part, handle + aliases
            if (str_ends_with($value, '@gmail.com')) {
                $parts = explode('@', $value);
                $local = str_replace('.', '', $parts[0]);
                $local = explode('+', $local)[0];
                $value = $local . '@gmail.com';
            }
        }

        return $value;
    }

    /**
     * Build user identifiers for Google Ads API
     */
    protected function buildUserIdentifiers(array $hashedData): array
    {
        $identifiers = [];

        if (isset($hashedData['email'])) {
            $identifiers[] = [
                'hashedEmail' => $hashedData['email'],
            ];
        }

        if (isset($hashedData['phone'])) {
            $identifiers[] = [
                'hashedPhoneNumber' => $hashedData['phone'],
            ];
        }

        // Address info
        $addressInfo = [];
        if (isset($hashedData['first_name'])) {
            $addressInfo['hashedFirstName'] = $hashedData['first_name'];
        }
        if (isset($hashedData['last_name'])) {
            $addressInfo['hashedLastName'] = $hashedData['last_name'];
        }
        if (isset($hashedData['street_address'])) {
            $addressInfo['hashedStreetAddress'] = $hashedData['street_address'];
        }
        if (isset($hashedData['city'])) {
            $addressInfo['city'] = $hashedData['city'];
        }
        if (isset($hashedData['region'])) {
            $addressInfo['state'] = $hashedData['region'];
        }
        if (isset($hashedData['postal_code'])) {
            $addressInfo['postalCode'] = $hashedData['postal_code'];
        }
        if (isset($hashedData['country'])) {
            $addressInfo['countryCode'] = strtoupper($hashedData['country']);
        }

        if (!empty($addressInfo)) {
            $identifiers[] = ['addressInfo' => $addressInfo];
        }

        return $identifiers;
    }

    /**
     * Generate unique conversion ID
     */
    protected function generateConversionId(): string
    {
        return 'gads_' . Str::uuid()->toString();
    }

    /**
     * Create Customer Match audience
     */
    public function createAudience(
        GoogleAdsConnection $connection,
        string $name,
        string $description = ''
    ): GoogleAdsAudience {
        $accessToken = $this->getAccessToken($connection);
        $settings = Setting::current();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'developer-token' => $settings->google_ads_developer_token,
            'login-customer-id' => $connection->customer_id,
        ])->post("{$this->baseUrl}/{$this->apiVersion}/customers/{$connection->customer_id}/userLists:mutate", [
            'operations' => [
                [
                    'create' => [
                        'name' => $name,
                        'description' => $description,
                        'membershipStatus' => 'OPEN',
                        'membershipLifeSpan' => 540, // 18 months max
                        'crmBasedUserList' => [
                            'uploadKeyType' => 'CONTACT_INFO',
                            'dataSourceType' => 'FIRST_PARTY',
                        ],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create audience: ' . $response->body());
        }

        $result = $response->json();
        $resourceName = $result['results'][0]['resourceName'] ?? '';

        return GoogleAdsAudience::create([
            'connection_id' => $connection->id,
            'resource_name' => $resourceName,
            'name' => $name,
            'description' => $description,
            'membership_status' => 'OPEN',
        ]);
    }

    /**
     * Add users to Customer Match audience
     */
    public function addUsersToAudience(
        GoogleAdsConnection $connection,
        GoogleAdsAudience $audience,
        array $users
    ): array {
        $accessToken = $this->getAccessToken($connection);
        $settings = Setting::current();

        $userIdentifiers = [];
        foreach ($users as $user) {
            $identifiers = [];

            if (!empty($user['email'])) {
                $identifiers[] = [
                    'hashedEmail' => hash('sha256', $this->normalizeValue('email', $user['email'])),
                ];
            }

            if (!empty($user['phone'])) {
                $identifiers[] = [
                    'hashedPhoneNumber' => hash('sha256', $this->normalizeValue('phone', $user['phone'])),
                ];
            }

            if (!empty($identifiers)) {
                $userIdentifiers[] = ['userIdentifiers' => $identifiers];
            }
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'developer-token' => $settings->google_ads_developer_token,
            'login-customer-id' => $connection->customer_id,
        ])->post("{$this->baseUrl}/{$this->apiVersion}/{$audience->resource_name}:mutateMembers", [
            'operations' => [
                [
                    'create' => [
                        'userList' => $audience->resource_name,
                        'crmBasedUserList' => [
                            'users' => $userIdentifiers,
                        ],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to add users to audience: ' . $response->body());
        }

        $audience->update(['last_synced_at' => now()]);

        return $response->json();
    }
}
