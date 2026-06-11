<?php

namespace App\Models\Integrations\Square;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SquareOrder extends Model
{
    protected $fillable = [
        'connection_id',
        'order_id',
        'location_id',
        'state',
        'total_money_cents',
        'currency',
        'line_items',
        'fulfillments',
        'source',
        'local_type',
        'local_id',
        'created_at_square',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'line_items' => 'array',
        'fulfillments' => 'array',
        'metadata' => 'array',
        'created_at_square' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SquareConnection::class, 'connection_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SquarePayment::class, 'square_order_id');
    }

    public function getTotalAttribute(): float
    {
        return $this->total_money_cents / 100;
    }

    public function isCompleted(): bool
    {
        return $this->state === 'COMPLETED';
    }

    public function isOpen(): bool
    {
        return $this->state === 'OPEN';
    }
}
