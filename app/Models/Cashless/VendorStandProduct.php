<?php

namespace App\Models\Cashless;

use App\Models\VendorProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorStandProduct extends Model
{
    protected $fillable = [
        'vendor_stand_id', 'vendor_product_id', 'is_available',
        'override_price_cents', 'sort_order', 'meta',
    ];

    protected $casts = [
        'is_available'         => 'boolean',
        'override_price_cents' => 'integer',
        'sort_order'           => 'integer',
        'meta'                 => 'array',
    ];

    public function stand(): BelongsTo { return $this->belongsTo(VendorStand::class, 'vendor_stand_id'); }
    public function product(): BelongsTo { return $this->belongsTo(VendorProduct::class, 'vendor_product_id'); }

    /**
     * Get the effective price (override or product default).
     */
    public function getEffectivePriceCents(): int
    {
        return $this->override_price_cents ?? $this->product->sale_price_cents ?? $this->product->price_cents;
    }
}
