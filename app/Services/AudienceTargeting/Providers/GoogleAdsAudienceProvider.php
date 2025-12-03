<?php

namespace App\Services\AudienceTargeting\Providers;

use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsAudienceProvider implements AudienceProviderInterface
{
    protected const API_VERSION = 'v15';
    protected const BASE_URL = 'https://googleads.googleapis.com';

    protected ?string $customerId = null;
    protected ?string $developerToken = null;
    protected ?array $credentials = null;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $settings = Setting::first();

        $this->customerId = $settings?->google_ads_customer_id;
        $this->developerToken = $settings?->google_ads_developer_token;

        if ($settings?->google_ads_credentials_json) {
            $this->credentials = json_decode($settings->google_ads_credentials_json, true);
            $this->refreshAccessToken();
        }
    }

    protected function refreshAccessToken(): void
    {
        if (!$this->credentials) {
            return;
        }

        // In production, implement proper OAuth2 token refresh
        // This is simplified for the example
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->credentials['client_id'] ?? '',
            'client_secret' => $this->credentials['client_secret'] ?? '',
            'refresh_token' => $this->credentials['refresh_token'] ?? '',
            'grant_type' => 'refresh_token',
        ]);

        if ($response->successful()) {
            $this->accessToken = $response->json('access_token');
        }
    }

    public function getName(): string
    {
        return 'Google Ads';
    }

    public function getPlatform(): string
    {
        return AudienceExport::PLATFORM_GOOGLE;
    }

    public function isConfigured(): bool
    {
        return !empty($this->customerId)
            && !empty($this->developerToken)
            && !empty($this->accessToken);
    }

    public function createCustomAudience(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): array {
        $this->ensureConfigured();

        $customerId = $this->formatCustomerId($this->customerId);

        // Step 1: Create User List
        $userListOperation = [
            'create' => [
                'name' => $audienceName,
                'description' => $description ?? "Audience from segment: {$segment->name}",
                'membershipStatus' => 'OPEN',
                'membershipLifeSpan' => 365, // 365 days
                'crmBasedUserList' => [
                    'uploadKeyType' => 'CONTACT_INFO',
                    'dataSourceType' => 'FIRST_PARTY',
                ],
            ],
        ];

        $response = $this->apiRequest(
            "customers/{$customerId}/userLists:mutate",
            ['operations' => [$userListOperation]]
        );

        if (!$response['success']) {
            throw new \RuntimeException(
                'Failed to create Google Ads user list: ' . ($response['error'] ?? 'Unknown error')
            );
        }

        $userListResourceName = $response['data']['results'][0]['resourceName'] ?? null;

        if (!$userListResourceName) {
            throw new \RuntimeException('User list created but no resource name returned');
        }

        // Extract the user list ID from resource name
        preg_match('/userLists\/(\d+)/', $userListResourceName, $matches);
        $userListId = $matches[1] ?? $userListResourceName;

        // Step 2: Upload customer data
        $customers = $segment->customers()->with('customer')->get()->pluck('customer');
        $result = $this->uploadCustomers($userListResourceName, $customers);

        return [
            'external_id' => $userListId,
            'name' => $audienceName,
            'matched_count' => $result['matched_count'] ?? null,
        ];
    }

    public function updateCustomAudience(
        string $externalAudienceId,
        Collection $customers
    ): array {
        $this->ensureConfigured();

        $customerId = $this->formatCustomerId($this->customerId);
        $userListResourceName = "customers/{$customerId}/userLists/{$externalAudienceId}";

        return $this->uploadCustomers($userListResourceName, $customers);
    }

    protected function uploadCustomers(string $userListResourceName, Collection $customers): array
    {
        $preparedData = $this->prepareCustomerData($customers);

        if (empty($preparedData['userIdentifiers'])) {
            return ['matched_count' => 0];
        }

        $customerId = $this->formatCustomerId($this->customerId);

        // Google Ads accepts batches of up to 100,000 identifiers
        $batches = array_chunk($preparedData['userIdentifiers'], 100000);
        $totalUploaded = 0;

        foreach ($batches as $batch) {
            $offlineUserDataJobOperation = [
                'create' => [
                    'type' => 'CUSTOMER_MATCH_USER_LIST',
                    'customerMatchUserListMetadata' => [
                        'userList' => $userListResourceName,
                    ],
                ],
            ];

            // Create the job
            $createResponse = $this->apiRequest(
                "customers/{$customerId}/offlineUserDataJobs:create",
                $offlineUserDataJobOperation
            );

            if (!$createResponse['success']) {
                Log::warning('Failed to create offline user data job', [
                    'error' => $createResponse['error'],
                ]);
                continue;
            }

            $jobResourceName = $createResponse['data']['resourceName'] ?? null;

            if (!$jobResourceName) {
                continue;
            }

            // Add operations to the job
            $userDataOperations = array_map(fn ($identifier) => [
                'create' => ['userIdentifiers' => [$identifier]],
            ], $batch);

            $this->apiRequest(
                "{$jobResourceName}:addOperations",
                [
                    'operations' => $userDataOperations,
                    'enablePartialFailure' => true,
                ]
            );

            // Run the job
            $this->apiRequest("{$jobResourceName}:run", []);

            $totalUploaded += count($batch);
        }

        return ['matched_count' => $totalUploaded];
    }

    public function deleteCustomAudience(string $externalAudienceId): bool
    {
        $this->ensureConfigured();

        $customerId = $this->formatCustomerId($this->customerId);

        $operation = [
            'remove' => "customers/{$customerId}/userLists/{$externalAudienceId}",
        ];

        $response = $this->apiRequest(
            "customers/{$customerId}/userLists:mutate",
            ['operations' => [$operation]]
        );

        return $response['success'];
    }

    public function createLookalikeAudience(
        string $sourceAudienceId,
        string $audienceName,
        array $options = []
    ): array {
        $this->ensureConfigured();

        $customerId = $this->formatCustomerId($this->customerId);
        $country = $options['country'] ?? 'RO';

        // In Google Ads, "Similar Audiences" are auto-generated by Google
        // We can't create them directly, but we can set up the source list
        // to be eligible for similar audience generation

        // For now, return the source audience info
        // In production, you'd check if a similar audience was generated
        return [
            'external_id' => $sourceAudienceId,
            'name' => $audienceName,
            'note' => 'Similar audiences are automatically generated by Google',
        ];
    }

    public function getAudienceDetails(string $externalAudienceId): ?array
    {
        $this->ensureConfigured();

        $customerId = $this->formatCustomerId($this->customerId);

        $query = "SELECT user_list.id, user_list.name, user_list.size_for_display, " .
            "user_list.size_for_search, user_list.membership_status " .
            "FROM user_list WHERE user_list.id = {$externalAudienceId}";

        $response = $this->apiRequest(
            "customers/{$customerId}/googleAds:searchStream",
            ['query' => $query]
        );

        if (!$response['success'] || empty($response['data'])) {
            return null;
        }

        return $response['data'][0]['results'][0]['userList'] ?? null;
    }

    public function prepareCustomerData(Collection $customers): array
    {
        $userIdentifiers = [];

        foreach ($customers as $customer) {
            if (!$customer || !$customer->email) {
                continue;
            }

            // Google recommends sending multiple identifiers per user
            $identifiers = [];

            // Hashed email
            $identifiers[] = [
                'hashedEmail' => $this->hashForGoogle($customer->email),
            ];

            // Hashed phone if available
            if ($customer->phone) {
                $identifiers[] = [
                    'hashedPhoneNumber' => $this->hashForGoogle(
                        $this->normalizePhone($customer->phone)
                    ),
                ];
            }

            // Address info if available
            if ($customer->first_name && $customer->last_name) {
                $identifiers[] = [
                    'addressInfo' => [
                        'hashedFirstName' => $this->hashForGoogle($customer->first_name),
                        'hashedLastName' => $this->hashForGoogle($customer->last_name),
                        'city' => $customer->city ?? '',
                        'countryCode' => $this->getCountryCode($customer->country),
                    ],
                ];
            }

            $userIdentifiers = array_merge($userIdentifiers, $identifiers);
        }

        return ['userIdentifiers' => $userIdentifiers];
    }

    /**
     * Hash value for Google Ads Customer Match (SHA256)
     */
    protected function hashForGoogle(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // Google requires lowercase, trimmed, SHA256 hashed values
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * Normalize phone number for Google Ads (E.164 format)
     */
    protected function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Ensure E.164 format
        if (!str_starts_with($phone, '+')) {
            $phone = '+40' . ltrim($phone, '0'); // Default to Romania
        }

        return $phone;
    }

    /**
     * Get ISO 3166-1 alpha-2 country code
     */
    protected function getCountryCode(?string $country): string
    {
        if (empty($country)) {
            return 'RO'; // Default
        }

        // Simple mapping - in production, use a proper library
        $mapping = [
            'romania' => 'RO',
            'germany' => 'DE',
            'france' => 'FR',
            'united states' => 'US',
            'united kingdom' => 'GB',
            // Add more as needed
        ];

        $lower = strtolower(trim($country));

        return $mapping[$lower] ?? strtoupper(substr($country, 0, 2));
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['customer_id'] ?? $this->customerId)) {
            $errors['customer_id'] = 'Google Ads Customer ID is required';
        }

        if (empty($settings['developer_token'] ?? $this->developerToken)) {
            $errors['developer_token'] = 'Google Ads Developer Token is required';
        }

        if (empty($settings['credentials_json'] ?? $this->credentials)) {
            $errors['credentials_json'] = 'Google Ads OAuth credentials are required';
        }

        return $errors;
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Google Ads provider is not configured');
        }
    }

    protected function formatCustomerId(string $customerId): string
    {
        // Remove dashes if present (123-456-7890 -> 1234567890)
        return str_replace('-', '', $customerId);
    }

    protected function apiRequest(string $endpoint, array $data): array
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'developer-token' => $this->developerToken,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }
}
