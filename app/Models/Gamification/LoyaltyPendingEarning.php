<?php

namespace App\Models\Gamification;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Points a paid marketplace order has earned, waiting to be credited. They are credited (moved to the points ledger)
 * once the activity has taken place plus the programme's confirmation days, and cancelled if the order is refunded
 * first. One row per order. See MarketplaceLoyaltyService.
 */
class LoyaltyPendingEarning extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_customer_id',
        'order_id',
        'points',
        'base_amount',
        'available_at',
        'status',
        'points_transaction_id',
    ];

    protected $casts = [
        'points' => 'integer',
        'base_amount' => 'decimal:2',
        'available_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
