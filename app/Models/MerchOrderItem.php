<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchOrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'merch_product_id',
        'quantity',
        'unit_price_cents',
        'total_price_cents',
        'currency',
        'variant',
        'fulfillment_status',
        'tracking_number',
        'tracking_url',
        'meta',
    ];

    protected $casts = [
        'variant' => 'array',
        'meta' => 'array',
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'total_price_cents' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchProduct(): BelongsTo
    {
        return $this->belongsTo(MerchProduct::class);
    }

    /**
     * Get unit price in major currency units.
     */
    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_cents / 100;
    }

    /**
     * Get total price in major currency units.
     */
    public function getTotalPriceAttribute(): float
    {
        return $this->total_price_cents / 100;
    }

    /**
     * Get variant label for display.
     */
    public function getVariantLabelAttribute(): ?string
    {
        if (empty($this->variant)) {
            return null;
        }

        return collect($this->variant)
            ->map(fn ($value, $key) => "{$key}: {$value}")
            ->implode(', ');
    }
}
