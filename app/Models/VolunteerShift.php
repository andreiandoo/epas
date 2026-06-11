<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerShift extends Model
{
    protected $fillable = [
        'festival_volunteer_id',
        'tenant_id',
        'festival_day_id',
        'role',
        'zone',
        'starts_at',
        'ends_at',
        'checked_in_at',
        'checked_out_at',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
        'meta'           => 'array',
    ];

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(FestivalVolunteer::class, 'festival_volunteer_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function festivalDay(): BelongsTo
    {
        return $this->belongsTo(FestivalDay::class);
    }

    public function checkIn(): void
    {
        $this->update([
            'status'        => 'checked_in',
            'checked_in_at' => now(),
        ]);
    }

    public function checkOut(): void
    {
        $this->update([
            'status'         => 'completed',
            'checked_out_at' => now(),
        ]);
    }

    public function getDurationHoursAttribute(): float
    {
        return $this->starts_at->diffInMinutes($this->ends_at) / 60;
    }

    public function getActualHoursAttribute(): ?float
    {
        if (!$this->checked_in_at || !$this->checked_out_at) {
            return null;
        }

        return $this->checked_in_at->diffInMinutes($this->checked_out_at) / 60;
    }

    public function isActive(): bool
    {
        return $this->status === 'checked_in';
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function scopeForDay($query, int $dayId)
    {
        return $query->where('festival_day_id', $dayId);
    }

    public function scopeForZone($query, string $zone)
    {
        return $query->where('zone', $zone);
    }
}
