<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component of a package: `quantity` x a product (and variant). The
 * package is sold at its own price; `allocated_price_cents` is the share of
 * that price counted for this component (reports, invoices).
 */
class ActivityPackageItem extends Model
{
    protected $fillable = [
        'package_activity_id',
        'component_activity_id',
        'component_variant_id',
        'quantity',
        'allocated_price_cents',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'allocated_price_cents' => 'integer',
        'sort_order' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'package_activity_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'component_activity_id');
    }

    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ActivityVariant::class, 'component_variant_id');
    }
}
