<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant;

class CoreSession extends Model
{
    protected $fillable = [
        'session_token',
        'core_customer_id',
        'tenant_id',
        'visitor_id',
        'started_at',
        'last_activity_at',
        'ended_at',
        'page_views',
        'total_time_seconds',
        'entry_page',
        'exit_page',
        'landing_page',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'ttclid',
        'li_fat_id',
        'device_type',
        'device_brand',
        'device_model',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'screen_width',
        'screen_height',
        'viewport_width',
        'viewport_height',
        'ip_address',
        'country_code',
        'country_name',
        'region',
        'city',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'isp',
        'is_bot',
        'is_mobile',
        'is_tablet',
        'is_desktop',
        'is_converted',
        'conversion_value',
        'events_count',
        'bounce',
        'engaged',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
        'page_views' => 'integer',
        'total_time_seconds' => 'integer',
        'screen_width' => 'integer',
        'screen_height' => 'integer',
        'viewport_width' => 'integer',
        'viewport_height' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_bot' => 'boolean',
        'is_mobile' => 'boolean',
        'is_tablet' => 'boolean',
        'is_desktop' => 'boolean',
        'is_converted' => 'boolean',
        'conversion_value' => 'decimal:2',
        'events_count' => 'integer',
        'bounce' => 'boolean',
        'engaged' => 'boolean',
    ];

    public function coreCustomer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CoreCustomerEvent::class, 'session_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('last_activity_at', '>=', now()->subMinutes(30))
                     ->whereNull('ended_at');
    }

    public function scopeEnded($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeConverted($query)
    {
        return $query->where('is_converted', true);
    }

    public function scopeBounced($query)
    {
        return $query->where('bounce', true);
    }

    public function scopeEngaged($query)
    {
        return $query->where('engaged', true);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeFromDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    public function scopeMobile($query)
    {
        return $query->where('is_mobile', true);
    }

    public function scopeDesktop($query)
    {
        return $query->where('is_desktop', true);
    }

    public function scopeNotBot($query)
    {
        return $query->where('is_bot', false);
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
              ->orWhereNotNull('ttclid')
              ->orWhereNotNull('li_fat_id');
        });
    }

    public function scopeFromUtmSource($query, string $source)
    {
        return $query->where('utm_source', $source);
    }

    // Activity tracking
    public function recordActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function recordPageView(string $pageUrl): void
    {
        $this->increment('page_views');
        $this->update([
            'last_activity_at' => now(),
            'exit_page' => $pageUrl,
        ]);
    }

    public function endSession(): void
    {
        $duration = $this->started_at->diffInSeconds(now());

        $this->update([
            'ended_at' => now(),
            'total_time_seconds' => $duration,
            'bounce' => $this->page_views <= 1,
            'engaged' => $duration >= 10 || $this->page_views >= 2,
        ]);
    }

    public function markConverted(float $value = 0): void
    {
        $this->update([
            'is_converted' => true,
            'conversion_value' => $value,
        ]);
    }

    // Helpers
    public function isActive(): bool
    {
        return is_null($this->ended_at) &&
               $this->last_activity_at >= now()->subMinutes(30);
    }

    public function getDurationMinutes(): float
    {
        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInMinutes($endTime);
    }

    public function getDurationFormatted(): string
    {
        $seconds = $this->total_time_seconds ?? $this->started_at->diffInSeconds(now());

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
        if ($this->li_fat_id) return 'LinkedIn Ads';

        if ($this->utm_source) {
            $source = ucfirst($this->utm_source);
            if ($this->utm_medium) {
                $source .= ' / ' . ucfirst($this->utm_medium);
            }
            return $source;
        }

        if ($this->referrer) {
            $host = parse_url($this->referrer, PHP_URL_HOST);
            if ($host) {
                // Clean up common referrers
                $host = preg_replace('/^(www\.|m\.)/', '', $host);
                return ucfirst(explode('.', $host)[0]);
            }
        }

        return 'Direct';
    }

    public function getDeviceInfo(): string
    {
        $parts = [];

        if ($this->device_brand && $this->device_model) {
            $parts[] = $this->device_brand . ' ' . $this->device_model;
        } elseif ($this->device_type) {
            $parts[] = ucfirst($this->device_type);
        }

        if ($this->browser) {
            $browser = $this->browser;
            if ($this->browser_version) {
                $browser .= ' ' . explode('.', $this->browser_version)[0];
            }
            $parts[] = $browser;
        }

        if ($this->os) {
            $os = $this->os;
            if ($this->os_version) {
                $os .= ' ' . $this->os_version;
            }
            $parts[] = $os;
        }

        return implode(' • ', $parts);
    }

    public function getLocation(): string
    {
        $parts = array_filter([$this->city, $this->region, $this->country_code]);
        return implode(', ', $parts) ?: 'Unknown';
    }

    // Static helpers
    public static function findOrCreateByToken(string $token, array $attributes = []): self
    {
        return static::firstOrCreate(
            ['session_token' => $token],
            array_merge($attributes, [
                'started_at' => now(),
                'last_activity_at' => now(),
                'page_views' => 0,
                'events_count' => 0,
            ])
        );
    }

    public static function getActiveCount(?int $tenantId = null): int
    {
        $query = static::active()->notBot();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query->count();
    }
}
