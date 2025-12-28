<?php

namespace App\Services\PaymentProcessors;

use App\Models\Tenant;
use App\Models\TenantPaymentConfig;

class PaymentProcessorFactory
{
    /**
     * Create a payment processor instance for a tenant
     *
     * @param Tenant $tenant
     * @return PaymentProcessorInterface
     * @throws \Exception
     */
    public static function make(Tenant $tenant): PaymentProcessorInterface
    {
        if (!$tenant->payment_processor) {
            throw new \Exception('No payment processor configured for this tenant');
        }

        $config = $tenant->activePaymentConfig();

        if (!$config) {
            throw new \Exception("Payment configuration not found for {$tenant->payment_processor}");
        }

        return self::makeFromConfig($config);
    }

    /**
     * Create a payment processor instance from a config
     *
     * @param TenantPaymentConfig $config
     * @return PaymentProcessorInterface
     * @throws \Exception
     */
    public static function makeFromConfig(TenantPaymentConfig $config): PaymentProcessorInterface
    {
        return match ($config->processor) {
            'stripe' => new StripeProcessor($config),
            'netopia' => new NetopiaProcessor($config),
            'euplatesc' => new EuplatescProcessor($config),
            'payu' => new PayUProcessor($config),
            default => throw new \Exception("Unsupported payment processor: {$config->processor}"),
        };
    }

    /**
     * Get available payment processors
     *
     * @return array
     */
    public static function getAvailableProcessors(): array
    {
        return [
            'stripe' => [
                'name' => 'Stripe',
                'description' => 'Accept payments worldwide with credit/debit cards',
                'logo' => '/images/processors/stripe.svg',
                'supported_currencies' => ['EUR', 'USD', 'GBP', 'RON', 'and 135+ more'],
                'fees' => '2.9% + €0.25 per transaction',
            ],
            'netopia' => [
                'name' => 'Netopia Payments (mobilPay)',
                'description' => 'Romanian payment gateway for local cards',
                'logo' => '/images/processors/netopia.svg',
                'supported_currencies' => ['RON', 'EUR', 'USD'],
                'fees' => 'Contact Netopia for pricing',
            ],
            'euplatesc' => [
                'name' => 'EuPlatesc',
                'description' => 'Romanian payment processor with competitive rates',
                'logo' => '/images/processors/euplatesc.svg',
                'supported_currencies' => ['RON', 'EUR', 'USD'],
                'fees' => 'Contact EuPlatesc for pricing',
            ],
            'payu' => [
                'name' => 'PayU',
                'description' => 'International payment gateway for Eastern Europe',
                'logo' => '/images/processors/payu.svg',
                'supported_currencies' => ['RON', 'EUR', 'USD', 'PLN', 'HUF'],
                'fees' => '2.99% + RON 0.39 per transaction',
            ],
        ];
    }

    /**
     * Get required configuration fields for a processor
     *
     * @param string $processor
     * @return array
     */
    public static function getRequiredFields(string $processor): array
    {
        return match ($processor) {
            'stripe' => [
                'stripe_publishable_key' => [
                    'label' => 'Publishable Key',
                    'type' => 'text',
                    'placeholder' => 'pk_test_... or pk_live_...',
                    'required' => true,
                ],
                'stripe_secret_key' => [
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'placeholder' => 'sk_test_... or sk_live_...',
                    'required' => true,
                ],
                'stripe_webhook_secret' => [
                    'label' => 'Webhook Secret (Optional)',
                    'type' => 'password',
                    'placeholder' => 'whsec_...',
                    'required' => false,
                ],
            ],
            'netopia' => [
                'netopia_signature' => [
                    'label' => 'Merchant Signature',
                    'type' => 'text',
                    'placeholder' => 'Your Netopia signature',
                    'required' => true,
                ],
                'netopia_api_key' => [
                    'label' => 'Private Key',
                    'type' => 'textarea',
                    'placeholder' => '-----BEGIN PRIVATE KEY-----...',
                    'required' => true,
                ],
                'netopia_public_key' => [
                    'label' => 'Public Certificate',
                    'type' => 'textarea',
                    'placeholder' => '-----BEGIN CERTIFICATE-----...',
                    'required' => true,
                ],
            ],
            'euplatesc' => [
                'euplatesc_merchant_id' => [
                    'label' => 'Merchant ID',
                    'type' => 'text',
                    'placeholder' => 'Your EuPlatesc merchant ID',
                    'required' => true,
                ],
                'euplatesc_secret_key' => [
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'placeholder' => 'Your EuPlatesc secret key',
                    'required' => true,
                ],
            ],
            'payu' => [
                'payu_merchant_id' => [
                    'label' => 'Merchant ID',
                    'type' => 'text',
                    'placeholder' => 'Your PayU merchant code',
                    'required' => true,
                ],
                'payu_secret_key' => [
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'placeholder' => 'Your PayU secret key',
                    'required' => true,
                ],
            ],
            default => [],
        };
    }

    /**
     * Validate processor configuration
     *
     * @param string $processor
     * @param array $data
     * @return array Validation errors (empty if valid)
     */
    public static function validateConfig(string $processor, array $data): array
    {
        $errors = [];
        $requiredFields = self::getRequiredFields($processor);

        foreach ($requiredFields as $field => $config) {
            if ($config['required'] && empty($data[$field])) {
                $errors[$field] = "The {$config['label']} field is required.";
            }
        }

        // Additional validation based on processor
        switch ($processor) {
            case 'stripe':
                if (!empty($data['stripe_publishable_key']) && !str_starts_with($data['stripe_publishable_key'], 'pk_')) {
                    $errors['stripe_publishable_key'] = 'Invalid Stripe publishable key format.';
                }
                if (!empty($data['stripe_secret_key']) && !str_starts_with($data['stripe_secret_key'], 'sk_')) {
                    $errors['stripe_secret_key'] = 'Invalid Stripe secret key format.';
                }
                if (!empty($data['stripe_webhook_secret']) && !str_starts_with($data['stripe_webhook_secret'], 'whsec_')) {
                    $errors['stripe_webhook_secret'] = 'Invalid Stripe webhook secret format.';
                }
                break;

            case 'netopia':
                if (!empty($data['netopia_api_key']) && !str_contains($data['netopia_api_key'], 'BEGIN PRIVATE KEY')) {
                    $errors['netopia_api_key'] = 'Private key must be in PEM format.';
                }
                if (!empty($data['netopia_public_key']) && !str_contains($data['netopia_public_key'], 'BEGIN CERTIFICATE')) {
                    $errors['netopia_public_key'] = 'Public certificate must be in PEM format.';
                }
                break;
        }

        return $errors;
    }
}
