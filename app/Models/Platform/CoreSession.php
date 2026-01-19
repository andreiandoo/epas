<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant;

class CoreSession extends Model
{
    protected $fillable = [
        'session_id',
        'customer_id',
        'tenant_id',
        'marketplace_event_id',
        'marketplace_client_id',
        'visitor_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'pageviews',
        'events',
        'is_bounce',
        'landing_page',
        'landing_page_type',
        'exit_page',
        'exit_page_type',
        'source',
        'medium',
        'campaign',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'gclid',
        'fbclid',
        'ttclid',
        'converted',
        'conversion_value',
        'conversion_type',
        'device_type',
        'browser',
        'os',
        'country_code',
        'city',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'pageviews' => 'integer',
        'events' => 'integer',
        'is_bounce' => 'boolean',
        'converted' => 'boolean',
        'conversion_value' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class, 'customer_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customerEvents(): HasMany
    {
        return $this->hasMany(CoreCustomerEvent::class, 'session_id', 'session_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        // Active = started recently (within 30 min) and not ended
        return $query->where('started_at', '>=', now()->subMinutes(30))
                     ->whereNull('ended_at');
    }

    public function scopeEnded($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeConverted($query)
    {
        return $query->where('converted', true);
    }

    public function scopeBounced($query)
    {
        return $query->where('is_bounce', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMarketplaceEvent($query, int $marketplaceEventId)
    {
        return $query->where('marketplace_event_id', $marketplaceEventId);
    }

    public function scopeForMarketplaceClient($query, int $marketplaceClientId)
    {
        return $query->where('marketplace_client_id', $marketplaceClientId);
    }

    public function scopeFromDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    public function scopeMobile($query)
    {
        return $query->where('device_type', 'mobile');
    }

    public function scopeDesktop($query)
    {
        return $query->where('device_type', 'desktop');
    }

    public function scopeTablet($query)
    {
        return $query->where('device_type', 'tablet');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('started_at', today());
    }

    public function scopeLastHours($query, int $hours)
    {
        return $query->where('started_at', '>=', now()->subHours($hours));
    }

    public function scopeLastDays($query, int $days)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    public function scopeWithClickId($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('gclid')
              ->orWhereNotNull('fbclid')
              ->orWhereNotNull('ttclid');
        });
    }

    public function scopeFromUtmSource($query, string $source)
    {
        return $query->where('utm_source', $source);
    }

    // Activity tracking
    public function recordPageView(string $pageUrl): void
    {
        $this->increment('pageviews');
        $this->update([
            'exit_page' => $pageUrl,
        ]);
    }

    public function endSession(): void
    {
        $duration = $this->started_at->diffInSeconds(now());

        $this->update([
            'ended_at' => now(),
            'duration_seconds' => $duration,
            'is_bounce' => $this->pageviews <= 1,
        ]);
    }

    public function markConverted(float $value = 0, ?string $type = null): void
    {
        $this->update([
            'converted' => true,
            'conversion_value' => $value,
            'conversion_type' => $type,
        ]);
    }

    // Helpers
    public function isActive(): bool
    {
        return is_null($this->ended_at) &&
               $this->started_at >= now()->subMinutes(30);
    }

    public function getDurationMinutes(): float
    {
        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInMinutes($endTime);
    }

    public function getDurationFormatted(): string
    {
        $seconds = $this->duration_seconds ?? $this->started_at->diffInSeconds(now());

        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $minutes . 'm ' . $remainingSeconds . 's';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return $hours . 'h ' . $remainingMinutes . 'm';
    }

    public function getTrafficSource(): string
    {
        if ($this->gclid) return 'Google Ads';
        if ($this->fbclid) return 'Facebook Ads';
        if ($this->ttclid) return 'TikTok Ads';

        if ($this->utm_source) {
            $source = ucfirst($this->utm_source);
            if ($this->utm_medium) {
                $source .= ' / ' . ucfirst($this->utm_medium);
            }
            return $source;
        }

        if ($this->source) {
            return ucfirst($this->source);
        }

        if ($this->referrer) {
            $host = parse_url($this->referrer, PHP_URL_HOST);
            if ($host) {
                $host = preg_replace('/^(www\.|m\.)/', '', $host);
                return ucfirst(explode('.', $host)[0]);
            }
        }

        return 'Direct';
    }

    public function getDeviceInfo(): string
    {
        $parts = [];

        if ($this->device_type) {
            $parts[] = ucfirst($this->device_type);
        }

        if ($this->browser) {
            $parts[] = $this->browser;
        }

        if ($this->os) {
            $parts[] = $this->os;
        }

        return implode(' • ', $parts);
    }

    public function getLocation(): string
    {
        $parts = array_filter([$this->city, $this->country_code]);
        return implode(', ', $parts) ?: 'Unknown';
    }

    // Static helpers
    public static function findOrCreateBySessionId(string $sessionId, array $attributes = []): self
    {
        return static::firstOrCreate(
            ['session_id' => $sessionId],
            array_merge($attributes, [
                'started_at' => now(),
                'pageviews' => 0,
                'events' => 0,
            ])
        );
    }

    public static function getActiveCount(?int $tenantId = null): int
    {
        $query = static::active();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query->count();
    }
}
