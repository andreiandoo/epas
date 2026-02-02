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
        'cost',
        'cost_per_kg',
        'min_order',
        'max_order',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'cost' => 'decimal:2',
        'cost_per_kg' => 'decimal:2',
        'min_order' => 'decimal:2',
        'max_order' => 'decimal:2',
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

    public function calculateCost(float $orderTotal, int $totalWeightGrams = 0): float
    {
        switch ($this->calculation_type) {
            case 'free':
                return 0;

            case 'flat':
                return $this->cost ?? 0;

            case 'weight_based':
                if (!$this->cost_per_kg || $totalWeightGrams <= 0) {
                    return $this->cost ?? 0;
                }
                $weightKg = ceil($totalWeightGrams / 1000);
                return round(($this->cost ?? 0) + ($weightKg * $this->cost_per_kg), 2);

            case 'price_based':
                // Tiered pricing based on order total
                if ($this->min_order && $orderTotal < $this->min_order) {
                    return $this->cost ?? 0;
                }
                if ($this->max_order && $orderTotal > $this->max_order) {
                    return 0; // Free shipping above threshold
                }
                return $this->cost ?? 0;

            default:
                return $this->cost ?? 0;
        }
    }

    public function isAvailableForOrder(float $orderTotal, int $totalWeightGrams = 0, bool $hasPhysicalProducts = true): bool
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

    public function isFreeShipping(float $orderTotal): bool
    {
        if ($this->calculation_type === 'free') {
            return true;
        }

        // Check free shipping threshold
        if ($this->min_order && $orderTotal >= $this->min_order) {
            return $this->calculation_type === 'price_based' && $this->max_order === null;
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
}
