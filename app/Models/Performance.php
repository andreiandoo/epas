<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Performance extends Model
{
    protected $fillable = [
        'event_id',
        'season_id',
        'starts_at',
        'ends_at',
        'door_time',
        'status',
        'label',
        'is_premiere',
        'special_guests',
        'notes',
        'ticket_overrides',
        'capacity_override',
    ];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'is_premiere'      => 'boolean',
        'special_guests'   => 'array',
        'notes'            => 'array',
        'ticket_overrides' => 'array',
        'capacity_override' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Check if this performance is in the past.
     */
    public function isPast(): bool
    {
        return $this->ends_at?->isPast() ?? $this->starts_at?->isPast() ?? false;
    }

    /**
     * Check if this performance is upcoming.
     */
    public function isUpcoming(): bool
    {
        return !$this->isPast() && $this->status !== 'cancelled';
    }

    /**
     * Get effective capacity (performance override or event default).
     */
    public function getEffectiveCapacity(): ?int
    {
        return $this->capacity_override ?? $this->event?->total_capacity;
    }

    /**
     * Scope for upcoming performances.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhereNull('status'));
    }

    /**
     * Scope for premieres.
     */
    public function scopePremieres($query)
    {
        return $query->where('is_premiere', true);
    }
}
