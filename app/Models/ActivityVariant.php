<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pricing variant for an Activity — the "ticket_type" of the activities world.
 *
 * One activity can have multiple variants:
 *   - Adult (95 RON, age 14+)
 *   - Copil (45 RON, age 4-13)
 *   - Grup 4 persoane (340 RON, capacity_share = 4)
 *
 * Field shape intentionally mirrors `ticket_types` so the checkout layer
 * can branch only on which FK (ticket_type_id vs activity_variant_id) is
 * populated and reuse all pricing / commission / perks code.
 */
class ActivityVariant extends Model
{
    use SoftDeletes, Translatable;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'activity_id',
        'name',
        'description',
        'sku',
        'price_cents',
        'currency',
        'min_age',
        'max_age',
        'capacity_share',
        'min_per_order',
        'max_per_order',
        'commission_type',
        'commission_rate',
        'commission_fixed',
        'commission_mode',
        'perks',
        'is_active',
        'is_refundable',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'perks' => 'array',
        'price_cents' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'capacity_share' => 'integer',
        'min_per_order' => 'integer',
        'max_per_order' => 'integer',
        'commission_rate' => 'decimal:2',
        'commission_fixed' => 'decimal:2',
        'is_active' => 'boolean',
        'is_refundable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Convenience: price in RON (or whichever currency) as decimal. Matches
     * TicketType::getPriceAttribute() shape so views can render either.
     */
    public function getPriceAttribute(): float
    {
        return $this->price_cents ? $this->price_cents / 100 : 0.0;
    }
}
