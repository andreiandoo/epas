<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'order_id',
        'ticket_type_id',
        'performance_id',
        'code',
        'status',
        'seat_label',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function event()
    {
        return $this->hasOneThrough(
            \App\Models\Event::class,
            \App\Models\TicketType::class,
            'id',            // TicketType key
            'id',            // Event key
            'ticket_type_id',// FK pe tickets
            'event_id'       // FK pe ticket_types
        );
    }
}
