<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestivalDay extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'event_id',
        'name',
        'date',
        'gates_open',
        'gates_close',
        'status',
        'description',
        'image_url',
        'capacity_override',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'date'              => 'date',
        'capacity_override' => 'integer',
        'meta'              => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function lineupSlots(): HasMany
    {
        return $this->hasMany(FestivalLineupSlot::class)->orderBy('start_time');
    }

    public function isPast(): bool
    {
        return $this->date->isPast();
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled');
    }
}
