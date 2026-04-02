<?php

namespace App\Services\Cashless;

use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CashlessSettings;
use App\Models\VendorEdition;
use App\Models\VendorProduct;
use App\Models\VendorSaleItem;
use App\Models\WristbandTransaction;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private CashlessAccountService $accountService,
        private AgeVerificationService $ageVerificationService,
    ) {}

    /**
     * Create a complete cashless sale with items, charge the account, create transaction.
     *
     * @param array<int, array{product_id: int, quantity: int, variant_name?: string}> $items
     */
    public function createSale(
        CashlessAccount $account,
        int $vendorId,
        int $editionId,
        array $items,
        ?int $employeeId = null,
        ?int $posDeviceId = null,
        ?int $shiftId = null,
        int $tipCents = 0,
    ): CashlessSale {
        // Load products
        $productIds = array_column($items, 'product_id');
        $products = VendorProduct::whereIn('id', $productIds)
            ->where('vendor_id', $vendorId)
            ->get()
            ->keyBy('id');

        // Validate all products exist and are available
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw new \InvalidArgumentException("Product #{$item['product_id']} not found for this vendor.");
            }
            if (! $product->is_available) {
                throw new \InvalidArgumentException("Product '{$product->name}' is not available.");
            }
        }

        // Age verification check
        $this->ageVerificationService->verifyForProducts($account, $products->values(), $editionId);

        // Calculate totals
        $subtotalCents = 0;
        $taxCents = 0;
        $sgrCents = 0;
        $lineItems = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $quantity = max(1, (int) $item['quantity']);
            $unitPrice = $product->sale_price_cents ?? $product->price_cents;
            $itemTotal = $unitPrice * $quantity;

            // Calculate tax per item
            $itemTax = 0;
            if ($product->vat_rate > 0 && $product->vat_included) {
                // VAT is included in price: extract it
                $itemTax = (int) round($itemTotal - ($itemTotal / (1 + $product->vat_rate / 100)));
            } elseif ($product->vat_rate > 0 && ! $product->vat_included) {
                // VAT not included: add it
                $itemTax = (int) round($itemTotal * $product->vat_rate / 100);
                $itemTotal += $itemTax;
            }

            $itemSgr = ($product->sgr_cents ?? 0) * $quantity;

            $subtotalCents += $itemTotal;
            $taxCents += $itemTax;
            $sgrCents += $itemSgr;

            $lineItems[] = [
                'product'        => $product,
                'quantity'       => $quantity,
                'unit_price'     => $unitPrice,
                'total'          => $itemTotal,
                'tax'            => $itemTax,
                'sgr'            => $itemSgr,
                'variant_name'   => $item['variant_name'] ?? null,
            ];
        }

        $totalCents = $subtotalCents + $sgrCents;

        // Get commission rate from VendorEdition pivot
        $vendorEdition = VendorEdition::where('vendor_id', $vendorId)
            ->where('festival_edition_id', $editionId)
            ->first();

        $commissionCents = 0;
        $commissionRate = 0;
        if ($vendorEdition) {
            $commissionRate = $vendorEdition->commission_rate ?? 0;
            if ($vendorEdition->commission_mode === 'fixed') {
                $commissionCents = $vendorEdition->fixed_commission_cents ?? 0;
            } else {
                // Tip is exempt from commission
                $commissionCents = (int) round($totalCents * $commissionRate / 100);
            }
        }

        // Total charge = items + SGR + tip
        $chargeAmount = $totalCents + $tipCents;

        // Validate tip against settings
        if ($tipCents > 0) {
            $settings = CashlessSettings::forEdition($editionId);
            if ($settings && ! $settings->allow_tipping) {
                throw new \InvalidArgumentException('Tipping is disabled for this edition.');
            }
            if ($settings && $settings->max_tip_cents && $tipCents > $settings->max_tip_cents) {
                throw new \InvalidArgumentException("Tip exceeds maximum ({$settings->max_tip_cents}).");
            }
        }

        return DB::transaction(function () use (
            $account, $vendorId, $editionId, $lineItems, $employeeId, $posDeviceId, $shiftId,
            $subtotalCents, $taxCents, $sgrCents, $totalCents, $commissionCents, $commissionRate, $tipCents, $chargeAmount,
        ) {
            // Charge the account
            $transaction = $this->accountService->charge(
                $account,
                $chargeAmount,
                description: 'POS Sale',
                vendorId: $vendorId,
                posDeviceId: $posDeviceId,
            );

            if ($transaction === false) {
                throw new \InvalidArgumentException(
                    "Insufficient balance. Required: {$chargeAmount}, Available: {$account->balance_cents}"
                );
            }

            // Create the sale record
            $sale = CashlessSale::create([
                'tenant_id'                => $account->tenant_id,
                'festival_edition_id'      => $editionId,
                'vendor_id'                => $vendorId,
                'cashless_account_id'      => $account->id,
                'customer_id'              => $account->customer_id,
                'wristband_transaction_id' => $transaction->id,
                'vendor_employee_id'       => $employeeId,
                'vendor_pos_device_id'     => $posDeviceId,
                'vendor_shift_id'          => $shiftId,
                'sale_number'              => CashlessSale::generateSaleNumber(),
                'subtotal_cents'           => $subtotalCents,
                'tax_cents'                => $taxCents + $sgrCents,
                'total_cents'              => $totalCents,
                'commission_cents'         => $commissionCents,
                'tip_cents'                => $tipCents,
                'currency'                 => $account->currency,
                'items_count'              => count($lineItems),
                'status'                   => SaleStatus::Completed,
                'sold_at'                  => now(),
            ]);

            // Create line items
            foreach ($lineItems as $line) {
                VendorSaleItem::create([
                    'cashless_sale_id'        => $sale->id,
                    'vendor_id'               => $vendorId,
                    'festival_edition_id'     => $editionId,
                    'vendor_product_id'       => $line['product']->id,
                    'wristband_transaction_id' => $transaction->id,
                    'vendor_pos_device_id'    => $posDeviceId,
                    'vendor_employee_id'      => $employeeId,
                    'vendor_shift_id'         => $shiftId,
                    'product_name'            => $line['product']->name,
                    'category_name'           => $line['product']->category?->name,
                    'variant_name'            => $line['variant_name'],
                    'quantity'                => $line['quantity'],
                    'unit_price_cents'        => $line['unit_price'],
                    'total_cents'             => $line['total'],
                    'tax_cents'               => $line['tax'],
                    'sgr_cents'               => $line['sgr'],
                    'product_type'            => $line['product']->type?->value,
                    'product_category_name'   => $line['product']->category?->name,
                    'currency'                => $account->currency,
                    'commission_cents'         => $commissionRate > 0
                        ? (int) round($line['total'] * $commissionRate / 100)
                        : 0,
                    'commission_rate'         => $commissionRate,
                ]);
            }

            // Update shift sales count if applicable
            if ($shiftId) {
                \App\Models\VendorShift::where('id', $shiftId)->increment('sales_count');
                \App\Models\VendorShift::where('id', $shiftId)->increment('sales_total_cents', $totalCents);
            }

            return $sale->load('items');
        });
    }

    /**
     * Void a sale (full refund back to account).
     */
    public function voidSale(CashlessSale $sale, ?string $operator = null): CashlessSale
    {
        if ($sale->status !== SaleStatus::Completed) {
            throw new \InvalidArgumentException("Cannot void a sale with status '{$sale->status->value}'.");
        }

        return DB::transaction(function () use ($sale, $operator) {
            $refundAmount = $sale->total_cents + $sale->tip_cents;

            $this->accountService->refund(
                $sale->account,
                $refundAmount,
                "Voided sale {$sale->sale_number}",
                $operator,
            );

            $sale->update(['status' => SaleStatus::Voided]);

            return $sale->fresh();
        });
    }

    /**
     * Partial refund on specific items.
     *
     * @param array<int> $saleItemIds IDs of VendorSaleItem to refund
     */
    public function partialRefund(CashlessSale $sale, array $saleItemIds, ?string $operator = null): CashlessSale
    {
        if (! in_array($sale->status, [SaleStatus::Completed, SaleStatus::PartialRefund])) {
            throw new \InvalidArgumentException("Cannot refund a sale with status '{$sale->status->value}'.");
        }

        $itemsToRefund = VendorSaleItem::whereIn('id', $saleItemIds)
            ->where('cashless_sale_id', $sale->id)
            ->get();

        if ($itemsToRefund->isEmpty()) {
            throw new \InvalidArgumentException('No valid items to refund.');
        }

        return DB::transaction(function () use ($sale, $itemsToRefund, $operator) {
            $refundAmount = $itemsToRefund->sum('total_cents') + $itemsToRefund->sum('sgr_cents');

            $this->accountService->refund(
                $sale->account,
                $refundAmount,
                "Partial refund on sale {$sale->sale_number}",
                $operator,
            );

            // Check if all items are now refunded
            $totalItems = $sale->items()->count();
            $refundedItems = $itemsToRefund->count();
            $newStatus = $refundedItems >= $totalItems ? SaleStatus::Refunded : SaleStatus::PartialRefund;

            $sale->update(['status' => $newStatus]);

            return $sale->fresh();
        });
    }

    /**
     * Get sales grouped by category for an edition.
     */
    public function salesByCategory(int $editionId, ?int $vendorId = null): array
    {
        $query = VendorSaleItem::where('festival_edition_id', $editionId)
            ->whereHas('cashlessSale', fn ($q) => $q->where('status', SaleStatus::Completed));

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        $categories = $query
            ->selectRaw('
                COALESCE(product_category_name, category_name, \'Uncategorized\') as category,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents,
                COUNT(DISTINCT cashless_sale_id) as total_sales,
                AVG(unit_price_cents) as avg_price_cents
            ')
            ->groupBy(DB::raw('COALESCE(product_category_name, category_name, \'Uncategorized\')'))
            ->orderByDesc('total_revenue_cents')
            ->get();

        return $categories->map(function ($cat) use ($editionId, $vendorId) {
            $productsQuery = VendorSaleItem::where('festival_edition_id', $editionId)
                ->where(function ($q) use ($cat) {
                    $q->where('product_category_name', $cat->category)
                        ->orWhere('category_name', $cat->category);
                })
                ->whereHas('cashlessSale', fn ($q) => $q->where('status', SaleStatus::Completed));

            if ($vendorId) {
                $productsQuery->where('vendor_id', $vendorId);
            }

            $products = $productsQuery
                ->selectRaw('product_name, SUM(quantity) as quantity, SUM(total_cents) as revenue_cents')
                ->groupBy('product_name')
                ->orderByDesc('revenue_cents')
                ->get();

            return [
                'category'           => $cat->category,
                'total_sales'        => (int) $cat->total_sales,
                'total_quantity'     => (int) $cat->total_quantity,
                'total_revenue_cents' => (int) $cat->total_revenue_cents,
                'avg_price_cents'    => (int) $cat->avg_price_cents,
                'products'           => $products->toArray(),
            ];
        })->toArray();
    }
}
