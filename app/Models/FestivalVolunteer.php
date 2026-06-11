<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestivalVolunteer extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'customer_id',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'id_number',
        'role',
        'department',
        'team_leader',
        'skills',
        'tshirt_size',
        'dietary_restrictions',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'confirmed_at',
        'checked_in_at',
        'assigned_zone_ids',
        'notes',
        'meta',
    ];

    protected $casts = [
        'date_of_birth'    => 'date',
        'confirmed_at'     => 'datetime',
        'checked_in_at'    => 'datetime',
        'assigned_zone_ids' => 'array',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(VolunteerShift::class);
    }

    public function upcomingShifts(): HasMany
    {
        return $this->shifts()->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function approve(): void
    {
        $this->update(['status' => 'approved']);
    }

    public function confirm(): void
    {
        $this->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function checkIn(): void
    {
        $this->update([
            'status'        => 'checked_in',
            'checked_in_at' => now(),
        ]);
    }

    public function getTotalShiftHoursAttribute(): float
    {
        return $this->shifts()
            ->where('status', 'completed')
            ->get()
            ->sum(fn ($shift) => $shift->starts_at->diffInMinutes($shift->ends_at) / 60);
    }

    public static function roleLabels(): array
    {
        return [
            'general'     => 'General',
            'security'    => 'Security',
            'medical'     => 'Medical',
            'logistics'   => 'Logistics',
            'hospitality' => 'Hospitality',
            'tech'        => 'Technical / Sound',
            'info'        => 'Info Point',
            'bar'         => 'Bar Staff',
            'cleanup'     => 'Cleanup Crew',
            'parking'     => 'Parking',
            'camping'     => 'Camping',
        ];
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['confirmed', 'checked_in', 'active']);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
