<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Performance extends Model
{
    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the tenant through the event.
     */
    public function tenant(): BelongsTo
    {
        return $this->event->tenant();
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
     * Scope for upcoming performances.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())
            ->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhereNull('status'));
    }
}
