<?php

namespace App\Models\Marketplace;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MarketplacePayoutItem Model
 *
 * Represents a single order included in a payout.
 * Contains the commission breakdown for that order.
 */
class MarketplacePayoutItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_id',
        'order_id',
        'order_total',
        'tixello_fee',
        'marketplace_fee',
        'organizer_amount',
        'refund_amount',
        'is_refunded',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'tixello_fee' => 'decimal:2',
        'marketplace_fee' => 'decimal:2',
        'organizer_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'is_refunded' => 'boolean',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the payout this item belongs to.
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketplacePayout::class, 'payout_id');
    }

    /**
     * Get the order this item represents.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get total fees (Tixello + Marketplace).
     */
    public function getTotalFees(): float
    {
        return (float) ($this->tixello_fee + $this->marketplace_fee);
    }

    /**
     * Get fee percentage of order total.
     */
    public function getFeePercentage(): float
    {
        if ($this->order_total <= 0) {
            return 0;
        }
        return ($this->getTotalFees() / $this->order_total) * 100;
    }

    /**
     * Get net amount after refund.
     */
    public function getNetAmount(): float
    {
        return (float) ($this->organizer_amount - $this->refund_amount);
    }
}
