<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareService
{
    private ?string $apiToken;
    private ?string $zoneId;
    private string $baseDomain;
    private string $apiUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct()
    {
        $this->apiToken = config('services.cloudflare.api_token');
        $this->zoneId = config('services.cloudflare.zone_id');
        $this->baseDomain = config('services.cloudflare.base_domain', 'ticks.ro');
    }

    /**
     * Check if Cloudflare is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->zoneId);
    }

    /**
     * Create a DNS A record for a subdomain
     * Since we use wildcard DNS (*.ticks.ro), this is optional
     * but can be used for tracking/management purposes
     */
    public function createSubdomainRecord(string $subdomain): array
    {
        if (!$this->isConfigured()) {
            Log::warning('Cloudflare not configured, skipping DNS record creation', [
                'subdomain' => $subdomain,
            ]);
            return ['id' => null, 'skipped' => true];
        }

        $response = Http::withToken($this->apiToken)
            ->post("{$this->apiUrl}/zones/{$this->zoneId}/dns_records", [
                'type' => 'CNAME',
                'name' => $subdomain,
                'content' => $this->baseDomain,  // CNAME to base domain
                'ttl' => 1,  // Auto TTL
                'proxied' => true,  // Enable Cloudflare proxy
            ]);

        if (!$response->successful()) {
            $error = $response->json();

            // Check if record already exists (error code 81057)
            if (isset($error['errors'][0]['code']) && $error['errors'][0]['code'] === 81057) {
                Log::info('DNS record already exists', [
                    'subdomain' => $subdomain,
                    'full_domain' => "{$subdomain}.{$this->baseDomain}",
                ]);

                // Try to get existing record
                $existingRecord = $this->getSubdomainRecord($subdomain);
                if ($existingRecord) {
                    return $existingRecord;
                }

                return ['id' => null, 'already_exists' => true];
            }

            Log::error('Failed to create DNS record', [
                'subdomain' => $subdomain,
                'error' => $error,
            ]);

            throw new \Exception("Failed to create DNS record: " . ($error['errors'][0]['message'] ?? $response->body()));
        }

        $result = $response->json('result');

        Log::info('DNS record created', [
            'subdomain' => $subdomain,
            'full_domain' => "{$subdomain}.{$this->baseDomain}",
            'record_id' => $result['id'] ?? null,
        ]);

        return $result;
    }

    /**
     * Get an existing DNS record for a subdomain
     */
    public function getSubdomainRecord(string $subdomain): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $response = Http::withToken($this->apiToken)
            ->get("{$this->apiUrl}/zones/{$this->zoneId}/dns_records", [
                'name' => "{$subdomain}.{$this->baseDomain}",
            ]);

        if (!$response->successful()) {
            return null;
        }

        $records = $response->json('result', []);

        return !empty($records) ? $records[0] : null;
    }

    /**
     * Delete a DNS record
     */
    public function deleteSubdomainRecord(string $recordId): bool
    {
        if (!$this->isConfigured() || empty($recordId)) {
            return false;
        }

        $response = Http::withToken($this->apiToken)
            ->delete("{$this->apiUrl}/zones/{$this->zoneId}/dns_records/{$recordId}");

        if ($response->successful()) {
            Log::info('DNS record deleted', [
                'record_id' => $recordId,
            ]);
            return true;
        }

        Log::error('Failed to delete DNS record', [
            'record_id' => $recordId,
            'error' => $response->json(),
        ]);

        return false;
    }

    /**
     * Check if a subdomain already exists (in Cloudflare)
     */
    public function subdomainExistsInCloudflare(string $subdomain): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = Http::withToken($this->apiToken)
            ->get("{$this->apiUrl}/zones/{$this->zoneId}/dns_records", [
                'type' => 'CNAME',
                'name' => "{$subdomain}.{$this->baseDomain}",
            ]);

        if (!$response->successful()) {
            return false;
        }

        return count($response->json('result', [])) > 0;
    }

    /**
     * Get the base domain
     */
    public function getBaseDomain(): string
    {
        return $this->baseDomain;
    }

    /**
     * Build full domain from subdomain
     */
    public function getFullDomain(string $subdomain): string
    {
        return "{$subdomain}.{$this->baseDomain}";
    }

    /**
     * Get list of reserved subdomains that cannot be used
     */
    public function getReservedSubdomains(): array
    {
        return [
            'www',
            'mail',
            'ftp',
            'admin',
            'api',
            'app',
            'cdn',
            'static',
            'assets',
            'test',
            'demo',
            'staging',
            'dev',
            'core',
            'panel',
            'dashboard',
            'login',
            'register',
            'auth',
            'oauth',
            'shop',
            'store',
            'help',
            'support',
            'docs',
            'status',
            'blog',
            'news',
        ];
    }

    /**
     * Validate subdomain format
     */
    public function isValidSubdomain(string $subdomain): bool
    {
        // Must be 3-63 characters
        if (strlen($subdomain) < 3 || strlen($subdomain) > 63) {
            return false;
        }

        // Can only contain lowercase letters, numbers, and hyphens
        // Cannot start or end with a hyphen
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain)) {
            return false;
        }

        // Cannot be a reserved subdomain
        if (in_array($subdomain, $this->getReservedSubdomains())) {
            return false;
        }

        return true;
    }
}
