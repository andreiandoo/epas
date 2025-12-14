<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Shop\ShopCart;
use App\Models\Shop\ShopCartItem;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopProductVariant;
use App\Models\Shop\ShopGiftCard;
use App\Models\Coupon\CouponCode;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopCartController extends Controller
{
    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();
            return $domain?->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function hasShopMicroservice(Tenant $tenant): bool
    {
        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function getOrCreateCart(Request $request, Tenant $tenant): ShopCart
    {
        $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId();
        $customerId = $request->user()?->customer_id;
        $email = $request->input('email');

        $cart = ShopCart::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId, fn($q) => $q->where('session_id', $sessionId))
            ->first();

        if (!$cart) {
            $cart = ShopCart::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'customer_id' => $customerId,
                'session_id' => $sessionId,
                'email' => $email,
                'currency' => $tenant->settings['currency'] ?? 'RON',
                'status' => 'active',
                'expires_at' => now()->addHours(72),
            ]);
        }

        return $cart;
    }

    /**
     * Get cart contents
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $cart = $this->getOrCreateCart($request, $tenant);
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'data' => $this->formatCart($cart, $tenantLanguage),
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasShopMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Shop is not enabled'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|uuid',
            'variant_id' => 'nullable|uuid',
            'quantity' => 'required|integer|min:1|max:99',
        ]);

        $product = ShopProduct::where('tenant_id', $tenant->id)
            ->where('id', $validated['product_id'])
            ->active()
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or unavailable',
            ], 404);
        }

        $variant = null;
        if (!empty($validated['variant_id'])) {
            $variant = $product->variants()->where('id', $validated['variant_id'])->where('is_active', true)->first();
            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variant not found',
                ], 404);
            }
        }

        // Check stock
        if ($product->track_inventory) {
            $availableStock = $variant ? $variant->stock_quantity : $product->stock_quantity;
            if ($availableStock !== null && $availableStock < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock. Available: ' . $availableStock,
                ], 400);
            }
        }

        $cart = $this->getOrCreateCart($request, $tenant);

        // Check if item already exists
        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->first();

        if ($existingItem) {
            $newQuantity = $existingItem->quantity + $validated['quantity'];

            // Check stock for combined quantity
            if ($product->track_inventory) {
                $availableStock = $variant ? $variant->stock_quantity : $product->stock_quantity;
                if ($availableStock !== null && $availableStock < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot add more. Maximum available: ' . $availableStock,
                    ], 400);
                }
            }

            $existingItem->update(['quantity' => $newQuantity]);
        } else {
            $unitPrice = $variant?->price_cents ?? $variant?->sale_price_cents ?? $product->display_price_cents;

            $cart->items()->create([
                'id' => Str::uuid(),
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $validated['quantity'],
                'unit_price_cents' => $unitPrice,
            ]);
        }

        $cart->touch();
        $cart->load('items.product', 'items.variant');
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
            'data' => $this->formatCart($cart, $tenantLanguage),
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, string $itemId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        $cart = $this->getOrCreateCart($request, $tenant);
        $item = $cart->items()->where('id', $itemId)->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        }

        if ($validated['quantity'] === 0) {
            $item->delete();
        } else {
            // Check stock
            if ($item->product->track_inventory) {
                $availableStock = $item->variant ? $item->variant->stock_quantity : $item->product->stock_quantity;
                if ($availableStock !== null && $availableStock < $validated['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock. Available: ' . $availableStock,
                    ], 400);
                }
            }
            $item->update(['quantity' => $validated['quantity']]);
        }

        $cart->touch();
        $cart->load('items.product', 'items.variant');
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'message' => 'Cart updated',
            'data' => $this->formatCart($cart, $tenantLanguage),
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $cart = $this->getOrCreateCart($request, $tenant);
        $cart->items()->where('id', $itemId)->delete();

        $cart->touch();
        $cart->load('items.product', 'items.variant');
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'message' => 'Item removed',
            'data' => $this->formatCart($cart, $tenantLanguage),
        ]);
    }

    /**
     * Clear cart
     */
    public function clear(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $cart = $this->getOrCreateCart($request, $tenant);
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared',
            'data' => $this->formatCart($cart, $tenant->language ?? 'en'),
        ]);
    }

    /**
     * Apply coupon code
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $cart = $this->getOrCreateCart($request, $tenant);
        $cart->load('items.product');

        // Find coupon
        $coupon = CouponCode::where('tenant_id', $tenant->id)
            ->byCode($validated['code'])
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon code not found',
            ], 404);
        }

        if (!$coupon->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon code is not valid',
            ], 400);
        }

        // Check if coupon applies to shop
        $appliesTo = $coupon->applies_to ?? ['tickets'];
        if (!in_array('shop', $appliesTo) && !in_array('both', $appliesTo)) {
            return response()->json([
                'success' => false,
                'message' => 'This coupon cannot be used for shop purchases',
            ], 400);
        }

        // Calculate cart subtotal
        $subtotal = $cart->items->sum(fn($i) => $i->unit_price_cents * $i->quantity);

        if (!$coupon->isValidForAmount($subtotal / 100)) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount not met: ' . number_format($coupon->min_purchase_amount, 2),
            ], 400);
        }

        $cart->update(['coupon_code' => $coupon->code]);

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'message' => 'Coupon applied',
            'data' => $this->formatCart($cart->fresh(['items.product', 'items.variant']), $tenantLanguage),
        ]);
    }

    /**
     * Remove coupon
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $cart = $this->getOrCreateCart($request, $tenant);
        $cart->update(['coupon_code' => null]);

        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return response()->json([
            'success' => true,
            'message' => 'Coupon removed',
            'data' => $this->formatCart($cart->fresh(['items.product', 'items.variant']), $tenantLanguage),
        ]);
    }

    /**
     * Apply gift card
     */
    public function applyGiftCard(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $giftCard = ShopGiftCard::where('tenant_id', $tenant->id)
            ->where('code', strtoupper($validated['code']))
            ->where('status', 'active')
            ->first();

        if (!$giftCard) {
            return response()->json([
                'success' => false,
                'message' => 'Gift card not found or invalid',
            ], 404);
        }

        if (!$giftCard->canBeUsed()) {
            return response()->json([
                'success' => false,
                'message' => 'Gift card cannot be used',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $giftCard->code,
                'balance_cents' => $giftCard->current_balance_cents,
                'balance' => $giftCard->current_balance_cents / 100,
                'currency' => $giftCard->currency,
            ],
        ]);
    }

    private function formatCart(ShopCart $cart, string $language): array
    {
        $cart->loadMissing('items.product', 'items.variant');

        $items = $cart->items->map(function ($item) use ($language) {
            $title = is_array($item->product->title)
                ? ($item->product->title[$language] ?? $item->product->title['en'] ?? '')
                : $item->product->title;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'product_slug' => $item->product->slug,
                'title' => $title,
                'variant_name' => $item->variant?->name,
                'sku' => $item->variant?->sku ?? $item->product->sku,
                'image_url' => $item->variant?->image_url ?? $item->product->image_url,
                'type' => $item->product->type,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'unit_price' => $item->unit_price_cents / 100,
                'total_cents' => $item->unit_price_cents * $item->quantity,
                'total' => ($item->unit_price_cents * $item->quantity) / 100,
            ];
        });

        $subtotal = $items->sum('total_cents');
        $discount = 0;
        $couponInfo = null;

        if ($cart->coupon_code) {
            $coupon = CouponCode::where('tenant_id', $cart->tenant_id)
                ->byCode($cart->coupon_code)
                ->first();

            if ($coupon && $coupon->isValid()) {
                $discount = (int) ($coupon->calculateDiscount($subtotal / 100) * 100);
                $couponInfo = [
                    'code' => $coupon->code,
                    'discount_type' => $coupon->discount_type,
                    'discount_value' => $coupon->discount_value,
                    'discount_amount' => $discount / 100,
                ];
            }
        }

        return [
            'id' => $cart->id,
            'items' => $items,
            'item_count' => $items->sum('quantity'),
            'subtotal_cents' => $subtotal,
            'subtotal' => $subtotal / 100,
            'discount_cents' => $discount,
            'discount' => $discount / 100,
            'total_cents' => $subtotal - $discount,
            'total' => ($subtotal - $discount) / 100,
            'currency' => $cart->currency,
            'coupon' => $couponInfo,
        ];
    }
}
