<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * External partner of a marketplace (e.g. a media site) with its own scoped key
 * to /api/partner/v1. The key is stored only as a hash; the plain value is shown
 * once, when it is generated.
 */
class MarketplacePartner extends Model
{
    public const MICROSERVICE = 'media-partners';

    public const SCOPES = [
        'events:read' => 'Citire evenimente',
        'artists:read' => 'Citire artiști',
    ];

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'status',
        'scopes',
        'allowed_ips',
        'rate_limit_per_minute',
        'settings',
        'webhook_url',
        'outbound_secret',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_ips' => 'array',
        'settings' => 'array',
        'rate_limit_per_minute' => 'integer',
        'last_used_at' => 'datetime',
        'outbound_secret' => 'encrypted',
    ];

    protected $hidden = [
        'api_key_hash',
        'outbound_secret',
    ];

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MarketplacePartnerDelivery::class);
    }

    /**
     * Whether event changes should be pushed to this partner.
     */
    public function wantsEventWebhooks(): bool
    {
        return $this->isActive()
            && !empty($this->webhook_url)
            && !empty($this->outbound_secret)
            && $this->hasScope('events:read');
    }

    /**
     * Replace the key. Returns the plain value, which cannot be recovered later.
     */
    public function regenerateApiKey(): string
    {
        $plain = 'ptk_' . Str::random(48);

        $this->forceFill([
            'api_key_hash' => hash('sha256', $plain),
            'api_key_prefix' => substr($plain, 0, 12),
        ])->save();

        return $plain;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function allowsIp(?string $ip): bool
    {
        $allowed = array_values(array_filter($this->allowed_ips ?? []));

        if (empty($allowed)) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowed);
    }

    /**
     * Append this partner's UTM parameters to a link on the marketplace site.
     */
    public function withUtm(string $url): string
    {
        $params = array_filter([
            'utm_source' => Arr::get($this->settings ?? [], 'utm.source') ?: $this->slug,
            'utm_medium' => Arr::get($this->settings ?? [], 'utm.medium') ?: 'referral',
            'utm_campaign' => Arr::get($this->settings ?? [], 'utm.campaign'),
        ]);

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
}
