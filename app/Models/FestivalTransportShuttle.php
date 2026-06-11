<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalTransportShuttle extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'route_code',
        'departure_location',
        'arrival_location',
        'departure_lat',
        'departure_lng',
        'arrival_lat',
        'arrival_lng',
        'duration_minutes',
        'capacity',
        'price_cents',
        'currency',
        'schedule',
        'operating_days',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'departure_lat'    => 'decimal:7',
        'departure_lng'    => 'decimal:7',
        'arrival_lat'      => 'decimal:7',
        'arrival_lng'      => 'decimal:7',
        'duration_minutes' => 'integer',
        'capacity'         => 'integer',
        'price_cents'      => 'integer',
        'schedule'         => 'array',
        'operating_days'   => 'array',
        'meta'             => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function isFree(): bool
    {
        return $this->price_cents === 0;
    }

    public function getDeparturesForDay(int $dayId): array
    {
        if (!$this->schedule) {
            return [];
        }

        foreach ($this->schedule as $entry) {
            if (($entry['day_id'] ?? null) == $dayId) {
                return $entry['departures'] ?? [];
            }
        }

        return [];
    }

    public function operatesOnDay(int $dayId): bool
    {
        if (empty($this->operating_days)) {
            return true;
        }

        return in_array($dayId, $this->operating_days);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
