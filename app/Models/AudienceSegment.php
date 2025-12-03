<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudienceSegment extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'segment_type',
        'criteria',
        'source_segment_id',
        'customer_count',
        'last_synced_at',
        'status',
        'auto_refresh',
        'refresh_interval_hours',
    ];

    protected $casts = [
        'criteria' => 'array',
        'last_synced_at' => 'datetime',
        'auto_refresh' => 'boolean',
    ];

    const TYPE_DYNAMIC = 'dynamic';
    const TYPE_STATIC = 'static';
    const TYPE_LOOKALIKE = 'lookalike';

    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_ARCHIVED = 'archived';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sourceSegment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_segment_id');
    }

    public function lookalikeSegments(): HasMany
    {
        return $this->hasMany(self::class, 'source_segment_id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'audience_segment_customers')
            ->withPivot(['score', 'source', 'added_at'])
            ->withTimestamps();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AudienceCampaign::class, 'segment_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AudienceExport::class, 'segment_id');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDynamic($query)
    {
        return $query->where('segment_type', self::TYPE_DYNAMIC);
    }

    public function scopeNeedsRefresh($query)
    {
        return $query->where('auto_refresh', true)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhereRaw('last_synced_at < NOW() - INTERVAL refresh_interval_hours HOUR');
            });
    }

    /**
     * Check if segment is dynamic
     */
    public function isDynamic(): bool
    {
        return $this->segment_type === self::TYPE_DYNAMIC;
    }

    /**
     * Check if segment is static
     */
    public function isStatic(): bool
    {
        return $this->segment_type === self::TYPE_STATIC;
    }

    /**
     * Check if segment is lookalike
     */
    public function isLookalike(): bool
    {
        return $this->segment_type === self::TYPE_LOOKALIKE;
    }

    /**
     * Check if segment needs refresh based on interval
     */
    public function needsRefresh(): bool
    {
        if (!$this->auto_refresh || $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->addHours($this->refresh_interval_hours)->isPast();
    }

    /**
     * Get customers with high affinity scores
     */
    public function getHighAffinityCustomers(int $minScore = 80)
    {
        return $this->customers()
            ->wherePivot('score', '>=', $minScore)
            ->orderByPivot('score', 'desc');
    }

    /**
     * Add customer to segment
     */
    public function addCustomer(int $customerId, int $score = 100, string $source = 'rule'): void
    {
        $this->customers()->syncWithoutDetaching([
            $customerId => [
                'score' => $score,
                'source' => $source,
                'added_at' => now(),
            ]
        ]);
    }

    /**
     * Remove customer from segment
     */
    public function removeCustomer(int $customerId): void
    {
        $this->customers()->detach($customerId);
    }

    /**
     * Update customer count cache
     */
    public function refreshCustomerCount(): void
    {
        $this->update([
            'customer_count' => $this->customers()->count(),
            'last_synced_at' => now(),
        ]);
    }
}
