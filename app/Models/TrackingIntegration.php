<?php

namespace App\Models;

use App\Models\MarketplaceClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TrackingIntegration extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'marketplace_organizer_id',
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
     * Get the marketplace organizer that owns this integration
     */
    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class);
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
     * Get provider-specific ID (measurement_id, container_id, pixel_id, conversion_id)
     */
    public function getProviderId(): ?string
    {
        $settings = $this->settings ?? [];

        return match ($this->provider) {
            'ga4' => $settings['measurement_id'] ?? null,
            'gtm' => $settings['container_id'] ?? null,
            'meta' => $settings['pixel_id'] ?? null,
            'tiktok' => $settings['pixel_id'] ?? null,
            // Google Ads conversion tracking — uses AW-XXXXXX format,
            // distinct from GA4's G-XXXXXX measurement IDs.
            'google_ads' => $settings['conversion_id'] ?? null,
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
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Server-side credential keys stored inside the `settings` JSON,
     * encrypted at rest. Kept separate from the public provider ID
     * (measurement_id, pixel_id, etc.) because we never want them to
     * leak into logs, responses, or the browser-facing config endpoint.
     */
    public const CREDENTIAL_KEYS = [
        'ga4' => 'api_secret',           // GA4 Measurement Protocol API secret
        'tiktok' => 'access_token',      // TikTok Events API access token
        'meta' => 'access_token',        // Meta CAPI access token (currently stored in facebook_capi_connections; reserved key)
        'google_ads' => 'developer_token', // reserved for later
    ];

    /**
     * Read a server-side credential (decrypted). Returns null when the
     * settings JSON has no credential stored for that key, or when
     * decryption fails (e.g. APP_KEY rotated). Silently falls back to
     * the raw value if it wasn't encrypted (defensive against partial
     * writes made before this helper existed).
     */
    public function getCredential(string $key): ?string
    {
        $val = $this->settings[$key] ?? null;
        if (!filled($val)) {
            return null;
        }
        try {
            return Crypt::decryptString($val);
        } catch (\Throwable $e) {
            Log::warning('TrackingIntegration credential decrypt failed, returning raw', [
                'integration_id' => $this->id,
                'provider' => $this->provider,
                'key' => $key,
                'reason' => $e->getMessage(),
            ]);
            return is_string($val) ? $val : null;
        }
    }

    /**
     * Store a server-side credential encrypted. Passing null/empty
     * removes the key from settings (used when the admin cleared the
     * password field explicitly).
     */
    public function setCredential(string $key, ?string $value): void
    {
        $settings = $this->settings ?? [];
        if (filled($value)) {
            $settings[$key] = Crypt::encryptString($value);
        } else {
            unset($settings[$key]);
        }
        $this->settings = $settings;
    }

    /**
     * Convenience helper for the default credential key of this
     * provider (GA4 → api_secret, TikTok → access_token, etc.).
     */
    public function getServerSideCredential(): ?string
    {
        $key = self::CREDENTIAL_KEYS[$this->provider] ?? null;
        return $key ? $this->getCredential($key) : null;
    }

    /**
     * True when this integration can push to a server-side conversion
     * API (has a valid provider ID + a stored credential). Consumers
     * use this to short-circuit dispatch when nothing is configured.
     */
    public function hasServerSideCredentials(): bool
    {
        return filled($this->getProviderId()) && filled($this->getServerSideCredential());
    }

}
