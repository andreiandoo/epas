<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Coupon\CouponCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get cart contents
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $sessionId = $request->header('X-Session-ID') ?? $request->session()->getId();

        // Get cart items from session/database
        $items = [];
        $subtotal = 0;
        $fees = 0;
        $total = 0;

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'subtotal' => $subtotal,
                'fees' => $fees,
                'total' => $total,
                'currency' => $tenant->settings['currency'] ?? 'RON',
                'item_count' => count($items),
            ],
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'ticket_type_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10',
            'seat_ids' => 'nullable|array',
            'seat_ids.*' => 'integer',
        ]);

        // Validate ticket availability
        // Add to cart (session or database)

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
            'data' => [
                'item_id' => 1, // Cart item ID
                'quantity' => $validated['quantity'],
            ],
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        if ($validated['quantity'] === 0) {
            // Remove item
            return $this->removeItem($request, $itemId);
        }

        // Update quantity

        return response()->json([
            'success' => true,
            'message' => 'Cart updated',
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        // Remove item from cart

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
        ]);
    }

    /**
     * Clear entire cart
     */
    public function clear(Request $request): JsonResponse
    {
        // Clear all items

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared',
        ]);
    }

    /**
     * Apply promo code
     */
    public function applyPromoCode(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'cart' => 'nullable|array',
        ]);

        // Find coupon code for this tenant
        $coupon = CouponCode::where('tenant_id', $tenant->id)
            ->byCode($validated['code'])
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Codul promoțional nu a fost găsit.',
            ], 404);
        }

        // Check if coupon is valid (active, not expired, not exhausted)
        if (!$coupon->isValid()) {
            $message = 'Codul promoțional nu este valid.';

            if ($coupon->status !== 'active') {
                $message = 'Codul promoțional nu mai este activ.';
            } elseif ($coupon->expires_at && $coupon->expires_at->isPast()) {
                $message = 'Codul promoțional a expirat.';
            } elseif ($coupon->starts_at && $coupon->starts_at->isFuture()) {
                $message = 'Codul promoțional nu este încă valid.';
            } elseif ($coupon->max_uses_total && $coupon->current_uses >= $coupon->max_uses_total) {
                $message = 'Codul promoțional a atins limita maximă de utilizări.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 400);
        }

        // Check time-based restrictions
        if (!$coupon->isValidAtTime()) {
            return response()->json([
                'success' => false,
                'message' => 'Codul promoțional nu este valid în acest moment.',
            ], 400);
        }

        // Calculate cart total from request if provided
        $cartTotal = 0;
        if (isset($validated['cart']) && is_array($validated['cart'])) {
            foreach ($validated['cart'] as $item) {
                $price = $item['salePrice'] ?? $item['price'] ?? 0;
                $quantity = $item['quantity'] ?? 1;
                $cartTotal += $price * $quantity;
            }
        }

        // Check minimum purchase amount
        if ($cartTotal > 0 && !$coupon->isValidForAmount($cartTotal)) {
            return response()->json([
                'success' => false,
                'message' => 'Comanda minimă pentru acest cod promoțional este ' .
                    number_format($coupon->min_purchase_amount, 2) . ' ' . ($tenant->settings['currency'] ?? 'RON') . '.',
            ], 400);
        }

        // Calculate discount
        $discountAmount = $coupon->calculateDiscount($cartTotal);

        // Get campaign name for display
        $campaignName = $coupon->campaign ?
            ($coupon->campaign->getTranslation('name', app()->getLocale()) ?? $coupon->campaign->name ?? $validated['code']) :
            $validated['code'];

        return response()->json([
            'success' => true,
            'message' => 'Codul promoțional a fost aplicat cu succes!',
            'data' => [
                'code' => $coupon->code,
                'name' => $campaignName,
                'discount_type' => $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed',
                'discount_value' => (float) $coupon->discount_value,
                'discount_amount' => $discountAmount,
                'max_discount_amount' => $coupon->max_discount_amount ? (float) $coupon->max_discount_amount : null,
            ],
        ]);
    }

    /**
     * Remove promo code
     */
    public function removePromoCode(Request $request): JsonResponse
    {
        // Remove applied promo code

        return response()->json([
            'success' => true,
            'message' => 'Promo code removed',
        ]);
    }
}
