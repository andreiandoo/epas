<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingIntegration extends Model
{
    protected $fillable = [
        'tenant_id',
        'provider',
        'enabled',
        'consent_category',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the tenant that owns this integration
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to enabled integrations only
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to specific provider
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to specific consent category
     */
    public function scopeConsentCategory($query, string $category)
    {
        return $query->where('consent_category', $category);
    }

    /**
     * Get provider-specific ID (measurement_id, container_id, pixel_id)
     */
    public function getProviderId(): ?string
    {
        $settings = $this->settings ?? [];

        return match ($this->provider) {
            'ga4' => $settings['measurement_id'] ?? null,
            'gtm' => $settings['container_id'] ?? null,
            'meta' => $settings['pixel_id'] ?? null,
            'tiktok' => $settings['pixel_id'] ?? null,
            default => null,
        };
    }

    /**
     * Get injection location (head, body)
     */
    public function getInjectAt(): string
    {
        return $this->settings['inject_at'] ?? 'head';
    }

    /**
     * Get page scope (all, public, admin)
     */
    public function getPageScope(): string
    {
        return $this->settings['page_scope'] ?? 'public';
    }

    /**
     * Check if should inject on current page
     */
    public function shouldInjectOnPage(string $currentScope): bool
    {
        $pageScope = $this->getPageScope();

        if ($pageScope === 'all') {
            return true;
        }

        return $pageScope === $currentScope;
    }

    /**
     * Get all settings
     */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }
}
