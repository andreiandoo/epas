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
            'revolut' => new RevolutProcessor($config),
            'paypal' => new PayPalProcessor($config),
            'klarna' => new KlarnaProcessor($config),
            'sms' => new SmsPaymentProcessor($config),
            'noda' => new NodaProcessor($config),
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
            'revolut' => [
                'name' => 'Revolut',
                'description' => 'Modern payment processing with Revolut Pay, cards, Apple Pay & Google Pay',
                'logo' => '/images/processors/revolut.svg',
                'supported_currencies' => ['EUR', 'GBP', 'USD', 'RON', 'PLN', 'CHF', 'and 25+ more'],
                'fees' => '1% + €0.20 per transaction (Revolut Pay), 2.8% + €0.20 (cards)',
            ],
            'paypal' => [
                'name' => 'PayPal',
                'description' => 'Global payment platform with PayPal, credit cards, and Pay Later options',
                'logo' => '/images/processors/paypal.svg',
                'supported_currencies' => ['EUR', 'USD', 'GBP', 'CAD', 'AUD', 'and 25+ more'],
                'fees' => '2.9% + €0.35 per transaction',
            ],
            'klarna' => [
                'name' => 'Klarna',
                'description' => 'Buy Now Pay Later solutions - Pay in 3, Pay in 30 days, financing',
                'logo' => '/images/processors/klarna.svg',
                'supported_currencies' => ['EUR', 'SEK', 'NOK', 'DKK', 'GBP', 'USD', 'CHF', 'PLN'],
                'fees' => 'Contact Klarna for pricing',
            ],
            'sms' => [
                'name' => 'SMS Payment',
                'description' => 'Send payment links via SMS - works with any configured payment processor',
                'logo' => '/images/processors/sms.svg',
                'supported_currencies' => ['All currencies supported by fallback processor'],
                'fees' => 'SMS costs + fallback processor fees',
            ],
            'noda' => [
                'name' => 'Noda Open Banking',
                'description' => 'Pay by bank - instant account-to-account payments via SEPA Instant (EUR) and Plăți Instant (RON)',
                'logo' => '/images/processors/noda.svg',
                'supported_currencies' => ['EUR', 'RON', 'GBP', 'PLN', 'CZK', 'BGN', 'HUF', 'SEK', 'DKK', 'NOK', 'CHF'],
                'fees' => 'From 0.1% per transaction - no chargebacks',
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
            'revolut' => [
                'revolut_api_key' => [
                    'label' => 'API Key (Secret Key)',
                    'type' => 'password',
                    'placeholder' => 'sk_...',
                    'required' => true,
                ],
                'revolut_merchant_id' => [
                    'label' => 'Merchant ID (Public Key)',
                    'type' => 'text',
                    'placeholder' => 'pk_...',
                    'required' => false,
                ],
                'revolut_webhook_secret' => [
                    'label' => 'Webhook Secret (Optional)',
                    'type' => 'password',
                    'placeholder' => 'Your Revolut webhook signing secret',
                    'required' => false,
                ],
            ],
            'paypal' => [
                'paypal_client_id' => [
                    'label' => 'Client ID',
                    'type' => 'text',
                    'placeholder' => 'Your PayPal client ID',
                    'required' => true,
                ],
                'paypal_client_secret' => [
                    'label' => 'Client Secret',
                    'type' => 'password',
                    'placeholder' => 'Your PayPal client secret',
                    'required' => true,
                ],
                'paypal_webhook_id' => [
                    'label' => 'Webhook ID (Optional)',
                    'type' => 'text',
                    'placeholder' => 'Your PayPal webhook ID for signature verification',
                    'required' => false,
                ],
            ],
            'klarna' => [
                'klarna_api_username' => [
                    'label' => 'API Username (UID)',
                    'type' => 'text',
                    'placeholder' => 'K12345_abcdef123456...',
                    'required' => true,
                ],
                'klarna_api_password' => [
                    'label' => 'API Password',
                    'type' => 'password',
                    'placeholder' => 'Your Klarna API password',
                    'required' => true,
                ],
                'klarna_region' => [
                    'label' => 'Region',
                    'type' => 'select',
                    'options' => [
                        'eu' => 'Europe (EU)',
                        'na' => 'North America (NA)',
                        'oc' => 'Oceania (OC)',
                    ],
                    'placeholder' => 'Select your Klarna region',
                    'required' => true,
                ],
            ],
            'sms' => [
                'sms_twilio_sid' => [
                    'label' => 'Twilio Account SID',
                    'type' => 'text',
                    'placeholder' => 'AC...',
                    'required' => true,
                ],
                'sms_twilio_auth_token' => [
                    'label' => 'Twilio Auth Token',
                    'type' => 'password',
                    'placeholder' => 'Your Twilio auth token',
                    'required' => true,
                ],
                'sms_twilio_phone_number' => [
                    'label' => 'Twilio Phone Number',
                    'type' => 'text',
                    'placeholder' => '+1234567890',
                    'required' => true,
                ],
                'sms_fallback_processor' => [
                    'label' => 'Fallback Payment Processor',
                    'type' => 'select',
                    'options' => [
                        'stripe' => 'Stripe',
                        'paypal' => 'PayPal',
                        'revolut' => 'Revolut',
                        'klarna' => 'Klarna',
                    ],
                    'placeholder' => 'Select the processor for actual payments',
                    'required' => true,
                ],
            ],
            'noda' => [
                'noda_api_key' => [
                    'label' => 'API Key',
                    'type' => 'password',
                    'placeholder' => 'Your Noda API key from noda.live/hub',
                    'required' => true,
                ],
                'noda_shop_id' => [
                    'label' => 'Shop ID',
                    'type' => 'text',
                    'placeholder' => 'Your Noda shop/merchant ID',
                    'required' => false,
                ],
                'noda_signature_key' => [
                    'label' => 'Webhook Signature Key (Optional)',
                    'type' => 'password',
                    'placeholder' => 'Key for verifying webhook signatures',
                    'required' => false,
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

            case 'revolut':
                if (!empty($data['revolut_api_key']) && !str_starts_with($data['revolut_api_key'], 'sk_')) {
                    $errors['revolut_api_key'] = 'Invalid Revolut API key format (should start with sk_).';
                }
                break;

            case 'paypal':
                // PayPal client IDs are typically alphanumeric strings
                if (!empty($data['paypal_client_id']) && strlen($data['paypal_client_id']) < 20) {
                    $errors['paypal_client_id'] = 'Invalid PayPal client ID format.';
                }
                break;

            case 'klarna':
                if (!empty($data['klarna_region']) && !in_array($data['klarna_region'], ['eu', 'na', 'oc'])) {
                    $errors['klarna_region'] = 'Invalid Klarna region. Must be eu, na, or oc.';
                }
                break;

            case 'sms':
                if (!empty($data['sms_twilio_sid']) && !str_starts_with($data['sms_twilio_sid'], 'AC')) {
                    $errors['sms_twilio_sid'] = 'Invalid Twilio Account SID format (should start with AC).';
                }
                if (!empty($data['sms_twilio_phone_number'])) {
                    // Basic phone number validation
                    $phone = preg_replace('/[^0-9+]/', '', $data['sms_twilio_phone_number']);
                    if (!str_starts_with($phone, '+') || strlen($phone) < 10) {
                        $errors['sms_twilio_phone_number'] = 'Phone number must be in E.164 format (e.g., +1234567890).';
                    }
                }
                if (!empty($data['sms_fallback_processor'])) {
                    $validProcessors = ['stripe', 'paypal', 'revolut', 'klarna', 'netopia', 'euplatesc', 'payu', 'noda'];
                    if (!in_array($data['sms_fallback_processor'], $validProcessors)) {
                        $errors['sms_fallback_processor'] = 'Invalid fallback processor selected.';
                    }
                }
                break;

            case 'noda':
                // Noda API keys are typically UUID format or alphanumeric strings
                if (!empty($data['noda_api_key']) && strlen($data['noda_api_key']) < 10) {
                    $errors['noda_api_key'] = 'Invalid Noda API key format.';
                }
                break;
        }

        return $errors;
    }
}
