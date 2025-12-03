<?php

namespace App\Services\AudienceTargeting\Providers;

use App\Models\AudienceExport;
use App\Models\AudienceSegment;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoAudienceProvider implements AudienceProviderInterface
{
    protected const BASE_URL = 'https://api.brevo.com/v3';

    protected ?string $apiKey = null;

    public function __construct()
    {
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $settings = Setting::first();
        $this->apiKey = $settings?->brevo_api_key;
    }

    public function getName(): string
    {
        return 'Brevo (Email Marketing)';
    }

    public function getPlatform(): string
    {
        return AudienceExport::PLATFORM_BREVO;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function createCustomAudience(
        AudienceSegment $segment,
        string $audienceName,
        ?string $description = null
    ): array {
        $this->ensureConfigured();

        // Step 1: Create a contact list
        $response = $this->apiRequest('POST', 'contacts/lists', [
            'name' => $audienceName,
            'folderId' => $this->getOrCreateFolder('Audience Segments'),
        ]);

        if (!$response['success']) {
            throw new \RuntimeException(
                'Failed to create Brevo list: ' . ($response['error'] ?? 'Unknown error')
            );
        }

        $listId = $response['data']['id'] ?? null;

        if (!$listId) {
            throw new \RuntimeException('List created but no ID returned');
        }

        // Step 2: Add contacts to the list
        $customers = $segment->customers()->with('customer')->get()->pluck('customer');
        $result = $this->uploadCustomers((string) $listId, $customers);

        return [
            'external_id' => (string) $listId,
            'name' => $audienceName,
            'matched_count' => $result['matched_count'] ?? count($customers),
        ];
    }

    public function updateCustomAudience(
        string $externalAudienceId,
        Collection $customers
    ): array {
        $this->ensureConfigured();

        return $this->uploadCustomers($externalAudienceId, $customers);
    }

    protected function uploadCustomers(string $listId, Collection $customers): array
    {
        $preparedData = $this->prepareCustomerData($customers);

        if (empty($preparedData['contacts'])) {
            return ['matched_count' => 0];
        }

        // Brevo accepts batches of contacts
        $batches = array_chunk($preparedData['contacts'], 150); // Brevo limit
        $totalImported = 0;

        foreach ($batches as $batch) {
            // First, create/update contacts
            $importResponse = $this->apiRequest('POST', 'contacts/import', [
                'listIds' => [(int) $listId],
                'updateExistingContacts' => true,
                'emptyContactsAttributes' => false,
                'jsonBody' => $batch,
            ]);

            if (!$importResponse['success']) {
                Log::warning('Brevo contact import batch failed', [
                    'error' => $importResponse['error'],
                ]);
                continue;
            }

            $totalImported += count($batch);
        }

        return ['matched_count' => $totalImported];
    }

    public function deleteCustomAudience(string $externalAudienceId): bool
    {
        $this->ensureConfigured();

        $response = $this->apiRequest('DELETE', "contacts/lists/{$externalAudienceId}");

        return $response['success'];
    }

    public function createLookalikeAudience(
        string $sourceAudienceId,
        string $audienceName,
        array $options = []
    ): array {
        // Brevo doesn't support lookalike audiences natively
        // Return the source audience info
        return [
            'external_id' => $sourceAudienceId,
            'name' => $audienceName,
            'note' => 'Brevo does not support lookalike audiences',
        ];
    }

    public function getAudienceDetails(string $externalAudienceId): ?array
    {
        $this->ensureConfigured();

        $response = $this->apiRequest('GET', "contacts/lists/{$externalAudienceId}");

        if (!$response['success']) {
            return null;
        }

        return $response['data'];
    }

    public function prepareCustomerData(Collection $customers): array
    {
        $contacts = [];

        foreach ($customers as $customer) {
            if (!$customer || !$customer->email) {
                continue;
            }

            $contact = [
                'email' => strtolower(trim($customer->email)),
                'attributes' => [],
            ];

            // Add additional attributes
            if ($customer->first_name) {
                $contact['attributes']['FIRSTNAME'] = $customer->first_name;
            }

            if ($customer->last_name) {
                $contact['attributes']['LASTNAME'] = $customer->last_name;
            }

            if ($customer->phone) {
                $contact['attributes']['SMS'] = $this->normalizePhone($customer->phone);
            }

            if ($customer->city) {
                $contact['attributes']['CITY'] = $customer->city;
            }

            if ($customer->country) {
                $contact['attributes']['COUNTRY'] = $customer->country;
            }

            $contacts[] = $contact;
        }

        return ['contacts' => $contacts];
    }

    /**
     * Normalize phone number for Brevo
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
            $phone = '+40' . ltrim($phone, '0'); // Default to Romania
        }

        return $phone;
    }

    public function validateSettings(array $settings): array
    {
        $errors = [];

        if (empty($settings['api_key'] ?? $this->apiKey)) {
            $errors['api_key'] = 'Brevo API key is required';
        }

        return $errors;
    }

    protected function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Brevo provider is not configured');
        }
    }

    protected function apiRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = self::BASE_URL . '/' . $endpoint;

        $request = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $response = match ($method) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => $response->json('message', 'Unknown error'),
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Get or create a folder for organizing lists
     */
    protected function getOrCreateFolder(string $name): int
    {
        // Try to find existing folder
        $response = $this->apiRequest('GET', 'contacts/folders', ['limit' => 50]);

        if ($response['success']) {
            foreach ($response['data']['folders'] ?? [] as $folder) {
                if ($folder['name'] === $name) {
                    return $folder['id'];
                }
            }
        }

        // Create new folder
        $createResponse = $this->apiRequest('POST', 'contacts/folders', [
            'name' => $name,
        ]);

        if ($createResponse['success']) {
            return $createResponse['data']['id'];
        }

        return 1; // Default folder ID
    }

    /**
     * Send an email campaign to a list
     */
    public function sendEmailCampaign(
        string $listId,
        string $subject,
        string $htmlContent,
        string $senderName,
        string $senderEmail,
        ?string $scheduledAt = null
    ): array {
        $this->ensureConfigured();

        $campaignData = [
            'name' => $subject . ' - ' . now()->format('Y-m-d H:i'),
            'subject' => $subject,
            'sender' => [
                'name' => $senderName,
                'email' => $senderEmail,
            ],
            'type' => 'classic',
            'htmlContent' => $htmlContent,
            'recipients' => [
                'listIds' => [(int) $listId],
            ],
        ];

        // Create campaign
        $createResponse = $this->apiRequest('POST', 'emailCampaigns', $campaignData);

        if (!$createResponse['success']) {
            throw new \RuntimeException(
                'Failed to create email campaign: ' . ($createResponse['error'] ?? 'Unknown error')
            );
        }

        $campaignId = $createResponse['data']['id'];

        // Schedule or send immediately
        if ($scheduledAt) {
            $this->apiRequest('POST', "emailCampaigns/{$campaignId}/schedule", [
                'scheduledAt' => $scheduledAt,
            ]);
        } else {
            $this->apiRequest('POST', "emailCampaigns/{$campaignId}/sendNow");
        }

        return [
            'campaign_id' => $campaignId,
            'status' => $scheduledAt ? 'scheduled' : 'sent',
        ];
    }

    /**
     * Get campaign statistics
     */
    public function getCampaignStats(int $campaignId): ?array
    {
        $this->ensureConfigured();

        $response = $this->apiRequest('GET', "emailCampaigns/{$campaignId}");

        if (!$response['success']) {
            return null;
        }

        $campaign = $response['data'];

        return [
            'sent' => $campaign['statistics']['globalStats']['sent'] ?? 0,
            'delivered' => $campaign['statistics']['globalStats']['delivered'] ?? 0,
            'opens' => $campaign['statistics']['globalStats']['uniqueViews'] ?? 0,
            'clicks' => $campaign['statistics']['globalStats']['uniqueClicks'] ?? 0,
            'unsubscribes' => $campaign['statistics']['globalStats']['unsubscribed'] ?? 0,
            'bounces' => $campaign['statistics']['globalStats']['hardBounces'] ?? 0,
        ];
    }

    /**
     * Get list statistics
     */
    public function getListStats(int $listId): ?array
    {
        $this->ensureConfigured();

        $response = $this->apiRequest('GET', "contacts/lists/{$listId}");

        if (!$response['success']) {
            return null;
        }

        return [
            'total_subscribers' => $response['data']['totalSubscribers'] ?? 0,
            'total_blacklisted' => $response['data']['totalBlacklisted'] ?? 0,
        ];
    }
}
