<?php

namespace App\Models\Integrations\Square;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquarePayment extends Model
{
    protected $fillable = [
        'connection_id',
        'square_order_id',
        'payment_id',
        'order_id',
        'location_id',
        'amount_cents',
        'currency',
        'status',
        'source_type',
        'card_details',
        'receipt_url',
        'created_at_square',
        'metadata',
    ];

    protected $casts = [
        'card_details' => 'array',
        'metadata' => 'array',
        'created_at_square' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SquareConnection::class, 'connection_id');
    }

    public function squareOrder(): BelongsTo
    {
        return $this->belongsTo(SquareOrder::class, 'square_order_id');
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isCard(): bool
    {
        return $this->source_type === 'CARD';
    }
}
