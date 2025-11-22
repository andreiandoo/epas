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

        switch ($this->processor) {
            case 'stripe':
                $keys = [
                    'publishable_key' => $this->stripe_publishable_key,
                    'secret_key' => $this->stripe_secret_key,
                    'webhook_secret' => $this->stripe_webhook_secret,
                ];
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
