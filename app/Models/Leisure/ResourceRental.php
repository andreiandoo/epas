<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rental session: ticket + physical resource + start/end timestamps.
 * `overtime_minutes` and `overtime_surcharge_cents` are populated at end()
 * by RentalService — kept on the row for fast reporting without recompute.
 */
class ResourceRental extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'physical_resource_id',
        'started_by_user_id',
        'ended_by_user_id',
        'started_at',
        'planned_end_at',
        'ended_at',
        'overtime_minutes',
        'overtime_surcharge_cents',
        'surcharge_paid',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'ended_at' => 'datetime',
        'overtime_minutes' => 'integer',
        'overtime_surcharge_cents' => 'integer',
        'surcharge_paid' => 'boolean',
    ];

    protected $appends = ['is_active', 'is_overdue', 'elapsed_minutes', 'current_overtime_minutes'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function physicalResource(): BelongsTo
    {
        return $this->belongsTo(PhysicalResource::class);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->ended_at === null;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_active && $this->planned_end_at !== null && CarbonImmutable::now()->greaterThan($this->planned_end_at);
    }

    public function getElapsedMinutesAttribute(): int
    {
        if (! $this->started_at) return 0;
        $end = $this->ended_at ?? CarbonImmutable::now();
        return (int) $this->started_at->diffInMinutes($end);
    }

    public function getCurrentOvertimeMinutesAttribute(): int
    {
        if (! $this->planned_end_at) return 0;
        $checkpoint = $this->ended_at ?? CarbonImmutable::now();
        if ($checkpoint->lessThanOrEqualTo($this->planned_end_at)) {
            return 0;
        }
        return (int) $this->planned_end_at->diffInMinutes($checkpoint);
    }

    public function scopeActive($q)
    {
        return $q->whereNull('ended_at');
    }

    public function scopeOverdue($q)
    {
        return $q->whereNull('ended_at')->where('planned_end_at', '<', now());
    }
}
