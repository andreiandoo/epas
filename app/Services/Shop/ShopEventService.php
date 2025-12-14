<?php

namespace App\Services\Shop;

use App\Models\Shop\ShopEventProduct;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopCart;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class ShopEventService
{
    public function __construct(
        protected ShopCartService $cartService
    ) {}

    // ==========================================
    // UPSELLS MANAGEMENT
    // ==========================================

    public function getUpsellsForEvent(int $eventId, string $language = 'en'): array
    {
        $eventProducts = ShopEventProduct::getUpsellsForEvent($eventId);

        return $eventProducts->map(function ($ep) use ($language) {
            $product = $ep->product;

            return [
                'id' => $ep->id,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->getTranslation('title', $language),
                    'description' => $product->getTranslation('short_description', $language),
                    'image_url' => $product->image_url,
                    'price_cents' => $product->display_price_cents,
                    'original_price_cents' => $product->price_cents,
                    'is_on_sale' => $product->isOnSale(),
                    'type' => $product->type,
                    'in_stock' => $product->isInStock(),
                    'has_variants' => $product->hasVariants(),
                    'variants' => $product->hasVariants() ? $this->formatVariants($product, $language) : [],
                ],
                'sort_order' => $ep->sort_order,
            ];
        })->toArray();
    }

    public function getUpsellsForTicketType(int $ticketTypeId, string $language = 'en'): array
    {
        $ticketType = TicketType::find($ticketTypeId);
        if (!$ticketType) {
            return [];
        }

        $eventProducts = ShopEventProduct::where('event_id', $ticketType->event_id)
            ->where('association_type', 'upsell')
            ->where('is_active', true)
            ->where(function ($q) use ($ticketTypeId) {
                $q->whereNull('ticket_type_id')
                    ->orWhere('ticket_type_id', $ticketTypeId);
            })
            ->with(['product' => fn($q) => $q->active()->visible()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($ep) => $ep->product !== null);

        return $eventProducts->map(function ($ep) use ($language) {
            $product = $ep->product;

            return [
                'id' => $ep->id,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->getTranslation('title', $language),
                    'description' => $product->getTranslation('short_description', $language),
                    'image_url' => $product->image_url,
                    'price_cents' => $product->display_price_cents,
                    'original_price_cents' => $product->price_cents,
                    'is_on_sale' => $product->isOnSale(),
                    'type' => $product->type,
                    'in_stock' => $product->isInStock(),
                ],
                'is_specific_to_ticket_type' => $ep->ticket_type_id !== null,
                'sort_order' => $ep->sort_order,
            ];
        })->toArray();
    }

    // ==========================================
    // BUNDLES MANAGEMENT
    // ==========================================

    public function getBundlesForTicketType(int $ticketTypeId, string $language = 'en'): array
    {
        $bundles = ShopEventProduct::getBundlesForTicketType($ticketTypeId);

        return $bundles->map(function ($ep) use ($language) {
            $product = $ep->product;

            return [
                'id' => $ep->id,
                'product' => [
                    'id' => $product->id,
                    'title' => $product->getTranslation('title', $language),
                    'description' => $product->getTranslation('short_description', $language),
                    'image_url' => $product->image_url,
                    'type' => $product->type,
                ],
                'quantity_included' => $ep->quantity_included,
                'sort_order' => $ep->sort_order,
            ];
        })->toArray();
    }

    public function getProductsIncludedWithTicket(int $ticketTypeId): array
    {
        $bundles = ShopEventProduct::getBundlesForTicketType($ticketTypeId);

        return $bundles->map(function ($ep) {
            return [
                'product_id' => $ep->product_id,
                'quantity' => $ep->quantity_included,
            ];
        })->toArray();
    }

    // ==========================================
    // EVENT-PRODUCT ASSOCIATION
    // ==========================================

    public function associateProductWithEvent(
        int $eventId,
        string $productId,
        string $associationType,
        ?int $ticketTypeId = null,
        int $quantityIncluded = 1,
        int $sortOrder = 0
    ): ShopEventProduct {
        return ShopEventProduct::updateOrCreate(
            [
                'event_id' => $eventId,
                'product_id' => $productId,
                'association_type' => $associationType,
                'ticket_type_id' => $ticketTypeId,
            ],
            [
                'quantity_included' => $quantityIncluded,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]
        );
    }

    public function removeProductFromEvent(int $eventId, string $productId, ?string $associationType = null): bool
    {
        $query = ShopEventProduct::where('event_id', $eventId)
            ->where('product_id', $productId);

        if ($associationType) {
            $query->where('association_type', $associationType);
        }

        return $query->delete() > 0;
    }

    public function updateEventProductSort(int $eventId, array $sortedIds): void
    {
        DB::transaction(function () use ($eventId, $sortedIds) {
            foreach ($sortedIds as $index => $id) {
                ShopEventProduct::where('event_id', $eventId)
                    ->where('id', $id)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    public function toggleEventProduct(string $eventProductId, bool $active): void
    {
        ShopEventProduct::where('id', $eventProductId)->update(['is_active' => $active]);
    }

    // ==========================================
    // ADD UPSELLS TO CART
    // ==========================================

    public function addUpsellToCart(
        ShopCart $cart,
        string $eventProductId,
        ?string $variantId = null,
        int $quantity = 1
    ): array {
        $eventProduct = ShopEventProduct::with('product')->find($eventProductId);

        if (!$eventProduct || !$eventProduct->is_active) {
            return [
                'success' => false,
                'error' => 'invalid_upsell',
                'message' => 'Invalid upsell product',
            ];
        }

        if (!$eventProduct->isUpsell()) {
            return [
                'success' => false,
                'error' => 'not_upsell',
                'message' => 'Product is not an upsell',
            ];
        }

        $product = $eventProduct->product;

        if (!$product || !$product->is_visible || $product->status !== 'active') {
            return [
                'success' => false,
                'error' => 'product_unavailable',
                'message' => 'Product is not available',
            ];
        }

        return $this->cartService->addItem($cart, $product->id, $variantId, $quantity);
    }

    // ==========================================
    // FULFILL BUNDLED PRODUCTS
    // ==========================================

    public function fulfillBundledProducts(int $orderId, int $ticketTypeId, int $ticketQuantity): array
    {
        $bundles = ShopEventProduct::getBundlesForTicketType($ticketTypeId);
        $fulfilledProducts = [];

        foreach ($bundles as $bundle) {
            $product = $bundle->product;
            $totalQuantity = $bundle->quantity_included * $ticketQuantity;

            // For digital products, create download records
            if ($product->type === 'digital' && $product->digital_file_url) {
                // Digital products handled during order creation
                $fulfilledProducts[] = [
                    'product_id' => $product->id,
                    'product_title' => $product->getTranslation('title', 'en'),
                    'quantity' => $totalQuantity,
                    'type' => 'digital',
                ];
            } else {
                // Physical products need to be tracked for fulfillment
                $fulfilledProducts[] = [
                    'product_id' => $product->id,
                    'product_title' => $product->getTranslation('title', 'en'),
                    'quantity' => $totalQuantity,
                    'type' => 'physical',
                    'needs_shipping' => true,
                ];

                // Deduct inventory
                if ($product->track_inventory) {
                    $product->decrementStock($totalQuantity);
                }
            }
        }

        return $fulfilledProducts;
    }

    // ==========================================
    // EVENT PRODUCTS LISTING
    // ==========================================

    public function getEventProducts(int $eventId): array
    {
        $products = ShopEventProduct::where('event_id', $eventId)
            ->with(['product', 'ticketType'])
            ->orderBy('association_type')
            ->orderBy('sort_order')
            ->get();

        return [
            'upsells' => $products->filter(fn($p) => $p->isUpsell())->values()->map(fn($p) => $this->formatEventProduct($p))->toArray(),
            'bundles' => $products->filter(fn($p) => $p->isBundle())->values()->map(fn($p) => $this->formatEventProduct($p))->toArray(),
        ];
    }

    protected function formatEventProduct(ShopEventProduct $ep): array
    {
        return [
            'id' => $ep->id,
            'product_id' => $ep->product_id,
            'product_title' => $ep->product?->getTranslation('title', 'en'),
            'product_image' => $ep->product?->image_url,
            'association_type' => $ep->association_type,
            'ticket_type_id' => $ep->ticket_type_id,
            'ticket_type_name' => $ep->ticketType?->getTranslation('name', 'en'),
            'quantity_included' => $ep->quantity_included,
            'sort_order' => $ep->sort_order,
            'is_active' => $ep->is_active,
        ];
    }

    protected function formatVariants(ShopProduct $product, string $language): array
    {
        return $product->activeVariants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'label' => $variant->getAttributeLabel(),
                'sku' => $variant->sku,
                'price_cents' => $variant->display_price_cents,
                'in_stock' => $variant->isInStock(),
                'stock_quantity' => $variant->stock_quantity,
                'image_url' => $variant->image_url,
            ];
        })->toArray();
    }
}
