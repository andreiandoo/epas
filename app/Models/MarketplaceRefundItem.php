<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceRefundItem extends Model
{
    protected $fillable = [
        'refund_request_id',
        'ticket_id',
        'ticket_type_id',
        'order_item_id',
        'face_value',
        'commission_amount',
        'refund_amount',
        'commission_refunded',
        'status',
    ];

    protected $casts = [
        'face_value' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'commission_refunded' => 'boolean',
    ];

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRefundRequest::class, 'refund_request_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Doar itemii care tin de evenimentul dat. O comanda cu bilete din mai
     * multe evenimente are o singura cerere de rambursare; fara filtrul asta
     * biletele altui eveniment ar intra in decontul evenimentului curent.
     * Itemii fara bilet sau cu bilet fara eveniment raman (date vechi).
     */
    public function scopeForEvent($query, ?int $eventId)
    {
        if (!$eventId) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->whereNotExists(function ($q) use ($eventId, $table) {
            $q->selectRaw('1')
                ->from('tickets')
                ->whereColumn('tickets.id', $table . '.ticket_id')
                ->where(fn ($w) => $w->whereNotNull('tickets.event_id')->orWhereNotNull('tickets.marketplace_event_id'))
                ->where(fn ($w) => $w->whereNull('tickets.event_id')->orWhere('tickets.event_id', '!=', $eventId))
                ->where(fn ($w) => $w->whereNull('tickets.marketplace_event_id')->orWhere('tickets.marketplace_event_id', '!=', $eventId));
        });
    }
}
