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
}
