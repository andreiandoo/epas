<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MedicalIncident extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'festival_day_id',
        'incident_number',
        'severity',
        'category',
        'description',
        'location',
        'lat',
        'lng',
        'reported_at',
        'response_at',
        'response_time_minutes',
        'patient_name',
        'patient_age_group',
        'patient_gender',
        'patient_wristband_uid',
        'treatment_given',
        'outcome',
        'hospital_name',
        'ambulance_unit',
        'attending_medic',
        'status',
        'resolved_at',
        'staff_notes',
        'meta',
    ];

    protected $casts = [
        'lat'                   => 'decimal:7',
        'lng'                   => 'decimal:7',
        'reported_at'           => 'datetime',
        'response_at'           => 'datetime',
        'response_time_minutes' => 'integer',
        'resolved_at'           => 'datetime',
        'meta'                  => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $incident) {
            if (empty($incident->incident_number)) {
                $incident->incident_number = 'MED-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
            }

            if ($incident->reported_at && $incident->response_at) {
                $incident->response_time_minutes = $incident->reported_at->diffInMinutes($incident->response_at);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function festivalDay(): BelongsTo
    {
        return $this->belongsTo(FestivalDay::class);
    }

    public function respond(): void
    {
        $responseAt = now();
        $this->update([
            'status'                => 'in_progress',
            'response_at'           => $responseAt,
            'response_time_minutes' => $this->reported_at->diffInMinutes($responseAt),
        ]);
    }

    public function resolve(?string $notes = null): void
    {
        $this->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
            'staff_notes' => $notes ?? $this->staff_notes,
        ]);
    }

    public function isCritical(): bool
    {
        return in_array($this->severity, ['critical', 'fatal']);
    }

    public function requiresHospital(): bool
    {
        return in_array($this->outcome, ['ambulance_called', 'hospital_transport']);
    }

    public static function severityLabels(): array
    {
        return [
            'minor'    => 'Minor',
            'moderate' => 'Moderate',
            'serious'  => 'Serious',
            'critical' => 'Critical',
            'fatal'    => 'Fatal',
        ];
    }

    public static function categoryLabels(): array
    {
        return [
            'dehydration'   => 'Dehydration',
            'heat_stroke'   => 'Heat Stroke',
            'injury'        => 'Injury',
            'allergy'       => 'Allergic Reaction',
            'intoxication'  => 'Intoxication',
            'cardiac'       => 'Cardiac',
            'respiratory'   => 'Respiratory',
            'wound'         => 'Wound / Cut',
            'fracture'      => 'Fracture',
            'seizure'       => 'Seizure',
            'mental_health' => 'Mental Health',
            'other'         => 'Other',
        ];
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('severity', ['critical', 'fatal']);
    }

    public function scopeForDay($query, int $dayId)
    {
        return $query->where('festival_day_id', $dayId);
    }
}
