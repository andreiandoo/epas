<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use Translatable;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'start_date',
        'end_date',
        'status',
        'poster_url',
        'settings',
        'is_subscription_enabled',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'settings' => 'array',
        'is_subscription_enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SeasonSubscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active');
    }

    /**
     * Check if this season is currently active (date-wise).
     */
    public function isCurrent(): bool
    {
        $now = now()->toDateString();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    /**
     * Scope for active seasons.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for current seasons (by date).
     */
    public function scopeCurrent($query)
    {
        $now = now()->toDateString();
        return $query->where('start_date', '<=', $now)->where('end_date', '>=', $now);
    }

    /**
     * Get a display label (first available locale).
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;
        if (is_array($name)) {
            return $name['ro'] ?? $name['en'] ?? reset($name) ?? '';
        }
        return (string) ($name ?? '');
    }
}
