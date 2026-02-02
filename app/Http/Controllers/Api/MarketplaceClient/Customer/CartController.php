<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCart;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceTicketType;
use App\Models\MarketplacePromoCode;
use App\Services\Seating\SeatHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends BaseController
{
    public function __construct(
        protected SeatHoldService $seatHoldService
    ) {}
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
     * Add item with specific seats to cart (for seated events)
     * This method holds the seats for 15 minutes
     */
    public function addItemWithSeats(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'ticket_type_id' => 'required|integer',
            'event_seating_id' => 'required|integer',
            'seat_uids' => 'required|array|min:1|max:10',
            'seat_uids.*' => 'required|string|max:32',
            'seats' => 'nullable|array', // Optional seat details for display
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

        $sessionId = $this->getSessionId($request);
        $quantity = count($validated['seat_uids']);

        // Check max per order
        if ($ticketType->max_per_order && $quantity > $ticketType->max_per_order) {
            return $this->error("Maximum {$ticketType->max_per_order} tickets per order", 400);
        }

        // Try to hold the seats
        try {
            $holdResult = $this->seatHoldService->holdSeats(
                $validated['event_seating_id'],
                $validated['seat_uids'],
                $sessionId
            );

            // Check if all seats were held
            if (!empty($holdResult['failed'])) {
                $failedUids = array_column($holdResult['failed'], 'seat_uid');
                $reasons = array_column($holdResult['failed'], 'reason');

                Log::warning('CartController: Some seats could not be held', [
                    'event_id' => $event->id,
                    'session_id' => $sessionId,
                    'failed' => $holdResult['failed'],
                ]);

                // Release any seats that were held
                if (!empty($holdResult['held'])) {
                    $this->seatHoldService->releaseSeats(
                        $validated['event_seating_id'],
                        $holdResult['held'],
                        $sessionId
                    );
                }

                return $this->error('Some seats are no longer available', 409, [
                    'unavailable_seats' => $failedUids,
                    'reasons' => $reasons,
                ]);
            }

            // All seats held successfully - add to cart
            $customerId = $this->getCustomerId($request);
            $cart = MarketplaceCart::getOrCreate($sessionId, $client->id, $customerId);

            // Remove existing item for this ticket type (seats replace, not add)
            $itemKey = $event->id . '_' . $ticketType->id;
            $items = $cart->items ?? [];

            // If there are existing seats for this item, release them first
            if (isset($items[$itemKey]['seat_uids']) && !empty($items[$itemKey]['seat_uids'])) {
                $this->seatHoldService->releaseSeats(
                    $items[$itemKey]['event_seating_id'],
                    $items[$itemKey]['seat_uids'],
                    $sessionId
                );
            }

            // Add/update item with seat information
            $cart->addItem([
                'event_id' => $event->id,
                'event_name' => $event->name,
                'event_date' => $event->starts_at->toIso8601String(),
                'event_image' => $event->image,
                'ticket_type_id' => $ticketType->id,
                'ticket_type_name' => $ticketType->name,
                'price' => (float) $ticketType->price,
                'quantity' => $quantity,
                'currency' => $ticketType->currency,
                'event_seating_id' => $validated['event_seating_id'],
                'seat_uids' => $holdResult['held'],
                'seats' => $validated['seats'] ?? [], // Display info (section, row, seat labels)
                'hold_expires_at' => $holdResult['expires_at'],
            ]);

            // Extend cart expiration to match hold TTL (15 minutes)
            $cart->extendExpiration(15);
            $cart->save();

            Log::info('CartController: Added seats to cart', [
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'session_id' => $sessionId,
                'seats_held' => count($holdResult['held']),
                'expires_at' => $holdResult['expires_at'],
            ]);

            return $this->success([
                'cart' => $this->formatCart($cart),
                'message' => 'Seats added to cart',
                'hold_expires_at' => $holdResult['expires_at'],
            ]);

        } catch (\Exception $e) {
            Log::error('CartController: Failed to hold seats', [
                'event_id' => $event->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to reserve seats. Please try again.', 500);
        }
    }

    /**
     * Release seats for a cart item (without removing the item)
     * Used when customer deselects seats but keeps the ticket type
     */
    public function releaseSeats(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'event_seating_id' => 'required|integer',
            'seat_uids' => 'required|array|min:1',
            'seat_uids.*' => 'required|string|max:32',
        ]);

        $sessionId = $this->getSessionId($request);

        try {
            $result = $this->seatHoldService->releaseSeats(
                $validated['event_seating_id'],
                $validated['seat_uids'],
                $sessionId
            );

            Log::info('CartController: Released seats', [
                'session_id' => $sessionId,
                'released' => count($result['released']),
            ]);

            return $this->success([
                'released' => $result['released'],
                'message' => 'Seats released',
            ]);

        } catch (\Exception $e) {
            Log::error('CartController: Failed to release seats', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to release seats', 500);
        }
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
     * Remove item from cart (and release any held seats)
     */
    public function removeItem(Request $request, string $itemKey): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart) {
            return $this->error('Cart not found', 404);
        }

        $items = $cart->items ?? [];
        if (!isset($items[$itemKey])) {
            return $this->error('Item not found in cart', 404);
        }

        // Release any held seats before removing the item
        $item = $items[$itemKey];
        if (!empty($item['seat_uids']) && !empty($item['event_seating_id'])) {
            try {
                $this->seatHoldService->releaseSeats(
                    $item['event_seating_id'],
                    $item['seat_uids'],
                    $sessionId
                );

                Log::info('CartController: Released seats on item removal', [
                    'session_id' => $sessionId,
                    'item_key' => $itemKey,
                    'seats_released' => count($item['seat_uids']),
                ]);
            } catch (\Exception $e) {
                Log::warning('CartController: Failed to release seats on item removal', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
                // Continue with removal even if seat release fails
            }
        }

        $cart->removeItem($itemKey);
        $cart->save();

        return $this->success([
            'cart' => $this->formatCart($cart),
            'message' => 'Item removed from cart',
        ]);
    }

    /**
     * Clear cart (and release all held seats)
     */
    public function clear(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if ($cart) {
            // Release all held seats before clearing
            $items = $cart->items ?? [];
            foreach ($items as $itemKey => $item) {
                if (!empty($item['seat_uids']) && !empty($item['event_seating_id'])) {
                    try {
                        $this->seatHoldService->releaseSeats(
                            $item['event_seating_id'],
                            $item['seat_uids'],
                            $sessionId
                        );

                        Log::info('CartController: Released seats on cart clear', [
                            'session_id' => $sessionId,
                            'event_seating_id' => $item['event_seating_id'],
                            'seats_released' => count($item['seat_uids']),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('CartController: Failed to release seats on cart clear', [
                            'session_id' => $sessionId,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue clearing even if seat release fails
                    }
                }
            }

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
     * SECURITY FIX: Generate cryptographically secure session IDs
     */
    protected function getSessionId(Request $request): string
    {
        // Try Laravel session first (most secure)
        try {
            if ($request->hasSession() && ($sessionId = $request->session()->getId())) {
                if (strlen($sessionId) >= 32) {
                    return $sessionId;
                }
            }
        } catch (\Exception $e) {
            // Session not available, fall through
        }

        // Try cookie (must be validated format)
        $cookieSession = $request->cookie('session_id');
        if ($cookieSession && preg_match('/^[a-zA-Z0-9]{32,64}$/', $cookieSession)) {
            return $cookieSession;
        }

        // SECURITY FIX: Generate cryptographically secure random ID
        // Previously was: md5($request->ip() . $request->userAgent()) - PREDICTABLE!
        return bin2hex(random_bytes(32));
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
            $formattedItem = [
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

            // Include seat information if present
            if (!empty($item['seat_uids'])) {
                $formattedItem['has_seats'] = true;
                $formattedItem['event_seating_id'] = $item['event_seating_id'] ?? null;
                $formattedItem['seat_uids'] = $item['seat_uids'];
                $formattedItem['seats'] = $item['seats'] ?? [];
                $formattedItem['hold_expires_at'] = $item['hold_expires_at'] ?? null;
            } else {
                $formattedItem['has_seats'] = false;
            }

            return $formattedItem;
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
