<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tour extends Model
{
    use Translatable;

    public array $translatable = ['description'];

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'artist_id',
        'name',
        'slug',
        'type',
        'status',
        'start_date',
        'end_date',
        'budget_cents',
        'currency',
        'poster_url',
        'description',
        'routing_notes',
        'meta',
    ];

    protected $casts = [
        'description' => 'array',
        'meta' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'budget_cents' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('event_date');
    }

    /**
     * Get budget in major currency units.
     */
    public function getBudgetAttribute(): ?float
    {
        return $this->budget_cents ? $this->budget_cents / 100 : null;
    }

    /**
     * Get the number of stops on this tour.
     */
    public function getStopCountAttribute(): int
    {
        return $this->events()->count();
    }

    /**
     * Check if tour is currently running.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'in_progress') {
            return false;
        }

        $now = now()->toDateString();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    public function scopePlanning($query)
    {
        return $query->where('status', 'planning');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeForArtist($query, int $artistId)
    {
        return $query->where('artist_id', $artistId);
    }
}
