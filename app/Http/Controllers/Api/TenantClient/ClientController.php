<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    /**
     * Register a new client
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:50',
        ]);

        // Check if email exists for this tenant
        $existingClient = $tenant->clients()->where('email', $validated['email'])->first();
        if ($existingClient) {
            return response()->json([
                'success' => false,
                'message' => 'Email already registered.',
            ], 422);
        }

        $client = $tenant->clients()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        $token = $client->createToken('client-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Login client
     */
    public function login(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $client = $tenant->clients()->where('email', $validated['email'])->first();

        if (!$client || !Hash::check($validated['password'], $client->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $client->createToken('client-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout client
     */
    public function logout(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');
        $client->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get client profile
     */
    public function profile(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'created_at' => $client->created_at,
            ],
        ]);
    }

    /**
     * Update client profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'current_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:8|confirmed',
        ]);

        // Check email uniqueness
        $tenant = $request->attributes->get('tenant');
        $existingClient = $tenant->clients()
            ->where('email', $validated['email'])
            ->where('id', '!=', $client->id)
            ->first();

        if ($existingClient) {
            return response()->json([
                'success' => false,
                'message' => 'Email already in use.',
            ], 422);
        }

        $client->name = $validated['name'];
        $client->email = $validated['email'];
        $client->phone = $validated['phone'] ?? $client->phone;

        // Update password if provided
        if (!empty($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $client->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ], 422);
            }
            $client->password = Hash::make($validated['new_password']);
        }

        $client->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Get client orders
     */
    public function orders(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $orders = $client->orders()
            ->with(['tickets.event', 'tickets.ticketType'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $formattedOrders = $orders->getCollection()->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at,
                'tickets_count' => $order->tickets->count(),
                'events' => $order->tickets->pluck('event.name')->unique()->values(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $formattedOrders,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
            ],
        ]);
    }

    /**
     * Get single order details
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $client = $request->attributes->get('client');

        $order = $client->orders()
            ->with(['tickets.event', 'tickets.ticketType'])
            ->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'subtotal' => $order->subtotal,
                'fees' => $order->fees,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'billing_name' => $order->billing_name,
                'billing_email' => $order->billing_email,
                'created_at' => $order->created_at,
                'tickets' => $order->tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'status' => $ticket->status,
                        'event' => [
                            'id' => $ticket->event->id,
                            'name' => $ticket->event->name,
                            'date' => $ticket->event->start_date,
                            'venue' => $ticket->event->venue_name,
                        ],
                        'ticket_type' => $ticket->ticketType->name ?? 'General',
                        'price' => $ticket->price,
                        'seat' => $ticket->seat_number,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get client tickets
     */
    public function tickets(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $tickets = $client->tickets()
            ->with(['event', 'ticketType', 'order'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $formattedTickets = $tickets->getCollection()->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'event' => [
                    'id' => $ticket->event->id,
                    'name' => $ticket->event->name,
                    'date' => $ticket->event->start_date,
                    'venue' => $ticket->event->venue_name,
                    'image' => $ticket->event->image_url,
                ],
                'ticket_type' => $ticket->ticketType->name ?? 'General',
                'price' => $ticket->price,
                'seat' => $ticket->seat_number,
                'qr_code' => $ticket->qr_code_url,
                'order_number' => $ticket->order->order_number,
                'created_at' => $ticket->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'tickets' => $formattedTickets,
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ],
        ]);
    }

    /**
     * Get upcoming events for client
     */
    public function upcomingEvents(Request $request): JsonResponse
    {
        $client = $request->attributes->get('client');

        $eventIds = $client->tickets()
            ->whereHas('event', function ($query) {
                $query->where('start_date', '>=', now());
            })
            ->pluck('event_id')
            ->unique();

        $tenant = $request->attributes->get('tenant');
        $events = $tenant->events()
            ->whereIn('id', $eventIds)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->get()
            ->map(function ($event) use ($client) {
                $ticketCount = $client->tickets()->where('event_id', $event->id)->count();
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'description' => $event->short_description,
                    'date' => $event->start_date,
                    'end_date' => $event->end_date,
                    'venue' => $event->venue_name,
                    'address' => $event->venue_address,
                    'image' => $event->image_url,
                    'tickets_count' => $ticketCount,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => ['events' => $events],
        ]);
    }

    /**
     * Get cart contents
     */
    public function cart(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $cartId = $request->header('X-Cart-Id') ?? $request->input('cart_id');

        if (!$cartId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'items' => [],
                    'subtotal' => 0,
                    'fees' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $cart = $tenant->carts()->where('session_id', $cartId)->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'data' => [
                    'items' => [],
                    'subtotal' => 0,
                    'fees' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $items = $cart->items()->with(['event', 'ticketType'])->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'event' => [
                    'id' => $item->event->id,
                    'name' => $item->event->name,
                    'date' => $item->event->start_date,
                    'image' => $item->event->image_url,
                ],
                'ticket_type' => [
                    'id' => $item->ticketType->id,
                    'name' => $item->ticketType->name,
                ],
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->quantity * $item->price,
            ];
        });

        $subtotal = $items->sum('total');
        $fees = $subtotal * 0.05; // 5% service fee
        $total = $subtotal + $fees;

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'subtotal' => round($subtotal, 2),
                'fees' => round($fees, 2),
                'total' => round($total, 2),
                'currency' => $tenant->currency ?? 'RON',
            ],
        ]);
    }

    /**
     * Add item to cart
     */
    public function addToCart(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'ticket_type_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10',
            'cart_id' => 'nullable|string',
        ]);

        // Get or create cart
        $cartId = $validated['cart_id'] ?? Str::uuid()->toString();
        $cart = $tenant->carts()->firstOrCreate(
            ['session_id' => $cartId],
            ['expires_at' => now()->addHours(2)]
        );

        // Get event and ticket type
        $event = $tenant->events()->findOrFail($validated['event_id']);
        $ticketType = $event->ticketTypes()->findOrFail($validated['ticket_type_id']);

        // Check availability
        $available = $ticketType->quantity - $ticketType->sold;
        if ($validated['quantity'] > $available) {
            return response()->json([
                'success' => false,
                'message' => "Only {$available} tickets available.",
            ], 422);
        }

        // Add or update cart item
        $cartItem = $cart->items()->where('event_id', $event->id)
            ->where('ticket_type_id', $ticketType->id)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $validated['quantity'];
            $cartItem->save();
        } else {
            $cart->items()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $validated['quantity'],
                'price' => $ticketType->price,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Added to cart.',
            'data' => ['cart_id' => $cartId],
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItem(Request $request, int $itemId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $cartId = $request->header('X-Cart-Id') ?? $request->input('cart_id');

        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        $cart = $tenant->carts()->where('session_id', $cartId)->first();
        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found.'], 404);
        }

        $item = $cart->items()->find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        if ($validated['quantity'] === 0) {
            $item->delete();
        } else {
            $item->quantity = $validated['quantity'];
            $item->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart updated.',
        ]);
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(Request $request, int $itemId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $cartId = $request->header('X-Cart-Id') ?? $request->input('cart_id');

        $cart = $tenant->carts()->where('session_id', $cartId)->first();
        if (!$cart) {
            return response()->json(['success' => false, 'message' => 'Cart not found.'], 404);
        }

        $item = $cart->items()->find($itemId);
        if ($item) {
            $item->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart.',
        ]);
    }

    /**
     * Process checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $client = $request->attributes->get('client');

        $validated = $request->validate([
            'cart_id' => 'required|string',
            'billing_name' => 'required|string|max:255',
            'billing_email' => 'required|email|max:255',
            'billing_phone' => 'nullable|string|max:50',
            'billing_address' => 'nullable|string|max:500',
            'payment_method' => 'required|string|in:stripe,netopia,euplatesc,payu',
        ]);

        $cart = $tenant->carts()->where('session_id', $validated['cart_id'])->first();
        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty.',
            ], 422);
        }

        // Calculate totals
        $subtotal = $cart->items->sum(fn($item) => $item->quantity * $item->price);
        $fees = $subtotal * 0.05;
        $total = $subtotal + $fees;

        // Create order
        $order = $tenant->orders()->create([
            'client_id' => $client?->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'status' => 'pending',
            'subtotal' => $subtotal,
            'fees' => $fees,
            'total' => $total,
            'currency' => $tenant->currency ?? 'RON',
            'payment_method' => $validated['payment_method'],
            'billing_name' => $validated['billing_name'],
            'billing_email' => $validated['billing_email'],
            'billing_phone' => $validated['billing_phone'] ?? null,
            'billing_address' => $validated['billing_address'] ?? null,
        ]);

        // Create tickets for each cart item
        foreach ($cart->items as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                $order->tickets()->create([
                    'client_id' => $client?->id,
                    'event_id' => $item->event_id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
                    'status' => 'pending',
                    'price' => $item->price,
                ]);
            }
        }

        // Generate payment URL based on processor
        $paymentUrl = $this->generatePaymentUrl($tenant, $order, $validated['payment_method']);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_url' => $paymentUrl,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Generate payment URL for processor
     */
    protected function generatePaymentUrl($tenant, $order, string $processor): string
    {
        $baseUrl = config('app.url');

        // In production, this would integrate with actual payment processors
        // For now, return a simulated payment URL
        return "{$baseUrl}/api/tenant-client/payment/{$order->id}/process?processor={$processor}";
    }

    /**
     * Handle payment callback/confirmation
     */
    public function paymentCallback(Request $request, int $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $order = $tenant->orders()->find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        // In production, verify payment with processor
        // For now, mark as completed
        $order->status = 'completed';
        $order->paid_at = now();
        $order->save();

        // Update tickets status
        $order->tickets()->update(['status' => 'valid']);

        // Update ticket type sold counts
        foreach ($order->tickets as $ticket) {
            $ticket->ticketType->increment('sold');
        }

        // Clear cart
        $cartId = $request->input('cart_id');
        if ($cartId) {
            $cart = $tenant->carts()->where('session_id', $cartId)->first();
            if ($cart) {
                $cart->items()->delete();
                $cart->delete();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'status' => 'completed',
            ],
        ]);
    }

    /**
     * Get order for thank you page
     */
    public function orderConfirmation(Request $request, string $orderNumber): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $order = $tenant->orders()
            ->with(['tickets.event', 'tickets.ticketType'])
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'currency' => $order->currency,
                'billing_email' => $order->billing_email,
                'created_at' => $order->created_at,
                'tickets' => $order->tickets->map(function ($ticket) {
                    return [
                        'ticket_number' => $ticket->ticket_number,
                        'event_name' => $ticket->event->name,
                        'event_date' => $ticket->event->start_date,
                        'ticket_type' => $ticket->ticketType->name ?? 'General',
                    ];
                }),
            ],
        ]);
    }
}
