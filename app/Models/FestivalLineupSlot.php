<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalLineupSlot extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_day_id',
        'stage_id',
        'artist_id',
        'custom_artist_name',
        'description',
        'start_time',
        'end_time',
        'slot_type',
        'is_headliner',
        'is_secret_guest',
        'status',
        'display_position',
        'display_tier',
        'image_override_url',
        'meta',
    ];

    protected $casts = [
        'is_headliner'    => 'boolean',
        'is_secret_guest' => 'boolean',
        'meta'            => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function festivalDay(): BelongsTo
    {
        return $this->belongsTo(FestivalDay::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Get the display name (artist name or custom name).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_secret_guest) {
            return 'Secret Guest';
        }

        return $this->artist?->name ?? $this->custom_artist_name ?? 'TBA';
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeHeadliners($query)
    {
        return $query->where('is_headliner', true);
    }

    public function scopeByDisplayOrder($query)
    {
        return $query->orderBy('display_position')->orderBy('start_time');
    }
}
