<?php

namespace App\Models\Leisure;

use App\Models\Tenant;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-date (and optionally per-hour-slot) capacity row for a leisure
 * TicketType. The `remaining` derived attribute is what the public availability
 * API exposes; `status` provides a coarse traffic-light label.
 */
class TicketTypeCapacity extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'ticket_type_id',
        'capacity_date',
        'time_slot_start',
        'time_slot_end',
        'capacity',
        'sold',
        'reserved',
        'is_closed',
        'price_override_cents',
        'note',
    ];

    protected $casts = [
        'capacity_date' => 'date',
        'time_slot_start' => 'datetime:H:i:s',
        'time_slot_end' => 'datetime:H:i:s',
        'capacity' => 'integer',
        'sold' => 'integer',
        'reserved' => 'integer',
        'is_closed' => 'boolean',
        'price_override_cents' => 'integer',
    ];

    protected $appends = ['remaining', 'status'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function getRemainingAttribute(): int
    {
        return max(0, (int) $this->capacity - (int) $this->sold - (int) $this->reserved);
    }

    /**
     * Coarse availability label for UI badges / calendar dots.
     */
    public function getStatusAttribute(): string
    {
        if ($this->is_closed) {
            return 'closed';
        }
        if ($this->capacity <= 0) {
            return 'unavailable';
        }
        $remaining = $this->remaining;
        if ($remaining === 0) {
            return 'sold_out';
        }
        if ($remaining < ($this->capacity * 0.2)) {
            return 'limited';
        }
        return 'available';
    }

    public function scopeForDate($q, $date)
    {
        return $q->whereDate('capacity_date', $date);
    }

    public function scopeBetween($q, $start, $end)
    {
        return $q->whereBetween('capacity_date', [$start, $end]);
    }

    public function scopeWholeDay($q)
    {
        return $q->whereNull('time_slot_start');
    }

    public function scopeWithSlots($q)
    {
        return $q->whereNotNull('time_slot_start');
    }
}
