<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
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
        ]);

        // Validate and apply promo code

        return response()->json([
            'success' => true,
            'message' => 'Promo code applied',
            'data' => [
                'discount' => 0,
                'discount_type' => 'percentage', // or 'fixed'
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
