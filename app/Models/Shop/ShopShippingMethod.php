<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ShopShippingMethod extends Model
{
    use HasUuids;

    protected $table = 'shop_shipping_methods';

    protected $fillable = [
        'zone_id',
        'name',
        'description',
        'provider',
        'calculation_type',
        'cost_cents',
        'cost_per_kg_cents',
        'min_order_cents',
        'max_order_cents',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'cost_cents' => 'integer',
        'cost_per_kg_cents' => 'integer',
        'min_order_cents' => 'integer',
        'max_order_cents' => 'integer',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShopShippingZone::class, 'zone_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // Cost Calculation

    public function calculateCost(int $orderTotalCents, int $totalWeightGrams = 0): int
    {
        switch ($this->calculation_type) {
            case 'free':
                return 0;

            case 'flat':
                return $this->cost_cents;

            case 'weight_based':
                if (!$this->cost_per_kg_cents || $totalWeightGrams <= 0) {
                    return $this->cost_cents;
                }
                $weightKg = ceil($totalWeightGrams / 1000);
                return (int) ($this->cost_cents + ($weightKg * $this->cost_per_kg_cents));

            case 'price_based':
                // Tiered pricing based on order total
                if ($this->min_order_cents && $orderTotalCents < $this->min_order_cents) {
                    return $this->cost_cents;
                }
                if ($this->max_order_cents && $orderTotalCents > $this->max_order_cents) {
                    return 0; // Free shipping above threshold
                }
                return $this->cost_cents;

            default:
                return $this->cost_cents;
        }
    }

    public function isAvailableForOrder(int $orderTotalCents, int $totalWeightGrams = 0, bool $hasPhysicalProducts = true): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // If no physical products, shipping is not needed (except for pickup options)
        if (!$hasPhysicalProducts && $this->provider !== 'pickup') {
            return false;
        }

        return true;
    }

    public function isFreeShipping(int $orderTotalCents): bool
    {
        if ($this->calculation_type === 'free') {
            return true;
        }

        // Check free shipping threshold
        if ($this->min_order_cents && $orderTotalCents >= $this->min_order_cents) {
            return $this->calculation_type === 'price_based' && $this->max_order_cents === null;
        }

        return false;
    }

    public function getEstimatedDeliveryText(): ?string
    {
        if (!$this->estimated_days_min && !$this->estimated_days_max) {
            return null;
        }

        if ($this->estimated_days_min === $this->estimated_days_max) {
            return "{$this->estimated_days_min} days";
        }

        if ($this->estimated_days_min && $this->estimated_days_max) {
            return "{$this->estimated_days_min}-{$this->estimated_days_max} days";
        }

        if ($this->estimated_days_min) {
            return "{$this->estimated_days_min}+ days";
        }

        return "Up to {$this->estimated_days_max} days";
    }

    // Accessors

    public function getCostAttribute(): float
    {
        return $this->cost_cents / 100;
    }

    public function getCostPerKgAttribute(): ?float
    {
        return $this->cost_per_kg_cents ? $this->cost_per_kg_cents / 100 : null;
    }

    public function getMinOrderAttribute(): ?float
    {
        return $this->min_order_cents ? $this->min_order_cents / 100 : null;
    }
}
