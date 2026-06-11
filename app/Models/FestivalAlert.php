<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalAlert extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'alert_type',
        'severity',
        'title',
        'message',
        'affected_stage_ids',
        'affected_day_ids',
        'channels',
        'status',
        'published_at',
        'resolved_at',
        'expires_at',
        'resolved_by',
        'resolution_notes',
        'meta',
    ];

    protected $casts = [
        'affected_stage_ids' => 'array',
        'affected_day_ids'   => 'array',
        'channels'           => 'array',
        'published_at'       => 'datetime',
        'resolved_at'        => 'datetime',
        'expires_at'         => 'datetime',
        'meta'               => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function publish(): void
    {
        $this->update([
            'status'       => 'active',
            'published_at' => now(),
        ]);
    }

    public function resolve(?string $resolvedBy = null, ?string $notes = null): void
    {
        $this->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'resolved_by'      => $resolvedBy,
            'resolution_notes' => $notes,
        ]);
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isCritical(): bool
    {
        return in_array($this->severity, ['critical', 'emergency']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function scopeCritical($query)
    {
        return $query->whereIn('severity', ['critical', 'emergency']);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('alert_type', $type);
    }
}
