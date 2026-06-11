<?php

namespace App\Models\Cashless;

use App\Enums\PricingComponentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRuleComponent extends Model
{
    protected $fillable = [
        'pricing_rule_id', 'component_type', 'label', 'amount_cents',
        'percentage', 'applies_on', 'sort_order', 'is_included_in_final', 'meta',
    ];

    protected $casts = [
        'component_type'      => PricingComponentType::class,
        'amount_cents'        => 'integer',
        'percentage'          => 'decimal:4',
        'sort_order'          => 'integer',
        'is_included_in_final' => 'boolean',
        'meta'                => 'array',
    ];

    public function pricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class);
    }

    /**
     * Calculate this component's contribution in cents.
     */
    public function calculateAmount(int $baseCents, int $subtotalCents = 0): int
    {
        if ($this->amount_cents !== null) {
            return $this->amount_cents;
        }

        if ($this->percentage !== null) {
            $applyOn = match ($this->applies_on) {
                'subtotal' => $subtotalCents,
                default    => $baseCents,
            };
            return (int) round($applyOn * $this->percentage / 100);
        }

        return 0;
    }
}
