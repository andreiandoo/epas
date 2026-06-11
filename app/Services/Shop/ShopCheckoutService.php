<?php

namespace App\Services\Shop;

use App\Models\Shop\ShopCart;
use App\Models\Shop\ShopOrder;
use App\Models\Shop\ShopOrderItem;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopDigitalDownload;
use App\Models\Shop\ShopGiftCard;
use App\Models\Shop\ShopShippingZone;
use App\Models\Shop\ShopShippingMethod;
use App\Models\Tenant;
use App\Services\Coupon\CouponService;
use App\Events\Shop\ShopOrderCreated;
use App\Events\Shop\ShopOrderCompleted;
use App\Events\Shop\ShopOrderRefunded;
use App\Events\Shop\ShopCheckoutStarted;
use App\Notifications\Shop\ShopOrderConfirmedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ShopCheckoutService
{
    public function __construct(
        protected ShopCartService $cartService,
        protected ShopInventoryService $inventoryService,
        protected CouponService $couponService
    ) {}

    // ==========================================
    // CHECKOUT VALIDATION
    // ==========================================

    public function validateCheckout(ShopCart $cart, array $data): array
    {
        $errors = [];

        // Validate cart has items
        if ($cart->isEmpty()) {
            $errors[] = [
                'field' => 'cart',
                'message' => 'Cart is empty',
            ];
            return ['valid' => false, 'errors' => $errors];
        }

        // Validate cart items are still available
        $cartValidation = $this->cartService->validateCartItems($cart);
        if (!$cartValidation['valid']) {
            foreach ($cartValidation['errors'] as $error) {
                $errors[] = [
                    'field' => 'cart_items',
                    'item_id' => $error['item_id'],
                    'message' => $error['message'],
                ];
            }
        }

        // Validate customer info
        if (empty($data['customer_email']) || !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = [
                'field' => 'customer_email',
                'message' => 'Valid email address is required',
            ];
        }

        // Validate shipping address for physical products
        $hasPhysicalProducts = $cart->items()
            ->whereHas('product', fn($q) => $q->where('type', 'physical'))
            ->exists();

        if ($hasPhysicalProducts) {
            $shippingErrors = $this->validateShippingAddress($data['shipping_address'] ?? []);
            $errors = array_merge($errors, $shippingErrors);

            // Validate shipping method
            if (empty($data['shipping_method_id'])) {
                $errors[] = [
                    'field' => 'shipping_method_id',
                    'message' => 'Shipping method is required for physical products',
                ];
            } else {
                $shippingMethod = ShopShippingMethod::find($data['shipping_method_id']);
                if (!$shippingMethod || !$shippingMethod->is_active) {
                    $errors[] = [
                        'field' => 'shipping_method_id',
                        'message' => 'Invalid shipping method',
                    ];
                }
            }
        }

        // Validate billing address if provided
        if (!empty($data['billing_address']) && !($data['same_as_shipping'] ?? false)) {
            $billingErrors = $this->validateBillingAddress($data['billing_address']);
            $errors = array_merge($errors, $billingErrors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $cartValidation['warnings'] ?? [],
        ];
    }

    protected function validateShippingAddress(array $address): array
    {
        $errors = [];
        // postal_code is optional - not everyone knows their postal code
        $required = ['name', 'line1', 'city', 'country'];

        foreach ($required as $field) {
            if (empty($address[$field])) {
                $errors[] = [
                    'field' => "shipping_address.{$field}",
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                ];
            }
        }

        return $errors;
    }

    protected function validateBillingAddress(array $address): array
    {
        $errors = [];
        // postal_code is optional - not everyone knows their postal code
        $required = ['name', 'line1', 'city', 'country'];

        foreach ($required as $field) {
            if (empty($address[$field])) {
                $errors[] = [
                    'field' => "billing_address.{$field}",
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
                ];
            }
        }

        return $errors;
    }

    // ==========================================
    // SHIPPING CALCULATION
    // ==========================================

    public function getAvailableShippingMethods(ShopCart $cart, array $address, bool $hasBundlePhysical = false): array
    {
        $tenant = $cart->tenant;

        // Check if cart has physical products
        $hasPhysicalProducts = $cart->items()
            ->whereHas('product', fn($q) => $q->where('type', 'physical'))
            ->exists();

        // Include bundle physical products (products attached to tickets)
        $hasPhysicalProducts = $hasPhysicalProducts || $hasBundlePhysical;

        if (!$hasPhysicalProducts) {
            return []; // No shipping needed for digital-only orders
        }

        // Find matching shipping zone
        $zone = ShopShippingZone::findForAddress($tenant->id, $address);

        if (!$zone) {
            return [];
        }

        // Get cart totals for shipping calculation
        $subtotalCents = $cart->getSubtotalCents();
        $totalWeightGrams = 0;

        foreach ($cart->items as $item) {
            $weight = $item->variant?->weight_grams ?? $item->product->weight_grams ?? 0;
            $totalWeightGrams += $weight * $item->quantity;
        }

        // Get available methods
        $methods = $zone->activeMethods()->get();
        $language = app()->getLocale();

        return $methods->filter(function ($method) use ($subtotalCents, $totalWeightGrams, $hasPhysicalProducts) {
            return $method->isAvailableForOrder($subtotalCents, $totalWeightGrams, $hasPhysicalProducts);
        })->map(function ($method) use ($subtotalCents, $totalWeightGrams, $language) {
            $cost = $method->calculateCost($subtotalCents / 100, $totalWeightGrams);

            // Get translated name and description
            $name = is_array($method->name)
                ? ($method->name[$language] ?? $method->name['en'] ?? array_values($method->name)[0] ?? '')
                : $method->name;

            $description = is_array($method->description)
                ? ($method->description[$language] ?? $method->description['en'] ?? '')
                : $method->description;

            return [
                'id' => $method->id,
                'name' => $name,
                'description' => $description,
                'provider' => $method->provider,
                'cost' => number_format($cost, 2, '.', ''),
                'estimated_days' => $method->estimated_days_min && $method->estimated_days_max
                    ? ($method->estimated_days_min === $method->estimated_days_max
                        ? $method->estimated_days_min
                        : "{$method->estimated_days_min}-{$method->estimated_days_max}")
                    : null,
                'is_free' => $cost == 0,
            ];
        })->values()->toArray();
    }

    public function calculateShippingCost(ShopCart $cart, string $methodId): ?int
    {
        $method = ShopShippingMethod::find($methodId);

        if (!$method || !$method->is_active) {
            return null;
        }

        $subtotalCents = $cart->getSubtotalCents();
        $totalWeightGrams = 0;

        foreach ($cart->items as $item) {
            $weight = $item->variant?->weight_grams ?? $item->product->weight_grams ?? 0;
            $totalWeightGrams += $weight * $item->quantity;
        }

        return $method->calculateCost($subtotalCents, $totalWeightGrams);
    }

    // ==========================================
    // ORDER CREATION
    // ==========================================

    public function createOrder(ShopCart $cart, array $data): array
    {
        // Validate checkout data
        $validation = $this->validateCheckout($cart, $data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        // Refresh cart prices to ensure accuracy
        $this->cartService->refreshCartPrices($cart);

        $tenant = $cart->tenant;

        // Calculate shipping
        $shippingCents = 0;
        $shippingMethod = null;

        $hasPhysicalProducts = $cart->items()
            ->whereHas('product', fn($q) => $q->where('type', 'physical'))
            ->exists();

        if ($hasPhysicalProducts && !empty($data['shipping_method_id'])) {
            $shippingCents = $this->calculateShippingCost($cart, $data['shipping_method_id']);
            $shippingMethod = ShopShippingMethod::find($data['shipping_method_id']);
        }

        // Calculate gift card amount to apply
        $giftCardCents = 0;
        $giftCard = null;
        if (!empty($data['gift_card_code'])) {
            $giftCardValidation = $this->cartService->validateGiftCard($cart, $data['gift_card_code']);
            if ($giftCardValidation['valid']) {
                $giftCard = ShopGiftCard::findByCode($tenant->id, $data['gift_card_code']);
                $giftCardCents = $giftCard->current_balance_cents;
            }
        }

        // Calculate totals
        $totals = $this->cartService->calculateTotals($cart, [
            'shipping_cents' => $shippingCents,
            'gift_card_cents' => $giftCardCents,
        ]);

        return DB::transaction(function () use ($cart, $data, $tenant, $totals, $shippingMethod, $giftCard, $giftCardCents, $hasPhysicalProducts) {
            // Reserve inventory
            $inventoryResult = $this->inventoryService->reserveInventoryForCart($cart);
            if (!$inventoryResult['success']) {
                return [
                    'success' => false,
                    'errors' => [[
                        'field' => 'inventory',
                        'message' => $inventoryResult['message'],
                    ]],
                ];
            }

            // Create the order
            $order = ShopOrder::create([
                'tenant_id' => $tenant->id,
                'order_number' => ShopOrder::generateOrderNumber($tenant->id),
                'customer_id' => $cart->customer_id,
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'status' => 'pending',
                'payment_status' => 'pending',
                'fulfillment_status' => $hasPhysicalProducts ? 'unfulfilled' : 'not_required',
                'subtotal_cents' => $totals['subtotal_cents'],
                'discount_cents' => $totals['discount_cents'],
                'shipping_cents' => $totals['shipping_cents'],
                'tax_cents' => $totals['tax_cents'],
                'total_cents' => $totals['total_cents'],
                'currency' => $cart->currency ?? $tenant->settings['currency'] ?? 'RON',
                'coupon_code' => $cart->coupon_code,
                'coupon_discount_cents' => $totals['discount_cents'],
                'billing_address' => $data['billing_address'] ?? $data['shipping_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'shipping_method' => $shippingMethod?->name,
                'shipping_provider' => $shippingMethod?->provider,
                'notes' => $data['notes'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'ticket_order_id' => $data['ticket_order_id'] ?? null,
                'meta' => [
                    'customer_name' => $data['customer_name'] ?? ($data['shipping_address']['name'] ?? null),
                    'gift_card_applied' => $giftCard ? [
                        'code' => $giftCard->code,
                        'amount_cents' => min($giftCardCents, $totals['total_cents'] + min($giftCardCents, $totals['subtotal_cents'] - $totals['discount_cents'] + $totals['shipping_cents'] + $totals['tax_cents'])),
                    ] : null,
                ],
            ]);

            // Create order items
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;
                $variant = $cartItem->variant;

                ShopOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cartItem->product_id,
                    'variant_id' => $cartItem->variant_id,
                    'product_title' => $product->getTranslation('title', app()->getLocale()),
                    'variant_title' => $variant?->getAttributeLabel(),
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $cartItem->quantity,
                    'unit_price_cents' => $cartItem->unit_price_cents,
                    'total_cents' => $cartItem->total_cents,
                    'meta' => [
                        'product_type' => $product->type,
                        'weight_grams' => $variant?->weight_grams ?? $product->weight_grams,
                    ],
                ]);

                // Create digital download records for digital products
                if ($product->type === 'digital' && $product->digital_file_url) {
                    for ($i = 0; $i < $cartItem->quantity; $i++) {
                        ShopDigitalDownload::create([
                            'tenant_id' => $tenant->id,
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'customer_email' => $data['customer_email'],
                            'file_url' => $product->digital_file_url,
                            'download_token' => Str::random(64),
                            'download_limit' => $product->digital_download_limit,
                            'downloads_remaining' => $product->digital_download_limit,
                            'expires_at' => $product->digital_download_expiry_days
                                ? now()->addDays($product->digital_download_expiry_days)
                                : null,
                        ]);
                    }
                }
            }

            // Redeem coupon if used
            if ($cart->coupon_code && $totals['discount_cents'] > 0) {
                try {
                    $this->couponService->redeemCode($tenant->id, $cart->coupon_code, [
                        'order_id' => $order->id,
                        'cart_total' => $totals['subtotal_cents'] / 100,
                        'user_id' => $cart->customer_id,
                    ]);
                } catch (\Exception $e) {
                    // Log but don't fail - coupon was validated earlier
                }
            }

            // Apply gift card if used
            if ($giftCard && $giftCardCents > 0) {
                $amountToDebit = min($giftCardCents, $totals['total_cents'] + min($giftCardCents, $totals['subtotal_cents'] - $totals['discount_cents'] + $totals['shipping_cents'] + $totals['tax_cents']));
                if ($amountToDebit > 0) {
                    $giftCard->debit($amountToDebit, $order->id, "Order #{$order->order_number}");
                }
            }

            // Mark cart as converted
            $cart->markAsConverted($order->id);

            // Dispatch order created event for tracking
            ShopOrderCreated::dispatch($tenant->id, $order->fresh(['items']));

            return [
                'success' => true,
                'order' => $order->fresh(['items']),
            ];
        });
    }

    // ==========================================
    // PAYMENT PROCESSING
    // ==========================================

    public function markOrderPaid(ShopOrder $order, array $paymentData = []): ShopOrder
    {
        $order->update([
            'payment_status' => 'paid',
            'status' => 'processing',
            'payment_method' => $paymentData['method'] ?? null,
            'payment_transaction_id' => $paymentData['transaction_id'] ?? null,
            'paid_at' => now(),
        ]);

        // Deduct inventory (was reserved during order creation)
        $this->inventoryService->confirmInventoryDeduction($order);

        $order = $order->fresh(['items', 'tenant']);

        // Dispatch order completed event for tracking (Purchase event)
        ShopOrderCompleted::dispatch($order->tenant_id, $order, $paymentData);

        // Send order confirmation notification
        if ($order->customer_email) {
            Notification::route('mail', $order->customer_email)
                ->notify(new ShopOrderConfirmedNotification($order));
        }

        return $order;
    }

    public function markOrderPaymentFailed(ShopOrder $order, ?string $reason = null): ShopOrder
    {
        $meta = $order->meta ?? [];
        $meta['payment_failure_reason'] = $reason;

        $order->update([
            'payment_status' => 'failed',
            'meta' => $meta,
        ]);

        // Release reserved inventory
        $this->inventoryService->releaseReservedInventory($order);

        return $order->fresh();
    }

    // ==========================================
    // ORDER MANAGEMENT
    // ==========================================

    public function cancelOrder(ShopOrder $order, ?string $reason = null): array
    {
        if (!$order->canBeCancelled()) {
            return [
                'success' => false,
                'message' => 'Order cannot be cancelled',
            ];
        }

        DB::transaction(function () use ($order, $reason) {
            $order->markAsCancelled($reason);

            // Release inventory if payment was pending
            if ($order->payment_status === 'pending') {
                $this->inventoryService->releaseReservedInventory($order);
            }

            // Refund gift card if used
            $giftCardData = $order->meta['gift_card_applied'] ?? null;
            if ($giftCardData) {
                $giftCard = ShopGiftCard::findByCode($order->tenant_id, $giftCardData['code']);
                if ($giftCard) {
                    $giftCard->refund($giftCardData['amount_cents'], $order->id, "Refund for cancelled order #{$order->order_number}");
                }
            }

            // Reverse coupon redemption if used
            if ($order->coupon_code) {
                // Note: CouponService handles reversal
            }
        });

        return [
            'success' => true,
            'order' => $order->fresh(),
        ];
    }

    public function refundOrder(ShopOrder $order, int $amountCents, ?string $reason = null): array
    {
        if (!$order->canBeRefunded()) {
            return [
                'success' => false,
                'message' => 'Order cannot be refunded',
            ];
        }

        // Validate refund amount
        if ($amountCents > $order->total_cents) {
            return [
                'success' => false,
                'message' => 'Refund amount exceeds order total',
            ];
        }

        DB::transaction(function () use ($order, $amountCents, $reason) {
            $meta = $order->meta ?? [];
            $meta['refunds'] = $meta['refunds'] ?? [];
            $meta['refunds'][] = [
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'refunded_at' => now()->toISOString(),
            ];

            $totalRefunded = array_sum(array_column($meta['refunds'], 'amount_cents'));
            $isFullRefund = $totalRefunded >= $order->total_cents;

            $order->update([
                'status' => $isFullRefund ? 'refunded' : $order->status,
                'payment_status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                'meta' => $meta,
            ]);

            // Restore inventory for full refunds
            if ($isFullRefund) {
                foreach ($order->items as $item) {
                    $item->product->incrementStock($item->quantity, $item->variant_id);
                }
            }
        });

        // Dispatch refund event for tracking
        ShopOrderRefunded::dispatch($order->tenant_id, $order->fresh(), $amountCents, $reason);

        return [
            'success' => true,
            'order' => $order->fresh(),
        ];
    }

    // ==========================================
    // CHECKOUT SESSION
    // ==========================================

    public function prepareCheckoutSession(ShopCart $cart, array $options = []): array
    {
        $cart->load(['items.product', 'items.variant']);

        // Validate cart
        $validation = $this->cartService->validateCartItems($cart);

        // Calculate initial totals (without shipping)
        $totals = $this->cartService->calculateTotals($cart);

        // Get tenant settings
        $tenant = $cart->tenant;
        $config = $tenant->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration ?? [];

        $requiresShipping = $cart->items()
            ->whereHas('product', fn($q) => $q->where('type', 'physical'))
            ->exists();

        // Dispatch checkout started event for tracking
        ShopCheckoutStarted::dispatch($tenant->id, $cart, $cart->customer_id);

        return [
            'cart' => $this->cartService->formatCart($cart),
            'validation' => $validation,
            'totals' => $totals,
            'requires_shipping' => $requiresShipping,
            'guest_checkout_enabled' => $config['guest_checkout'] ?? true,
            'currency' => $totals['currency'] ?? $tenant->settings['currency'] ?? 'RON',
        ];
    }
}
