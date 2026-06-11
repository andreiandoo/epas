<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApplePayDomainService
{
    /**
     * Register a domain for Apple Pay with Stripe
     */
    public function registerDomain(Tenant $tenant, string $domain): array
    {
        $config = $tenant->activePaymentConfig();

        if (!$config || $config->processor !== 'stripe') {
            return [
                'success' => false,
                'message' => 'Stripe is not configured for this tenant',
            ];
        }

        $keys = $config->getActiveKeys();
        $secretKey = $keys['secret_key'] ?? null;

        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Stripe secret key not configured',
            ];
        }

        try {
            // Use Stripe API directly to register the domain
            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->post('https://api.stripe.com/v1/apple_pay/domains', [
                    'domain_name' => $domain,
                ]);

            if ($response->successful()) {
                // Store the registered domain in additional_config
                $additionalConfig = $config->additional_config ?? [];
                $registeredDomains = $additionalConfig['apple_pay_domains'] ?? [];

                if (!in_array($domain, $registeredDomains)) {
                    $registeredDomains[] = $domain;
                    $additionalConfig['apple_pay_domains'] = $registeredDomains;
                    $config->update(['additional_config' => $additionalConfig]);
                }

                return [
                    'success' => true,
                    'message' => "Domain '{$domain}' registered for Apple Pay",
                    'data' => $response->json(),
                ];
            }

            $error = $response->json();
            $errorMessage = $error['error']['message'] ?? 'Unknown error';

            // Check if domain is already registered
            if (str_contains($errorMessage, 'already been registered')) {
                return [
                    'success' => true,
                    'message' => "Domain '{$domain}' is already registered for Apple Pay",
                ];
            }

            return [
                'success' => false,
                'message' => $errorMessage,
            ];

        } catch (\Exception $e) {
            Log::error('Apple Pay domain registration failed', [
                'tenant_id' => $tenant->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to register domain: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List registered Apple Pay domains for a tenant
     */
    public function listDomains(Tenant $tenant): array
    {
        $config = $tenant->activePaymentConfig();

        if (!$config || $config->processor !== 'stripe') {
            return [];
        }

        $keys = $config->getActiveKeys();
        $secretKey = $keys['secret_key'] ?? null;

        if (!$secretKey) {
            return [];
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->get('https://api.stripe.com/v1/apple_pay/domains', [
                    'limit' => 100,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Failed to list Apple Pay domains', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Delete a registered Apple Pay domain
     */
    public function deleteDomain(Tenant $tenant, string $domainId): array
    {
        $config = $tenant->activePaymentConfig();

        if (!$config || $config->processor !== 'stripe') {
            return [
                'success' => false,
                'message' => 'Stripe is not configured',
            ];
        }

        $keys = $config->getActiveKeys();
        $secretKey = $keys['secret_key'] ?? null;

        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Stripe secret key not configured',
            ];
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->delete("https://api.stripe.com/v1/apple_pay/domains/{$domainId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Domain removed from Apple Pay',
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'message' => $error['error']['message'] ?? 'Failed to delete domain',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the Apple Pay verification file content from Stripe
     */
    public static function getVerificationFileContent(): string
    {
        // This is the standard Apple Pay domain verification file content
        // It's the same for all Stripe accounts
        try {
            $response = Http::get('https://stripe.com/files/apple-pay/apple-developer-merchantid-domain-association');

            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch Apple Pay verification file', ['error' => $e->getMessage()]);
        }

        // Fallback to the standard content if fetch fails
        return '';
    }
}
