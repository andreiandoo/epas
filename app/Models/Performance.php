<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        // Accessibility
        'has_audio_description',
        'has_sign_language',
        'has_closed_captions',
        'is_sensory_friendly',
        'has_wheelchair_access',
        'has_hearing_loop',
        'has_braille_program',
        'has_tactile_tour',
        'accessibility_notes',
    ];

    protected $casts = [
        'starts_at'             => 'datetime',
        'ends_at'               => 'datetime',
        'is_premiere'           => 'boolean',
        'special_guests'        => 'array',
        'notes'                 => 'array',
        'ticket_overrides'      => 'array',
        'capacity_override'     => 'integer',
        'has_audio_description' => 'boolean',
        'has_sign_language'     => 'boolean',
        'has_closed_captions'   => 'boolean',
        'is_sensory_friendly'   => 'boolean',
        'has_wheelchair_access' => 'boolean',
        'has_hearing_loop'      => 'boolean',
        'has_braille_program'   => 'boolean',
        'has_tactile_tour'      => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function seatingLayout(): HasOne
    {
        return $this->hasOne(Seating\EventSeatingLayout::class);
    }

    /**
     * Get effective price for a ticket type (override or null = base price).
     */
    public function getEffectivePrice(TicketType $tt): ?int
    {
        $overrides = collect($this->ticket_overrides ?? []);
        $match = $overrides->firstWhere('ticket_type_id', $tt->id);
        return $match['price_cents'] ?? null;
    }

    /**
     * Get effective quota for a ticket type (override or null = ticket_type quota).
     */
    public function getEffectiveQuota(TicketType $tt): ?int
    {
        $overrides = collect($this->ticket_overrides ?? []);
        $match = $overrides->firstWhere('ticket_type_id', $tt->id);
        return $match['quota'] ?? null;
    }

    /**
     * Check if this performance has a seating snapshot saved.
     */
    public function hasSeatingSnapshot(): bool
    {
        return $this->seatingLayout()->exists();
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

    /**
     * Scope for performances with any accessibility feature.
     */
    public function scopeAccessible($query)
    {
        return $query->where(function ($q) {
            $q->where('has_audio_description', true)
              ->orWhere('has_sign_language', true)
              ->orWhere('has_closed_captions', true)
              ->orWhere('is_sensory_friendly', true)
              ->orWhere('has_wheelchair_access', true)
              ->orWhere('has_hearing_loop', true)
              ->orWhere('has_braille_program', true)
              ->orWhere('has_tactile_tour', true);
        });
    }

    /**
     * Get list of active accessibility features for display.
     */
    public function getAccessibilityFeaturesAttribute(): array
    {
        $features = [];

        if ($this->has_audio_description) $features[] = 'audio_description';
        if ($this->has_sign_language) $features[] = 'sign_language';
        if ($this->has_closed_captions) $features[] = 'closed_captions';
        if ($this->is_sensory_friendly) $features[] = 'sensory_friendly';
        if ($this->has_wheelchair_access) $features[] = 'wheelchair_access';
        if ($this->has_hearing_loop) $features[] = 'hearing_loop';
        if ($this->has_braille_program) $features[] = 'braille_program';
        if ($this->has_tactile_tour) $features[] = 'tactile_tour';

        return $features;
    }
}
