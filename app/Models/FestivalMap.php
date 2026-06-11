<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestivalMap extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'image_url',
        'bounds',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'bounds'    => 'array',
        'is_active' => 'boolean',
        'meta'      => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(FestivalPointOfInterest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
