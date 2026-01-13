<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'ticket_type_id',
        'performance_id',
        'event_id',
        'tenant_id',
        'marketplace_client_id',
        'marketplace_customer_id',
        'marketplace_event_id',
        'marketplace_ticket_type_id',
        'code',
        'barcode',
        'status',
        'seat_label',
        'price',
        'attendee_name',
        'attendee_email',
        'meta',
        'is_cancelled',
        'cancelled_at',
        'cancellation_reason',
        'refund_request_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
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

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(MarketplaceRefundRequest::class, 'refund_request_id');
    }

    /**
     * Cancel this ticket
     */
    public function cancel(string $reason = null, int $refundRequestId = null): void
    {
        $this->update([
            'is_cancelled' => true,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'refund_request_id' => $refundRequestId,
            'status' => 'cancelled',
        ]);
    }
}
