<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCart;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceTicketType;
use App\Models\MarketplacePromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends BaseController
{
    /**
     * Get cart contents
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);
        $customerId = $this->getCustomerId($request);

        $cart = MarketplaceCart::getOrCreate($sessionId, $client->id, $customerId);

        // Refresh prices and validate items
        $this->validateCartItems($cart);

        return $this->success([
            'cart' => $this->formatCart($cart),
        ]);
    }

    /**
     * Add item to cart
     */
    public function addItem(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'ticket_type_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:20',
        ]);

        // Validate event exists and belongs to this marketplace
        $event = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('id', $validated['event_id'])
            ->where('status', 'published')
            ->first();

        if (!$event) {
            return $this->error('Event not found or not available', 404);
        }

        // Validate ticket type
        $ticketType = MarketplaceTicketType::where('marketplace_event_id', $event->id)
            ->where('id', $validated['ticket_type_id'])
            ->where('status', 'on_sale')
            ->where('is_visible', true)
            ->first();

        if (!$ticketType) {
            return $this->error('Ticket type not available', 404);
        }

        // Check availability
        $available = $ticketType->quantity === null
            ? PHP_INT_MAX
            : $ticketType->quantity - $ticketType->quantity_sold - $ticketType->quantity_reserved;

        if ($available < $validated['quantity']) {
            return $this->error('Not enough tickets available', 400, [
                'available' => max(0, $available),
            ]);
        }

        // Check max per order
        if ($ticketType->max_per_order && $validated['quantity'] > $ticketType->max_per_order) {
            return $this->error("Maximum {$ticketType->max_per_order} tickets per order", 400);
        }

        $sessionId = $this->getSessionId($request);
        $customerId = $this->getCustomerId($request);
        $cart = MarketplaceCart::getOrCreate($sessionId, $client->id, $customerId);

        // Check if item already exists in cart
        $items = $cart->items ?? [];
        $itemKey = $event->id . '_' . $ticketType->id;

        $existingQuantity = $items[$itemKey]['quantity'] ?? 0;
        $totalQuantity = $existingQuantity + $validated['quantity'];

        // Check total quantity against limits
        if ($ticketType->max_per_order && $totalQuantity > $ticketType->max_per_order) {
            return $this->error("Maximum {$ticketType->max_per_order} tickets per order", 400);
        }

        if ($totalQuantity > $available) {
            return $this->error('Not enough tickets available', 400, [
                'available' => max(0, $available - $existingQuantity),
            ]);
        }

        // Add/update item
        $cart->addItem([
            'event_id' => $event->id,
            'event_name' => $event->name,
            'event_date' => $event->starts_at->toIso8601String(),
            'event_image' => $event->image,
            'ticket_type_id' => $ticketType->id,
            'ticket_type_name' => $ticketType->name,
            'price' => (float) $ticketType->price,
            'quantity' => $validated['quantity'],
            'currency' => $ticketType->currency,
        ]);

        $cart->extendExpiration(30);
        $cart->save();

        return $this->success([
            'cart' => $this->formatCart($cart),
            'message' => 'Item added to cart',
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateItem(Request $request, string $itemKey): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:20',
        ]);

        $sessionId = $this->getSessionId($request);
        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart) {
            return $this->error('Cart not found', 404);
        }

        $items = $cart->items ?? [];
        if (!isset($items[$itemKey])) {
            return $this->error('Item not found in cart', 404);
        }

        if ($validated['quantity'] === 0) {
            // Remove item
            $cart->removeItem($itemKey);
        } else {
            // Validate availability
            $ticketType = MarketplaceTicketType::find($items[$itemKey]['ticket_type_id']);
            if ($ticketType) {
                $available = $ticketType->quantity === null
                    ? PHP_INT_MAX
                    : $ticketType->quantity - $ticketType->quantity_sold - $ticketType->quantity_reserved;

                if ($validated['quantity'] > $available) {
                    return $this->error('Not enough tickets available', 400, [
                        'available' => $available,
                    ]);
                }

                if ($ticketType->max_per_order && $validated['quantity'] > $ticketType->max_per_order) {
                    return $this->error("Maximum {$ticketType->max_per_order} tickets per order", 400);
                }
            }

            $cart->updateItem($itemKey, $validated['quantity']);
        }

        $cart->extendExpiration(30);
        $cart->save();

        return $this->success([
            'cart' => $this->formatCart($cart),
            'message' => 'Cart updated',
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeItem(Request $request, string $itemKey): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart) {
            return $this->error('Cart not found', 404);
        }

        if (!$cart->removeItem($itemKey)) {
            return $this->error('Item not found in cart', 404);
        }

        $cart->save();

        return $this->success([
            'cart' => $this->formatCart($cart),
            'message' => 'Item removed from cart',
        ]);
    }

    /**
     * Clear cart
     */
    public function clear(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if ($cart) {
            $cart->clearItems();
            $cart->save();
        }

        return $this->success([
            'cart' => $cart ? $this->formatCart($cart) : $this->emptyCart(),
            'message' => 'Cart cleared',
        ]);
    }

    /**
     * Apply promo code
     */
    public function applyPromoCode(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $sessionId = $this->getSessionId($request);
        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty', 400);
        }

        // Get event IDs in cart
        $eventIds = collect($cart->items)->pluck('event_id')->unique()->values()->toArray();

        // Find promo code - must be for one of the events in cart
        $promoCode = MarketplacePromoCode::where('code', $validated['code'])
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->where(function ($q) use ($eventIds) {
                $q->whereNull('marketplace_event_id')
                    ->orWhereIn('marketplace_event_id', $eventIds);
            })
            ->first();

        if (!$promoCode) {
            return $this->error('Invalid or expired promo code', 400);
        }

        // Check usage limits
        if ($promoCode->max_uses !== null && $promoCode->times_used >= $promoCode->max_uses) {
            return $this->error('Promo code has reached its usage limit', 400);
        }

        // Check minimum purchase
        if ($promoCode->min_purchase_amount && $cart->subtotal < $promoCode->min_purchase_amount) {
            return $this->error(
                'Minimum purchase amount is ' . number_format($promoCode->min_purchase_amount, 2) . ' ' . $cart->currency,
                400
            );
        }

        // Apply promo code
        $cart->applyPromoCode([
            'code' => $promoCode->code,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => (float) $promoCode->discount_value,
            'max_discount_amount' => $promoCode->max_discount_amount ? (float) $promoCode->max_discount_amount : null,
            'name' => $promoCode->name ?? $promoCode->code,
        ]);
        $cart->save();

        return $this->success([
            'cart' => $this->formatCart($cart),
            'message' => 'Promo code applied',
        ]);
    }

    /**
     * Remove promo code
     */
    public function removePromoCode(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart) {
            return $this->error('Cart not found', 404);
        }

        $cart->removePromoCode();
        $cart->save();

        return $this->success([
            'cart' => $this->formatCart($cart),
            'message' => 'Promo code removed',
        ]);
    }

    /**
     * Get session ID from request
     */
    protected function getSessionId(Request $request): string
    {
        return $request->header('X-Session-ID')
            ?? $request->cookie('session_id')
            ?? $request->session()->getId()
            ?? md5($request->ip() . $request->userAgent());
    }

    /**
     * Get customer ID if logged in
     */
    protected function getCustomerId(Request $request): ?int
    {
        $user = $request->user();
        return $user instanceof MarketplaceCustomer ? $user->id : null;
    }

    /**
     * Format cart for response
     */
    protected function formatCart(MarketplaceCart $cart): array
    {
        $items = collect($cart->items ?? [])->map(function ($item, $key) {
            return [
                'key' => $key,
                'event_id' => $item['event_id'],
                'event_name' => $item['event_name'],
                'event_date' => $item['event_date'],
                'event_image' => $item['event_image'] ?? null,
                'ticket_type_id' => $item['ticket_type_id'],
                'ticket_type_name' => $item['ticket_type_name'],
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
                'total' => (float) ($item['price'] * $item['quantity']),
                'currency' => $item['currency'] ?? 'RON',
            ];
        })->values();

        return [
            'id' => $cart->id,
            'items' => $items,
            'item_count' => $cart->getItemCount(),
            'subtotal' => (float) $cart->subtotal,
            'discount' => (float) $cart->discount,
            'total' => (float) $cart->total,
            'currency' => $cart->currency,
            'promo_code' => $cart->promo_code ? [
                'code' => $cart->promo_code['code'],
                'name' => $cart->promo_code['name'] ?? $cart->promo_code['code'],
                'discount_type' => $cart->promo_code['discount_type'],
                'discount_value' => $cart->promo_code['discount_value'],
            ] : null,
            'expires_at' => $cart->expires_at?->toIso8601String(),
            'is_empty' => $cart->isEmpty(),
        ];
    }

    /**
     * Return empty cart structure
     */
    protected function emptyCart(): array
    {
        return [
            'id' => null,
            'items' => [],
            'item_count' => 0,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'currency' => 'RON',
            'promo_code' => null,
            'expires_at' => null,
            'is_empty' => true,
        ];
    }

    /**
     * Validate cart items (check availability, prices)
     */
    protected function validateCartItems(MarketplaceCart $cart): void
    {
        $items = $cart->items ?? [];
        $modified = false;

        foreach ($items as $key => $item) {
            $ticketType = MarketplaceTicketType::with('event')
                ->where('id', $item['ticket_type_id'])
                ->first();

            if (!$ticketType || $ticketType->status !== 'on_sale' || !$ticketType->event || $ticketType->event->status !== 'published') {
                // Remove unavailable items
                unset($items[$key]);
                $modified = true;
                continue;
            }

            // Check availability
            $available = $ticketType->quantity === null
                ? PHP_INT_MAX
                : $ticketType->quantity - $ticketType->quantity_sold - $ticketType->quantity_reserved;

            if ($item['quantity'] > $available) {
                if ($available <= 0) {
                    unset($items[$key]);
                } else {
                    $items[$key]['quantity'] = $available;
                }
                $modified = true;
            }

            // Update price if changed
            if ((float) $item['price'] !== (float) $ticketType->price) {
                $items[$key]['price'] = (float) $ticketType->price;
                $modified = true;
            }
        }

        if ($modified) {
            $cart->items = $items;
            $cart->recalculate();
            $cart->save();
        }
    }
}
