<?php

namespace App\Models\AdsCampaign;

use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdsAudienceSegment extends Model
{
    protected $table = 'ads_audience_segments';

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'name',
        'description',
        'type',
        'source_config',
        'facebook_audience_id',
        'google_audience_id',
        'tiktok_audience_id',
        'estimated_size',
        'last_synced_at',
        'auto_sync',
        'sync_status',
    ];

    protected $casts = [
        'source_config' => 'array',
        'sync_status' => 'array',
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    const TYPE_CUSTOM = 'custom';
    const TYPE_WEBSITE_VISITORS = 'website_visitors';
    const TYPE_PAST_ATTENDEES = 'past_attendees';
    const TYPE_CART_ABANDONERS = 'cart_abandoners';
    const TYPE_LOOKALIKE = 'lookalike';
    const TYPE_ENGAGED_USERS = 'engaged_users';
    const TYPE_EMAIL_SUBSCRIBERS = 'email_subscribers';
    const TYPE_HIGH_VALUE = 'high_value';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function isSyncedToFacebook(): bool
    {
        return !empty($this->facebook_audience_id);
    }

    public function isSyncedToGoogle(): bool
    {
        return !empty($this->google_audience_id);
    }

    public function needsSync(): bool
    {
        if (!$this->auto_sync) return false;
        if (!$this->last_synced_at) return true;
        return $this->last_synced_at->diffInHours(now()) > 24;
    }

    public function getPlatformAudienceId(string $platform): ?string
    {
        return match ($platform) {
            'facebook', 'instagram' => $this->facebook_audience_id,
            'google' => $this->google_audience_id,
            'tiktok' => $this->tiktok_audience_id,
            default => null,
        };
    }

    public function setPlatformAudienceId(string $platform, string $id): void
    {
        $field = match ($platform) {
            'facebook', 'instagram' => 'facebook_audience_id',
            'google' => 'google_audience_id',
            'tiktok' => 'tiktok_audience_id',
            default => null,
        };

        if ($field) {
            $this->update([$field => $id]);
        }
    }

    public function updateSyncStatus(string $platform, string $status): void
    {
        $syncStatus = $this->sync_status ?? [];
        $syncStatus[$platform] = $status;
        $this->update([
            'sync_status' => $syncStatus,
            'last_synced_at' => now(),
        ]);
    }

    public function scopeAutoSync($query)
    {
        return $query->where('auto_sync', true);
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('auto_sync', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subDay());
            });
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
