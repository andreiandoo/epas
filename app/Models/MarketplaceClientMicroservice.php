<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceClientMicroservice extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'microservice_id',
        'status',
        'is_active',
        'activated_at',
        'expires_at',
        'settings',
        'usage_stats',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'settings' => 'array',
        'usage_stats' => 'array',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function microservice(): BelongsTo
    {
        return $this->belongsTo(Microservice::class);
    }

    /**
     * Check if this payment method is active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a specific setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }

    /**
     * Get the settings schema from the microservice
     */
    public function getSettingsSchema(): array
    {
        return $this->microservice->metadata['settings_schema'] ?? [];
    }

    /**
     * Check if all required settings are configured
     */
    public function isConfigured(): bool
    {
        $schema = $this->getSettingsSchema();

        foreach ($schema as $field) {
            if ($field['required'] ?? false) {
                $value = $this->getSetting($field['key']);
                if (empty($value)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Scope for active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for payment category microservices
     */
    public function scopePaymentMethods($query)
    {
        return $query->whereHas('microservice', function ($q) {
            $q->where('category', 'payment');
        });
    }
}
