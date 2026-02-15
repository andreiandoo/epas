<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AffiliateEventSource extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'marketplace_client_id',
        'name',
        'slug',
        'website_url',
        'logo_url',
        'description',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($source) {
            if (empty($source->slug)) {
                $source->slug = Str::slug($source->name);
            }
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'affiliate_event_source_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getEventsCountAttribute(): int
    {
        return $this->events()->count();
    }

    public function getActiveEventsCountAttribute(): int
    {
        return $this->events()->where('is_published', true)->count();
    }
}
