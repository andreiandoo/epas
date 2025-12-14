<?php

namespace App\Services\Shop;

use App\Models\Shop\ShopCart;
use App\Models\Shop\ShopCartItem;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopProductVariant;
use App\Models\Shop\ShopGiftCard;
use App\Models\Tenant;
use App\Services\Coupon\CouponService;
use Illuminate\Support\Facades\DB;

class ShopCartService
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    // ==========================================
    // CART MANAGEMENT
    // ==========================================

    public function getOrCreateCart(
        int $tenantId,
        ?int $customerId = null,
        ?string $sessionId = null
    ): ShopCart {
        return ShopCart::getOrCreate($tenantId, $customerId, $sessionId);
    }

    public function getCart(
        int $tenantId,
        ?int $customerId = null,
        ?string $sessionId = null
    ): ?ShopCart {
        if ($customerId) {
            $cart = ShopCart::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->where('status', 'active')
                ->with(['items.product', 'items.variant'])
                ->first();

            if ($cart) {
                return $cart;
            }
        }

        if ($sessionId) {
            return ShopCart::where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->where('status', 'active')
                ->with(['items.product', 'items.variant'])
                ->first();
        }

        return null;
    }

    public function mergeGuestCartToCustomer(ShopCart $guestCart, int $customerId): ShopCart
    {
        // Check if customer already has a cart
        $customerCart = ShopCart::where('tenant_id', $guestCart->tenant_id)
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->first();

        if (!$customerCart) {
            // Just assign the guest cart to the customer
            $guestCart->update(['customer_id' => $customerId]);
            return $guestCart;
        }

        // Merge items from guest cart to customer cart
        DB::transaction(function () use ($guestCart, $customerCart) {
            foreach ($guestCart->items as $item) {
                $existingItem = $customerCart->items()
                    ->where('product_id', $item->product_id)
                    ->where('variant_id', $item->variant_id)
                    ->first();

                if ($existingItem) {
                    $existingItem->update([
                        'quantity' => $existingItem->quantity + $item->quantity,
                    ]);
                } else {
                    $customerCart->items()->create([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                        'meta' => $item->meta,
                    ]);
                }
            }

            // Transfer coupon if customer cart doesn't have one
            if ($guestCart->coupon_code && !$customerCart->coupon_code) {
                $customerCart->update(['coupon_code' => $guestCart->coupon_code]);
            }

            // Delete guest cart
            $guestCart->items()->delete();
            $guestCart->delete();
        });

        return $customerCart->fresh(['items.product', 'items.variant']);
    }

    // ==========================================
    // ITEM OPERATIONS
    // ==========================================

    public function addItem(
        ShopCart $cart,
        string $productId,
        ?string $variantId,
        int $quantity = 1
    ): array {
        $product = ShopProduct::where('tenant_id', $cart->tenant_id)
            ->where('id', $productId)
            ->first();

        if (!$product) {
            return [
                'success' => false,
                'error' => 'product_not_found',
                'message' => 'Product not found',
            ];
        }

        if (!$product->is_visible || $product->status !== 'active') {
            return [
                'success' => false,
                'error' => 'product_unavailable',
                'message' => 'Product is not available',
            ];
        }

        $variant = null;
        $priceCents = $product->display_price_cents;

        if ($variantId) {
            $variant = $product->variants()->find($variantId);
            if (!$variant || !$variant->is_active) {
                return [
                    'success' => false,
                    'error' => 'variant_not_found',
                    'message' => 'Product variant not found',
                ];
            }
            $priceCents = $variant->display_price_cents;
        }

        // Check stock availability
        $stockCheck = $this->checkStockAvailability($cart, $product, $variant, $quantity);
        if (!$stockCheck['available']) {
            return [
                'success' => false,
                'error' => 'insufficient_stock',
                'message' => $stockCheck['message'],
                'available_quantity' => $stockCheck['available_quantity'],
            ];
        }

        $item = $cart->addItem($productId, $variantId, $quantity, $priceCents);

        return [
            'success' => true,
            'item' => $item,
            'cart' => $cart->fresh(['items.product', 'items.variant']),
        ];
    }

    public function updateItemQuantity(ShopCart $cart, string $itemId, int $quantity): array
    {
        $item = $cart->items()->with(['product', 'variant'])->find($itemId);

        if (!$item) {
            return [
                'success' => false,
                'error' => 'item_not_found',
                'message' => 'Cart item not found',
            ];
        }

        if ($quantity <= 0) {
            $item->delete();
            return [
                'success' => true,
                'removed' => true,
                'cart' => $cart->fresh(['items.product', 'items.variant']),
            ];
        }

        // Check stock availability
        $stockCheck = $this->checkStockAvailability(
            $cart,
            $item->product,
            $item->variant,
            $quantity,
            $item->id
        );

        if (!$stockCheck['available']) {
            return [
                'success' => false,
                'error' => 'insufficient_stock',
                'message' => $stockCheck['message'],
                'available_quantity' => $stockCheck['available_quantity'],
            ];
        }

        $item->update(['quantity' => $quantity]);

        return [
            'success' => true,
            'item' => $item->fresh(),
            'cart' => $cart->fresh(['items.product', 'items.variant']),
        ];
    }

    public function removeItem(ShopCart $cart, string $itemId): array
    {
        $removed = $cart->removeItem($itemId);

        return [
            'success' => $removed,
            'cart' => $cart->fresh(['items.product', 'items.variant']),
        ];
    }

    public function clearCart(ShopCart $cart): array
    {
        $cart->clear();

        return [
            'success' => true,
            'cart' => $cart->fresh(),
        ];
    }

    // ==========================================
    // STOCK CHECKING
    // ==========================================

    protected function checkStockAvailability(
        ShopCart $cart,
        ShopProduct $product,
        ?ShopProductVariant $variant,
        int $requestedQuantity,
        ?string $excludeItemId = null
    ): array {
        if (!$product->track_inventory) {
            return ['available' => true, 'available_quantity' => null];
        }

        $stockQuantity = $variant?->stock_quantity ?? $product->stock_quantity;

        if ($stockQuantity === null) {
            return ['available' => true, 'available_quantity' => null];
        }

        // Get quantity already in cart for this product/variant (excluding current item if updating)
        $existingInCart = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->when($excludeItemId, fn($q) => $q->where('id', '!=', $excludeItemId))
            ->sum('quantity');

        $totalRequested = $existingInCart + $requestedQuantity;
        $availableQuantity = max(0, $stockQuantity - $existingInCart);

        if ($totalRequested > $stockQuantity) {
            return [
                'available' => false,
                'message' => $availableQuantity > 0
                    ? "Only {$availableQuantity} items available"
                    : 'Product is out of stock',
                'available_quantity' => $availableQuantity,
            ];
        }

        return ['available' => true, 'available_quantity' => $availableQuantity];
    }

    public function validateCartItems(ShopCart $cart): array
    {
        $errors = [];
        $warnings = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            // Check if product is still available
            if (!$product || !$product->is_visible || $product->status !== 'active') {
                $errors[] = [
                    'item_id' => $item->id,
                    'type' => 'product_unavailable',
                    'message' => "Product '{$item->getProductTitle()}' is no longer available",
                ];
                continue;
            }

            // Check if variant is still available
            if ($item->variant_id && (!$variant || !$variant->is_active)) {
                $errors[] = [
                    'item_id' => $item->id,
                    'type' => 'variant_unavailable',
                    'message' => "Selected variant for '{$item->getProductTitle()}' is no longer available",
                ];
                continue;
            }

            // Check stock
            if (!$item->isInStock()) {
                $available = $item->getAvailableQuantity();
                if ($available > 0) {
                    $warnings[] = [
                        'item_id' => $item->id,
                        'type' => 'quantity_reduced',
                        'message' => "Only {$available} of '{$item->getProductTitle()}' available",
                        'available_quantity' => $available,
                    ];
                } else {
                    $errors[] = [
                        'item_id' => $item->id,
                        'type' => 'out_of_stock',
                        'message' => "'{$item->getProductTitle()}' is out of stock",
                    ];
                }
            }

            // Check price changes
            $currentPrice = $variant?->display_price_cents ?? $product->display_price_cents;
            if ($currentPrice !== $item->unit_price_cents) {
                $warnings[] = [
                    'item_id' => $item->id,
                    'type' => 'price_changed',
                    'message' => "Price of '{$item->getProductTitle()}' has changed",
                    'old_price_cents' => $item->unit_price_cents,
                    'new_price_cents' => $currentPrice,
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    public function refreshCartPrices(ShopCart $cart): void
    {
        foreach ($cart->items as $item) {
            $item->updatePriceFromProduct();
        }
    }

    // ==========================================
    // COUPON OPERATIONS
    // ==========================================

    public function applyCoupon(ShopCart $cart, string $couponCode): array
    {
        $tenant = $cart->tenant;

        // Prepare context for coupon validation
        $context = [
            'cart_total' => $cart->getSubtotalCents(),
            'cart_items' => $cart->items->map(fn($item) => [
                'id' => $item->product_id,
                'product_id' => $item->product_id,
                'category_id' => $item->product?->category_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price_cents,
            ])->toArray(),
            'user_id' => $cart->customer_id,
        ];

        $validation = $this->couponService->validateCode($tenant->id, $couponCode, $context);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
                'message' => $validation['message'],
            ];
        }

        // Check if coupon is combinable with existing
        if ($cart->coupon_code && !$validation['is_combinable']) {
            return [
                'success' => false,
                'error' => 'not_combinable',
                'message' => 'This coupon cannot be combined with other coupons',
            ];
        }

        $cart->update(['coupon_code' => strtoupper($couponCode)]);

        return [
            'success' => true,
            'coupon' => [
                'code' => $validation['code'],
                'name' => $validation['campaign_name'],
                'discount_type' => $validation['discount_type'],
                'discount_value' => $validation['discount_value'],
                'discount_amount' => $validation['discount_amount'],
            ],
            'cart' => $cart->fresh(['items.product', 'items.variant']),
        ];
    }

    public function removeCoupon(ShopCart $cart): array
    {
        $cart->update(['coupon_code' => null]);

        return [
            'success' => true,
            'cart' => $cart->fresh(['items.product', 'items.variant']),
        ];
    }

    public function getCouponDiscount(ShopCart $cart): ?array
    {
        if (!$cart->coupon_code) {
            return null;
        }

        $context = [
            'cart_total' => $cart->getSubtotalCents(),
            'cart_items' => $cart->items->map(fn($item) => [
                'id' => $item->product_id,
                'product_id' => $item->product_id,
                'category_id' => $item->product?->category_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price_cents,
            ])->toArray(),
            'user_id' => $cart->customer_id,
        ];

        $validation = $this->couponService->validateCode($cart->tenant_id, $cart->coupon_code, $context);

        if (!$validation['valid']) {
            return null;
        }

        return [
            'code' => $validation['code'],
            'discount_type' => $validation['discount_type'],
            'discount_value' => $validation['discount_value'],
            'discount_cents' => (int) ($validation['discount_amount'] * 100),
        ];
    }

    // ==========================================
    // GIFT CARD OPERATIONS
    // ==========================================

    public function validateGiftCard(ShopCart $cart, string $code): array
    {
        $giftCard = ShopGiftCard::findByCode($cart->tenant_id, $code);

        if (!$giftCard) {
            return [
                'valid' => false,
                'error' => 'invalid_code',
                'message' => 'Gift card not found',
            ];
        }

        if (!$giftCard->isUsable()) {
            if ($giftCard->isExpired()) {
                return [
                    'valid' => false,
                    'error' => 'expired',
                    'message' => 'This gift card has expired',
                ];
            }

            if ($giftCard->current_balance_cents <= 0) {
                return [
                    'valid' => false,
                    'error' => 'no_balance',
                    'message' => 'This gift card has no remaining balance',
                ];
            }

            return [
                'valid' => false,
                'error' => 'not_active',
                'message' => 'This gift card is not active',
            ];
        }

        return [
            'valid' => true,
            'gift_card' => [
                'code' => $giftCard->code,
                'balance_cents' => $giftCard->current_balance_cents,
                'expires_at' => $giftCard->expires_at?->toISOString(),
            ],
        ];
    }

    // ==========================================
    // CART TOTALS CALCULATION
    // ==========================================

    public function calculateTotals(ShopCart $cart, array $options = []): array
    {
        $subtotalCents = $cart->getSubtotalCents();
        $discountCents = 0;
        $taxCents = 0;
        $shippingCents = $options['shipping_cents'] ?? 0;
        $giftCardCents = $options['gift_card_cents'] ?? 0;

        // Calculate coupon discount
        $couponDiscount = $this->getCouponDiscount($cart);
        if ($couponDiscount) {
            $discountCents = $couponDiscount['discount_cents'];
        }

        // Calculate tax for each item
        foreach ($cart->items as $item) {
            $product = $item->product;
            if ($product) {
                $itemTotal = $item->total_cents;
                // Apply proportional discount to item
                if ($discountCents > 0 && $subtotalCents > 0) {
                    $itemDiscount = (int) round(($itemTotal / $subtotalCents) * $discountCents);
                    $itemTotal = max(0, $itemTotal - $itemDiscount);
                }

                $taxMode = $product->getEffectiveTaxMode();
                if ($taxMode === 'added') {
                    $taxCents += $product->calculateTax($itemTotal);
                }
            }
        }

        $totalBeforeGiftCard = max(0, $subtotalCents - $discountCents + $shippingCents + $taxCents);
        $appliedGiftCardCents = min($giftCardCents, $totalBeforeGiftCard);
        $totalCents = max(0, $totalBeforeGiftCard - $appliedGiftCardCents);

        return [
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'shipping_cents' => $shippingCents,
            'tax_cents' => $taxCents,
            'gift_card_cents' => $appliedGiftCardCents,
            'total_cents' => $totalCents,
            'item_count' => $cart->getItemCount(),
            'coupon' => $couponDiscount,
        ];
    }

    // ==========================================
    // CART EXPIRATION & ABANDONMENT
    // ==========================================

    public function setCartEmail(ShopCart $cart, string $email): void
    {
        $cart->update(['email' => $email]);
    }

    public function markAbandoned(ShopCart $cart): void
    {
        $cart->markAsAbandoned();
    }

    public function getAbandonedCarts(int $tenantId, int $hoursOld = 24): \Illuminate\Database\Eloquent\Collection
    {
        return ShopCart::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('updated_at', '<', now()->subHours($hoursOld))
            ->whereHas('items')
            ->with(['items.product', 'items.variant'])
            ->get();
    }

    public function getRecoverableCarts(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return ShopCart::where('tenant_id', $tenantId)
            ->recoverable()
            ->with(['items.product', 'items.variant'])
            ->get();
    }

    // ==========================================
    // CART FORMATTING
    // ==========================================

    public function formatCart(ShopCart $cart, string $language = 'en'): array
    {
        $cart->load(['items.product', 'items.variant']);
        $totals = $this->calculateTotals($cart);

        $items = $cart->items->map(function ($item) use ($language) {
            $product = $item->product;
            $variant = $item->variant;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'name' => $product?->getTranslation('title', $language) ?? 'Unknown Product',
                'variant_label' => $variant?->getAttributeLabel(),
                'image_url' => $variant?->image_url ?? $product?->image_url,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'total_cents' => $item->total_cents,
                'in_stock' => $item->isInStock(),
                'available_quantity' => $item->getAvailableQuantity(),
            ];
        });

        return [
            'id' => $cart->id,
            'items' => $items,
            'item_count' => $totals['item_count'],
            'subtotal_cents' => $totals['subtotal_cents'],
            'discount_cents' => $totals['discount_cents'],
            'shipping_cents' => $totals['shipping_cents'],
            'tax_cents' => $totals['tax_cents'],
            'total_cents' => $totals['total_cents'],
            'coupon_code' => $cart->coupon_code,
            'coupon' => $totals['coupon'],
            'currency' => $cart->currency ?? $cart->tenant?->settings['currency'] ?? 'RON',
        ];
    }
}
