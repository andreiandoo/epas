<?php

namespace App\Services\Cashless;

use App\Models\Cashless\PricingRule;
use App\Models\Cashless\SupplierProduct;
use App\Models\VendorProduct;

class PricingService
{
    /**
     * Find the applicable mandatory pricing rule for a vendor product.
     */
    public function getMandatoryPrice(VendorProduct $product): ?PricingRule
    {
        if (! $product->supplier_product_id) {
            return null;
        }

        $supplierProduct = $product->supplierProduct;
        if (! $supplierProduct) {
            return null;
        }

        // Try exact product match first
        $rule = PricingRule::where('festival_edition_id', $product->festival_edition_id)
            ->where('is_active', true)
            ->where('is_mandatory', true)
            ->where('supplier_product_id', $supplierProduct->id)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->first();

        if ($rule) return $rule;

        // Try brand match
        if ($supplierProduct->supplier_brand_id) {
            $rule = PricingRule::where('festival_edition_id', $product->festival_edition_id)
                ->where('is_active', true)
                ->where('is_mandatory', true)
                ->where('supplier_brand_id', $supplierProduct->supplier_brand_id)
                ->whereNull('supplier_product_id')
                ->where(function ($q) {
                    $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
                })
                ->first();

            if ($rule) return $rule;
        }

        return null;
    }

    /**
     * Enforce mandatory pricing on a vendor product.
     * Returns true if price was enforced.
     */
    public function enforcePricing(VendorProduct $product): bool
    {
        $rule = $this->getMandatoryPrice($product);

        if (! $rule) {
            return false;
        }

        $product->update([
            'sale_price_cents' => $rule->final_price_cents,
        ]);

        return true;
    }

    /**
     * Get the price breakdown for a pricing rule.
     *
     * @return array<array{label: string, type: string, amount_cents: int}>
     */
    public function getPriceBreakdown(PricingRule $rule): array
    {
        $components = $rule->components;
        $baseCents = 0;
        $subtotalCents = 0;
        $breakdown = [];

        foreach ($components as $component) {
            // Find base price for percentage calculations
            if ($component->component_type->value === 'base_price') {
                $baseCents = $component->amount_cents ?? 0;
            }
        }

        foreach ($components as $component) {
            $amount = $component->calculateAmount($baseCents, $subtotalCents);
            $subtotalCents += $amount;

            $breakdown[] = [
                'label'        => $component->label,
                'type'         => $component->component_type->value,
                'amount_cents' => $amount,
                'included'     => $component->is_included_in_final,
            ];
        }

        return $breakdown;
    }

    /**
     * Enforce all mandatory prices for an edition.
     *
     * @return array{enforced: int, skipped: int}
     */
    public function enforceAllForEdition(int $editionId): array
    {
        $products = VendorProduct::where('festival_edition_id', $editionId)
            ->whereNotNull('supplier_product_id')
            ->get();

        $enforced = 0;
        $skipped = 0;

        foreach ($products as $product) {
            if ($this->enforcePricing($product)) {
                $enforced++;
            } else {
                $skipped++;
            }
        }

        return ['enforced' => $enforced, 'skipped' => $skipped];
    }
}
