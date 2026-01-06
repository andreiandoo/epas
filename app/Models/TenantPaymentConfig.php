<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPaymentConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'processor',
        'mode',
        // Stripe
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        // Netopia
        'netopia_api_key',
        'netopia_signature',
        'netopia_public_key',
        // Euplatesc
        'euplatesc_merchant_id',
        'euplatesc_secret_key',
        // PayU
        'payu_merchant_id',
        'payu_secret_key',
        // Revolut
        'revolut_api_key',
        'revolut_merchant_id',
        'revolut_webhook_secret',
        // PayPal
        'paypal_client_id',
        'paypal_client_secret',
        'paypal_webhook_id',
        // Klarna
        'klarna_api_username',
        'klarna_api_password',
        'klarna_region',
        // SMS Payment
        'sms_twilio_sid',
        'sms_twilio_auth_token',
        'sms_twilio_phone_number',
        'sms_fallback_processor',
        // Settings
        'is_active',
        'additional_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_config' => 'array',
        // Encrypt sensitive data
        'stripe_secret_key' => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
        'netopia_api_key' => 'encrypted',
        'netopia_signature' => 'encrypted',
        'netopia_public_key' => 'encrypted',
        'euplatesc_secret_key' => 'encrypted',
        'payu_secret_key' => 'encrypted',
        // Revolut
        'revolut_api_key' => 'encrypted',
        'revolut_webhook_secret' => 'encrypted',
        // PayPal
        'paypal_client_secret' => 'encrypted',
        // Klarna
        'klarna_api_password' => 'encrypted',
        // SMS Payment (Twilio)
        'sms_twilio_auth_token' => 'encrypted',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get decrypted keys based on mode
     */
    public function getActiveKeys(): array
    {
        $keys = [];
        $additionalConfig = $this->additional_config ?? [];

        switch ($this->processor) {
            case 'stripe':
                // Check mode - use test credentials if in test mode
                if ($this->mode === 'test') {
                    $keys = [
                        'publishable_key' => $additionalConfig['stripe_test_publishable_key'] ?? null,
                        'secret_key' => $additionalConfig['stripe_test_secret_key'] ?? null,
                        'webhook_secret' => $additionalConfig['stripe_test_webhook_secret'] ?? null,
                    ];
                } else {
                    // Live mode - use main columns
                    $keys = [
                        'publishable_key' => $this->stripe_publishable_key,
                        'secret_key' => $this->stripe_secret_key,
                        'webhook_secret' => $this->stripe_webhook_secret,
                    ];
                }
                break;

            case 'netopia':
                $keys = [
                    'api_key' => $this->netopia_api_key,
                    'signature' => $this->netopia_signature,
                    'public_key' => $this->netopia_public_key,
                ];
                break;

            case 'euplatesc':
                $keys = [
                    'merchant_id' => $this->euplatesc_merchant_id,
                    'secret_key' => $this->euplatesc_secret_key,
                ];
                break;

            case 'payu':
                $keys = [
                    'merchant_id' => $this->payu_merchant_id,
                    'secret_key' => $this->payu_secret_key,
                ];
                break;

            case 'revolut':
                if ($this->mode === 'test') {
                    $keys = [
                        'api_key' => $additionalConfig['revolut_test_api_key'] ?? $this->revolut_api_key,
                        'merchant_id' => $additionalConfig['revolut_test_merchant_id'] ?? $this->revolut_merchant_id,
                        'webhook_secret' => $additionalConfig['revolut_test_webhook_secret'] ?? $this->revolut_webhook_secret,
                    ];
                } else {
                    $keys = [
                        'api_key' => $this->revolut_api_key,
                        'merchant_id' => $this->revolut_merchant_id,
                        'webhook_secret' => $this->revolut_webhook_secret,
                    ];
                }
                break;

            case 'paypal':
                if ($this->mode === 'test') {
                    $keys = [
                        'client_id' => $additionalConfig['paypal_sandbox_client_id'] ?? $this->paypal_client_id,
                        'client_secret' => $additionalConfig['paypal_sandbox_client_secret'] ?? $this->paypal_client_secret,
                        'webhook_id' => $additionalConfig['paypal_sandbox_webhook_id'] ?? $this->paypal_webhook_id,
                    ];
                } else {
                    $keys = [
                        'client_id' => $this->paypal_client_id,
                        'client_secret' => $this->paypal_client_secret,
                        'webhook_id' => $this->paypal_webhook_id,
                    ];
                }
                break;

            case 'klarna':
                if ($this->mode === 'test') {
                    $keys = [
                        'api_username' => $additionalConfig['klarna_playground_username'] ?? $this->klarna_api_username,
                        'api_password' => $additionalConfig['klarna_playground_password'] ?? $this->klarna_api_password,
                        'region' => $this->klarna_region ?? 'eu',
                    ];
                } else {
                    $keys = [
                        'api_username' => $this->klarna_api_username,
                        'api_password' => $this->klarna_api_password,
                        'region' => $this->klarna_region ?? 'eu',
                    ];
                }
                break;

            case 'sms':
                $keys = [
                    'twilio_sid' => $this->sms_twilio_sid,
                    'twilio_auth_token' => $this->sms_twilio_auth_token,
                    'twilio_phone_number' => $this->sms_twilio_phone_number,
                    'fallback_processor' => $this->sms_fallback_processor ?? 'stripe',
                ];
                break;
        }

        return $keys;
    }

    /**
     * Check if configuration is complete
     */
    public function isConfigured(): bool
    {
        $keys = $this->getActiveKeys();

        return !empty(array_filter($keys, fn($value) => !empty($value)));
    }
}
