<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $fillable = [
        'tenant_id',
        'venue_id',
        'name',
        'slug',
        'description',
        'image_url',
        'capacity',
        'stage_type',
        'technical_specs',
        'location_coordinates',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'capacity'             => 'integer',
        'technical_specs'      => 'array',
        'location_coordinates' => 'array',
        'is_active'            => 'boolean',
        'meta'                 => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function lineupSlots(): HasMany
    {
        return $this->hasMany(FestivalLineupSlot::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
