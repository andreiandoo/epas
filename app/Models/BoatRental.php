<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatRental extends Model
{
    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'boat_id',
        'order_id',
        'order_item_id',
        'rental_ticket_id',
        'access_ticket_id',
        'started_by_member_id',
        'started_at',
        'planned_end_at',
        'ended_at',
        'finalized_at',
        'calup_duration_minutes',
        'calup_unit_price',
        'calupuri_planned',
        'calupuri_actual',
        'extra_charge_total',
        'extra_ticket_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'ended_at' => 'datetime',
        'finalized_at' => 'datetime',
        'calup_unit_price' => 'decimal:2',
        'extra_charge_total' => 'decimal:2',
        'calupuri_planned' => 'integer',
        'calupuri_actual' => 'integer',
        'calup_duration_minutes' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(LeisureBoat::class, 'boat_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rentalTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'rental_ticket_id');
    }

    public function accessTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'access_ticket_id');
    }

    /**
     * Calupuri reale = ceil((ended_at - started_at) / calup_duration_minutes)
     */
    public function calculateActualCalupuri(): int
    {
        if (!$this->ended_at || !$this->started_at) return $this->calupuri_planned;
        $durationMinutes = $this->started_at->diffInMinutes($this->ended_at);
        $durationMinutes = max(1, $durationMinutes); // minim 1 min, deci minim 1 calup
        return (int) ceil($durationMinutes / max(1, $this->calup_duration_minutes));
    }
}
