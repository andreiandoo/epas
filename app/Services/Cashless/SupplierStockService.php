<?php

namespace App\Services\Cashless;

use App\Enums\StockMovementType;
use App\Models\Cashless\InventoryMovement;
use App\Models\Cashless\InventoryStock;
use App\Models\Cashless\SupplierProduct;
use Illuminate\Support\Facades\DB;

class SupplierStockService
{
    /**
     * Record a delivery from supplier to festival stock.
     */
    public function recordDelivery(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        float $quantity,
        ?string $reference = null,
        ?string $notes = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $editionId, $supplierProductId, $quantity, $reference, $notes, $performedBy) {
            $product = SupplierProduct::findOrFail($supplierProductId);

            // Get or create festival-level stock (vendor_id = null)
            $stock = InventoryStock::firstOrCreate(
                [
                    'festival_edition_id' => $editionId,
                    'supplier_product_id' => $supplierProductId,
                    'vendor_id'           => null,
                ],
                [
                    'tenant_id'    => $tenantId,
                    'unit_measure' => $product->unit_measure,
                ]
            );

            $stock->increment('quantity_total', $quantity);
            $stock->update(['last_movement_at' => now()]);

            return InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $stock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::Delivery,
                'quantity'            => $quantity,
                'unit_measure'        => $product->unit_measure,
                'reference'           => $reference,
                'notes'               => $notes,
                'performed_by'        => $performedBy,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Allocate stock from festival to a vendor.
     */
    public function allocateToVendor(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        int $vendorId,
        float $quantity,
        ?string $reference = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $editionId, $supplierProductId, $vendorId, $quantity, $reference, $performedBy) {
            $product = SupplierProduct::findOrFail($supplierProductId);

            // Get festival stock
            $festivalStock = InventoryStock::where('festival_edition_id', $editionId)
                ->where('supplier_product_id', $supplierProductId)
                ->whereNull('vendor_id')
                ->lockForUpdate()
                ->first();

            if (! $festivalStock || $festivalStock->quantityAvailable < $quantity) {
                $available = $festivalStock?->quantityAvailable ?? 0;
                throw new \InvalidArgumentException(
                    "Insufficient festival stock. Available: {$available}, Requested: {$quantity}"
                );
            }

            // Decrease festival stock
            $festivalStock->increment('quantity_allocated', $quantity);
            $festivalStock->update(['last_movement_at' => now()]);

            // Get or create vendor stock
            $vendorStock = InventoryStock::firstOrCreate(
                [
                    'festival_edition_id' => $editionId,
                    'supplier_product_id' => $supplierProductId,
                    'vendor_id'           => $vendorId,
                ],
                [
                    'tenant_id'    => $tenantId,
                    'unit_measure' => $product->unit_measure,
                ]
            );

            $vendorStock->increment('quantity_total', $quantity);
            $vendorStock->update(['last_movement_at' => now()]);

            return InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $vendorStock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::Allocation,
                'from_vendor_id'      => null,
                'to_vendor_id'        => $vendorId,
                'quantity'            => $quantity,
                'unit_measure'        => $product->unit_measure,
                'reference'           => $reference,
                'performed_by'        => $performedBy,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Record a sale (auto-decrement vendor stock).
     * Called by SaleService when a product has a supplier_product_id.
     */
    public function recordSale(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        int $vendorId,
        float $quantity,
    ): void {
        $vendorStock = InventoryStock::where('festival_edition_id', $editionId)
            ->where('supplier_product_id', $supplierProductId)
            ->where('vendor_id', $vendorId)
            ->first();

        if (! $vendorStock) {
            return; // No stock tracking for this product/vendor
        }

        DB::transaction(function () use ($vendorStock, $tenantId, $editionId, $supplierProductId, $vendorId, $quantity) {
            $vendorStock->increment('quantity_sold', $quantity);
            $vendorStock->update(['last_movement_at' => now()]);

            InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $vendorStock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::Sale,
                'from_vendor_id'      => $vendorId,
                'quantity'            => $quantity,
                'unit_measure'        => $vendorStock->unit_measure,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Return stock from vendor back to festival.
     */
    public function returnToFestival(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        int $vendorId,
        float $quantity,
        ?string $notes = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $editionId, $supplierProductId, $vendorId, $quantity, $notes, $performedBy) {
            $vendorStock = InventoryStock::where('festival_edition_id', $editionId)
                ->where('supplier_product_id', $supplierProductId)
                ->where('vendor_id', $vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($vendorStock->quantityAvailable < $quantity) {
                throw new \InvalidArgumentException(
                    "Cannot return more than available. Available: {$vendorStock->quantityAvailable}"
                );
            }

            $vendorStock->increment('quantity_returned', $quantity);
            $vendorStock->update(['last_movement_at' => now()]);

            // Re-add to festival stock
            $festivalStock = InventoryStock::where('festival_edition_id', $editionId)
                ->where('supplier_product_id', $supplierProductId)
                ->whereNull('vendor_id')
                ->first();

            if ($festivalStock) {
                $festivalStock->decrement('quantity_allocated', $quantity);
                $festivalStock->update(['last_movement_at' => now()]);
            }

            return InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $vendorStock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::ReturnToFestival,
                'from_vendor_id'      => $vendorId,
                'to_vendor_id'        => null,
                'quantity'            => $quantity,
                'unit_measure'        => $vendorStock->unit_measure,
                'notes'               => $notes,
                'performed_by'        => $performedBy,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Return stock from festival back to supplier.
     */
    public function returnToSupplier(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        float $quantity,
        ?string $reference = null,
        ?string $notes = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $editionId, $supplierProductId, $quantity, $reference, $notes, $performedBy) {
            $festivalStock = InventoryStock::where('festival_edition_id', $editionId)
                ->where('supplier_product_id', $supplierProductId)
                ->whereNull('vendor_id')
                ->lockForUpdate()
                ->firstOrFail();

            if ($festivalStock->quantityAvailable < $quantity) {
                throw new \InvalidArgumentException(
                    "Cannot return more than available. Available: {$festivalStock->quantityAvailable}"
                );
            }

            $festivalStock->increment('quantity_returned', $quantity);
            $festivalStock->update(['last_movement_at' => now()]);

            return InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $festivalStock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::ReturnToSupplier,
                'quantity'            => $quantity,
                'unit_measure'        => $festivalStock->unit_measure,
                'reference'           => $reference,
                'notes'               => $notes,
                'performed_by'        => $performedBy,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Record waste/spoilage.
     */
    public function recordWaste(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        ?int $vendorId,
        float $quantity,
        ?string $notes = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $editionId, $supplierProductId, $vendorId, $quantity, $notes, $performedBy) {
            $stock = InventoryStock::where('festival_edition_id', $editionId)
                ->where('supplier_product_id', $supplierProductId)
                ->where('vendor_id', $vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            $stock->increment('quantity_wasted', $quantity);
            $stock->update(['last_movement_at' => now()]);

            return InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $stock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::Waste,
                'from_vendor_id'      => $vendorId,
                'quantity'            => $quantity,
                'unit_measure'        => $stock->unit_measure,
                'notes'               => $notes,
                'performed_by'        => $performedBy,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Correction (manual adjustment).
     */
    public function recordCorrection(
        int $tenantId,
        int $editionId,
        int $supplierProductId,
        ?int $vendorId,
        float $quantityDelta,
        ?string $notes = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($tenantId, $editionId, $supplierProductId, $vendorId, $quantityDelta, $notes, $performedBy) {
            $stock = InventoryStock::where('festival_edition_id', $editionId)
                ->where('supplier_product_id', $supplierProductId)
                ->where('vendor_id', $vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            $stock->increment('quantity_total', $quantityDelta);
            $stock->update(['last_movement_at' => now()]);

            return InventoryMovement::create([
                'tenant_id'           => $tenantId,
                'festival_edition_id' => $editionId,
                'inventory_stock_id'  => $stock->id,
                'supplier_product_id' => $supplierProductId,
                'movement_type'       => StockMovementType::Correction,
                'quantity'            => abs($quantityDelta),
                'unit_measure'        => $stock->unit_measure,
                'notes'               => $notes ?? ($quantityDelta > 0 ? 'Positive correction' : 'Negative correction'),
                'performed_by'        => $performedBy,
                'performed_at'        => now(),
            ]);
        });
    }

    /**
     * Get stock summary for an edition.
     */
    public function getStockSummary(int $editionId, ?int $vendorId = null): array
    {
        $query = InventoryStock::where('festival_edition_id', $editionId)
            ->with('supplierProduct:id,name,sku,unit_measure');

        if ($vendorId !== null) {
            $query->where('vendor_id', $vendorId);
        }

        return $query->get()->map(function ($stock) {
            return [
                'product_name'      => $stock->supplierProduct->name,
                'sku'               => $stock->supplierProduct->sku,
                'vendor_id'         => $stock->vendor_id,
                'quantity_total'    => (float) $stock->quantity_total,
                'quantity_allocated' => (float) $stock->quantity_allocated,
                'quantity_sold'     => (float) $stock->quantity_sold,
                'quantity_returned' => (float) $stock->quantity_returned,
                'quantity_wasted'   => (float) $stock->quantity_wasted,
                'quantity_available' => $stock->quantityAvailable,
                'unit_measure'      => $stock->unit_measure,
                'is_low'            => $stock->isLow(),
                'is_exhausted'      => $stock->isExhausted(),
            ];
        })->toArray();
    }
}
