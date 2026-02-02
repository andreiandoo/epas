<?php

namespace App\Services\Shop;

use App\Models\Shop\ShopCart;
use App\Models\Shop\ShopOrder;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopProductVariant;
use App\Models\Shop\ShopStockAlert;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopInventoryService
{
    // ==========================================
    // INVENTORY RESERVATION
    // ==========================================

    public function reserveInventoryForCart(ShopCart $cart): array
    {
        $errors = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            if (!$product->track_inventory) {
                continue;
            }

            $stockQuantity = $variant?->stock_quantity ?? $product->stock_quantity;

            if ($stockQuantity !== null && $stockQuantity < $item->quantity) {
                $errors[] = [
                    'item_id' => $item->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'requested' => $item->quantity,
                    'available' => $stockQuantity,
                    'message' => "Insufficient stock for '{$product->getTranslation('title', app()->getLocale())}'",
                ];
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Insufficient inventory for one or more items',
                'errors' => $errors,
            ];
        }

        // In a production system, you might want to actually reserve the stock here
        // by decrementing and storing reservation records that can be released later

        return ['success' => true];
    }

    public function confirmInventoryDeduction(ShopOrder $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product || !$product->track_inventory) {
                continue;
            }

            $success = $product->decrementStock($item->quantity, $item->variant_id);

            if (!$success) {
                Log::warning('Failed to deduct inventory after payment', [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                ]);
            }

            // Check if low stock and send alerts
            $this->checkAndTriggerLowStockAlert($product, $item->variant);
        }
    }

    public function releaseReservedInventory(ShopOrder $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product || !$product->track_inventory) {
                continue;
            }

            // Only increment if the stock was actually deducted (paid orders)
            if ($order->payment_status === 'paid') {
                $product->incrementStock($item->quantity, $item->variant_id);
            }
        }
    }

    // ==========================================
    // STOCK MANAGEMENT
    // ==========================================

    public function adjustStock(
        ShopProduct $product,
        int $quantity,
        ?string $variantId = null,
        string $reason = 'manual_adjustment',
        ?array $meta = null
    ): array {
        $variant = null;
        if ($variantId) {
            $variant = $product->variants()->find($variantId);
            if (!$variant) {
                return [
                    'success' => false,
                    'message' => 'Variant not found',
                ];
            }
        }

        $currentStock = $variant?->stock_quantity ?? $product->stock_quantity;
        $newStock = max(0, $currentStock + $quantity);

        DB::transaction(function () use ($product, $variant, $newStock, $quantity, $reason, $meta) {
            if ($variant) {
                $variant->update(['stock_quantity' => $newStock]);
            } else {
                $product->update(['stock_quantity' => $newStock]);
            }

            // Log the adjustment
            Log::info('Stock adjusted', [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'previous_stock' => $variant?->stock_quantity ?? $product->stock_quantity,
                'adjustment' => $quantity,
                'new_stock' => $newStock,
                'reason' => $reason,
                'meta' => $meta,
            ]);
        });

        // Check for back-in-stock notifications
        if ($quantity > 0 && $currentStock <= 0) {
            $this->processBackInStockAlerts($product, $variant);
        }

        // Check for low stock alerts
        $this->checkAndTriggerLowStockAlert($product, $variant);

        return [
            'success' => true,
            'previous_stock' => $currentStock,
            'new_stock' => $newStock,
        ];
    }

    public function setStock(
        ShopProduct $product,
        int $quantity,
        ?string $variantId = null,
        ?string $reason = null
    ): array {
        $currentStock = $variantId
            ? $product->variants()->find($variantId)?->stock_quantity ?? 0
            : $product->stock_quantity ?? 0;

        $adjustment = $quantity - $currentStock;

        return $this->adjustStock($product, $adjustment, $variantId, $reason ?? 'stock_set');
    }

    public function bulkAdjustStock(array $adjustments): array
    {
        $results = [];

        DB::transaction(function () use ($adjustments, &$results) {
            foreach ($adjustments as $adjustment) {
                $product = ShopProduct::find($adjustment['product_id']);

                if (!$product) {
                    $results[] = [
                        'product_id' => $adjustment['product_id'],
                        'success' => false,
                        'message' => 'Product not found',
                    ];
                    continue;
                }

                $result = $this->adjustStock(
                    $product,
                    $adjustment['quantity'],
                    $adjustment['variant_id'] ?? null,
                    $adjustment['reason'] ?? 'bulk_adjustment'
                );

                $results[] = array_merge($result, [
                    'product_id' => $adjustment['product_id'],
                    'variant_id' => $adjustment['variant_id'] ?? null,
                ]);
            }
        });

        return $results;
    }

    // ==========================================
    // STOCK ALERTS
    // ==========================================

    public function createStockAlert(
        int $tenantId,
        string $productId,
        string $email,
        ?string $variantId = null,
        ?int $customerId = null
    ): array {
        // Check if alert already exists
        $existing = ShopStockAlert::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'message' => 'You already have an alert set for this product',
                'alert' => $existing,
            ];
        }

        $alert = ShopStockAlert::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'customer_id' => $customerId,
            'email' => $email,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'alert' => $alert,
        ];
    }

    public function cancelStockAlert(ShopStockAlert $alert): void
    {
        $alert->update(['status' => 'cancelled']);
    }

    protected function processBackInStockAlerts(ShopProduct $product, ?ShopProductVariant $variant): void
    {
        $alerts = ShopStockAlert::where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->where('status', 'pending')
            ->get();

        foreach ($alerts as $alert) {
            // TODO: Dispatch back-in-stock notification job
            // BackInStockNotification::dispatch($alert);

            $alert->update([
                'status' => 'sent',
                'notified_at' => now(),
            ]);
        }
    }

    protected function checkAndTriggerLowStockAlert(ShopProduct $product, ?ShopProductVariant $variant): void
    {
        if (!$product->track_inventory) {
            return;
        }

        $stockQuantity = $variant?->stock_quantity ?? $product->stock_quantity;
        $threshold = $product->low_stock_threshold ?? 5;

        if ($stockQuantity !== null && $stockQuantity <= $threshold && $stockQuantity > 0) {
            // TODO: Dispatch low stock alert notification to tenant admins
            // LowStockNotification::dispatch($product, $variant);

            Log::info('Low stock alert triggered', [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'stock_quantity' => $stockQuantity,
                'threshold' => $threshold,
            ]);
        }

        if ($stockQuantity !== null && $stockQuantity <= 0) {
            // TODO: Dispatch out of stock notification
            Log::warning('Product out of stock', [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
            ]);
        }
    }

    // ==========================================
    // INVENTORY REPORTS
    // ==========================================

    public function getLowStockProducts(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return ShopProduct::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->where('status', '!=', 'archived')
            ->lowStock()
            ->with('category')
            ->get();
    }

    public function getOutOfStockProducts(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return ShopProduct::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->where('status', '!=', 'archived')
            ->outOfStock()
            ->with('category')
            ->get();
    }

    public function getInventorySummary(int $tenantId): array
    {
        $products = ShopProduct::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->where('status', '!=', 'archived')
            ->get();

        $totalProducts = $products->count();
        $inStock = $products->filter(fn($p) => $p->isInStock())->count();
        $lowStock = $products->filter(fn($p) => $p->isLowStock())->count();
        $outOfStock = $products->filter(fn($p) => !$p->isInStock())->count();

        $totalValue = 0;
        foreach ($products as $product) {
            $stock = $product->getTotalStock() ?? 0;
            $cost = $product->cost_cents ?? $product->price_cents;
            $totalValue += $stock * $cost;
        }

        return [
            'total_products_tracked' => $totalProducts,
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_inventory_value_cents' => $totalValue,
            'pending_alerts' => ShopStockAlert::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count(),
        ];
    }

    public function getInventoryReport(int $tenantId, array $filters = []): array
    {
        $query = ShopProduct::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->with(['category', 'variants']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'in_stock' => $query->inStock(),
                'low_stock' => $query->lowStock(),
                'out_of_stock' => $query->outOfStock(),
                default => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereRaw("title::text ILIKE ?", ["%{$search}%"])
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        $products = $query->orderBy('stock_quantity', 'asc')->get();

        return $products->map(function ($product) {
            $variants = $product->variants->map(fn($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'attributes' => $v->attribute_values,
                'stock_quantity' => $v->stock_quantity,
                'price_cents' => $v->price_cents,
                'is_active' => $v->is_active,
            ]);

            return [
                'id' => $product->id,
                'title' => $product->getTranslation('title', app()->getLocale()),
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'status' => $product->isInStock()
                    ? ($product->isLowStock() ? 'low_stock' : 'in_stock')
                    : 'out_of_stock',
                'price_cents' => $product->price_cents,
                'cost_cents' => $product->cost_cents,
                'variants' => $variants,
                'has_variants' => $product->hasVariants(),
                'total_stock' => $product->getTotalStock(),
            ];
        })->toArray();
    }

    // ==========================================
    // INVENTORY SYNC
    // ==========================================

    public function syncInventoryFromExternal(int $tenantId, array $items): array
    {
        $results = [
            'processed' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($tenantId, $items, &$results) {
            foreach ($items as $item) {
                $results['processed']++;

                $product = ShopProduct::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($item) {
                        $q->where('id', $item['product_id'] ?? '')
                            ->orWhere('sku', $item['sku'] ?? '');
                    })
                    ->first();

                if (!$product) {
                    $results['errors'][] = [
                        'sku' => $item['sku'] ?? $item['product_id'] ?? 'unknown',
                        'message' => 'Product not found',
                    ];
                    continue;
                }

                $variantId = null;
                if (!empty($item['variant_sku'])) {
                    $variant = $product->variants()->where('sku', $item['variant_sku'])->first();
                    if ($variant) {
                        $variantId = $variant->id;
                    }
                }

                $result = $this->setStock(
                    $product,
                    (int) $item['quantity'],
                    $variantId,
                    'external_sync'
                );

                if ($result['success']) {
                    $results['updated']++;
                } else {
                    $results['errors'][] = [
                        'sku' => $item['sku'] ?? $product->sku,
                        'message' => $result['message'] ?? 'Unknown error',
                    ];
                }
            }
        });

        return $results;
    }
}
