<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Shop\ShopOrder;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    protected GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Get customer's watchlist
     */
    public function getWatchlist(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $events = $customer->watchlist()
            ->with(['venue', 'ticketTypes'])
            ->where('status', 'published')
            ->orderBy('event_date', 'asc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->getTranslation('title', app()->getLocale()),
                    'slug' => $event->slug,
                    'start_date' => $event->event_date,
                    'start_time' => $event->start_time,
                    'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                    'venue' => $event->venue ? [
                        'id' => $event->venue->id,
                        'name' => $event->venue->getTranslation('name', app()->getLocale()),
                        'city' => $event->venue->city,
                    ] : null,
                    'price_from' => $event->ticketTypes->min('display_price'),
                    'currency' => $event->ticketTypes->first()->currency ?? 'EUR',
                    'is_sold_out' => $event->ticketTypes->sum('quota_total') <= $event->ticketTypes->sum('quota_sold'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Add event to watchlist
     */
    public function addToWatchlist(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Check if event exists
        $event = \App\Models\Event::find($eventId);
        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Evenimentul nu există',
            ], 404);
        }

        // Check if already in watchlist
        if ($customer->watchlist()->where('event_id', $eventId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Evenimentul este deja în watchlist',
            ], 409);
        }

        // Add to watchlist
        $customer->watchlist()->attach($eventId);

        return response()->json([
            'success' => true,
            'message' => 'Eveniment adăugat la watchlist',
        ]);
    }

    /**
     * Remove event from watchlist
     */
    public function removeFromWatchlist(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $customer->watchlist()->detach($eventId);

        return response()->json([
            'success' => true,
            'message' => 'Eveniment șters din watchlist',
        ]);
    }

    /**
     * Check if event is in watchlist
     */
    public function checkWatchlist(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'in_watchlist' => false,
            ]);
        }

        $inWatchlist = $customer->watchlist()->where('event_id', $eventId)->exists();

        return response()->json([
            'success' => true,
            'in_watchlist' => $inWatchlist,
        ]);
    }


    /**
     * Helper: Get authenticated customer from bearer token
     */
    private function getAuthenticatedCustomer(Request $request): ?Customer
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        $customerToken = CustomerToken::where('token', hash('sha256', $token))
            ->with('customer')
            ->first();

        if (!$customerToken || $customerToken->isExpired()) {
            return null;
        }

        // Update last_used_at
        $customerToken->markAsUsed();

        return $customerToken->customer;
    }

    /**
     * Get customer's orders
     */
    public function orders(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $orders = Order::where('customer_id', $customer->id)
            ->with(['tickets.ticketType.event', 'tickets.performance'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            // Group tickets by event and ticket type
            $groupedTickets = $order->tickets->groupBy(function ($ticket) {
                return $ticket->ticket_type_id;
            })->map(function ($tickets) {
                $first = $tickets->first();
                $event = $first->ticketType?->event;
                return [
                    'event_name' => $event?->getTranslation('title', 'ro') ?? 'Unknown Event',
                    'event_slug' => $event?->slug,
                    'ticket_type' => $first->ticketType?->name ?? 'Unknown Type',
                    'quantity' => $tickets->count(),
                ];
            })->values();

            return [
                'id' => $order->id,
                'order_number' => '#' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'date' => $order->created_at->format('d M Y'),
                'total' => number_format($order->total_cents / 100, 2),
                'currency' => $order->tickets->first()?->ticketType?->currency ?? 'EUR',
                'status' => $order->status,
                'status_label' => $this->getStatusLabel($order->status),
                'items_count' => $order->tickets->count(),
                'tickets' => $groupedTickets,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
        ]);
    }

    /**
     * Get single order detail
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $order = Order::where('customer_id', $customer->id)
            ->where('id', $orderId)
            ->with(['tickets.ticketType.event.venue', 'tickets.performance'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Comanda nu a fost găsită',
            ], 404);
        }

        // Group tickets by event
        $ticketsByEvent = $order->tickets->groupBy(function ($ticket) {
            return $ticket->ticketType?->event_id;
        })->map(function ($tickets) {
            $first = $tickets->first();
            $event = $first->ticketType?->event;
            $venue = $event?->venue;

            return [
                'event' => [
                    'id' => $event?->id,
                    'title' => $event?->getTranslation('title', 'ro') ?? 'Unknown Event',
                    'slug' => $event?->slug,
                    'date' => $event?->start_date,
                    'time' => $event?->start_time,
                    'poster_url' => $event?->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                ],
                'venue' => $venue ? [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', 'ro'),
                    'city' => $venue->city,
                    'address' => $venue->address,
                ] : null,
                'tickets' => $tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'code' => $ticket->code,
                        'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($ticket->code) . "&color=181622&margin=0",
                        'ticket_type' => $ticket->ticketType?->name ?? 'Unknown Type',
                        'seat_label' => $ticket->seat_label,
                        'status' => $ticket->status,
                        'status_label' => $this->getTicketStatusLabel($ticket->status),
                        'price' => $ticket->price_cents ? $ticket->price_cents / 100 : null,
                        'currency' => $ticket->ticketType?->currency ?? 'EUR',
                    ];
                })->values(),
            ];
        })->values();

        // Check for linked shop order - first try direct link, then time-based fallback
        $linkedShopOrder = ShopOrder::where('ticket_order_id', $order->id)
            ->with(['items.product', 'items.variant'])
            ->first();

        // Fallback: If no direct link, try time-based matching (for older orders)
        if (!$linkedShopOrder && $customer) {
            $linkedShopOrder = ShopOrder::where('customer_id', $customer->id)
                ->whereNull('ticket_order_id')
                ->whereBetween('created_at', [
                    $order->created_at->subMinutes(5),
                    $order->created_at->addMinutes(5)
                ])
                ->with(['items.product', 'items.variant'])
                ->first();
        }

        $shopOrderData = null;
        if ($linkedShopOrder) {
            $shopOrderData = [
                'id' => $linkedShopOrder->id,
                'order_number' => $linkedShopOrder->order_number,
                'status' => $linkedShopOrder->status,
                'status_label' => $this->getShopOrderStatusLabel($linkedShopOrder->status),
                'subtotal_cents' => $linkedShopOrder->subtotal_cents,
                'shipping_cents' => $linkedShopOrder->shipping_cents,
                'total_cents' => $linkedShopOrder->total_cents,
                'currency' => $linkedShopOrder->currency,
                'shipping_address' => $linkedShopOrder->shipping_address,
                'shipping_method' => $linkedShopOrder->meta['shipping_method'] ?? null,
                'estimated_delivery' => $linkedShopOrder->meta['estimated_delivery'] ?? null,
                'tracking_number' => $linkedShopOrder->tracking_number,
                'tracking_url' => $linkedShopOrder->tracking_url,
                'items' => $linkedShopOrder->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->product_title,
                        'image_url' => $item->product?->image_url,
                        'variant_name' => $item->variant?->name ?? $item->variant_title,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                        'total_cents' => $item->total_cents,
                    ];
                }),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => '#' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'date' => $order->created_at->format('d M Y, H:i'),
                'total' => number_format($order->total_cents / 100, 2),
                'currency' => $order->tickets->first()?->ticketType?->currency ?? 'EUR',
                'status' => $order->status,
                'status_label' => $this->getStatusLabel($order->status),
                'items_count' => $order->tickets->count(),
                'events' => $ticketsByEvent,
                'shop_order' => $shopOrderData,
                'meta' => [
                    'customer_name' => $order->meta['customer_name'] ?? null,
                    'customer_email' => $order->customer_email,
                    'payment_method' => $order->meta['payment_method'] ?? 'Card',
                ],
            ],
        ]);
    }

    /**
     * Helper: Get shop order status label
     */
    private function getShopOrderStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'În așteptare',
            'processing' => 'În procesare',
            'shipped' => 'Expediat',
            'delivered' => 'Livrat',
            'completed' => 'Finalizată',
            'cancelled' => 'Anulată',
            'refunded' => 'Rambursată',
            default => ucfirst($status),
        };
    }

    /**
     * Get customer's shop orders
     */
    public function shopOrders(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $orders = ShopOrder::where('customer_id', $customer->id)
            ->with(['items.product', 'items.variant'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'items_count' => $order->items->sum('quantity'),
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->product_title,
                        'image_url' => $item->product?->image_url,
                        'quantity' => $item->quantity,
                    ];
                }),
                'created_at' => $order->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $formattedOrders,
            ],
        ]);
    }

    /**
     * Get single shop order detail
     */
    public function shopOrderDetail(Request $request, string $orderId): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $order = ShopOrder::where('customer_id', $customer->id)
            ->where('id', $orderId)
            ->with(['items.product', 'items.variant'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Comanda nu a fost gasita',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'subtotal_cents' => $order->subtotal_cents,
                'discount_cents' => $order->discount_cents,
                'shipping_cents' => $order->shipping_cents,
                'tax_cents' => $order->tax_cents,
                'total_cents' => $order->total_cents,
                'currency' => $order->currency,
                'payment_method' => $order->payment_method,
                'shipping_address' => $order->shipping_address,
                'billing_address' => $order->billing_address,
                'tracking_number' => $order->tracking_number,
                'tracking_url' => $order->tracking_url,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->product_title,
                        'image_url' => $item->product?->image_url,
                        'variant_name' => $item->variant?->name ?? $item->variant_title,
                        'quantity' => $item->quantity,
                        'unit_price_cents' => $item->unit_price_cents,
                        'total_cents' => $item->total_cents,
                    ];
                }),
                'created_at' => $order->created_at->toISOString(),
                'updated_at' => $order->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get customer's tickets
     */
    public function tickets(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tickets = Ticket::whereHas('order', function ($query) use ($customer) {
            $query->where('customer_id', $customer->id);
        })
            ->with(['order', 'ticketType.event', 'performance'])
            ->get();

        $formattedTickets = $tickets->map(function ($ticket) {
            $event = $ticket->ticketType?->event;
            $performance = $ticket->performance;

            return [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($ticket->code) . "&color=181622&margin=0",
                'event_name' => $event?->getTranslation('title', 'ro') ?? 'Unknown Event',
                'event_slug' => $event?->slug,
                'ticket_type' => $ticket->ticketType?->name ?? 'Unknown Type',
                'status' => $ticket->status,
                'status_label' => $this->getTicketStatusLabel($ticket->status),
                'seat_label' => $ticket->seat_label,
                'date' => $performance?->start_at ?? $event?->start_date,
                'venue' => $event?->venue?->getTranslation('name', 'ro') ?? null,
                'order_number' => '#' . str_pad($ticket->order_id, 6, '0', STR_PAD_LEFT),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedTickets,
        ]);
    }

    /**
     * Get customer profile
     */
    public function profile(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'city' => $customer->city,
                'country' => $customer->country,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'age' => $customer->age,
            ],
        ]);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'current_password' => 'sometimes|required_with:new_password|string',
            'new_password' => 'sometimes|string|min:8|confirmed',
        ]);

        // Update basic info
        if (isset($validated['first_name'])) {
            $customer->first_name = $validated['first_name'];
        }
        if (isset($validated['last_name'])) {
            $customer->last_name = $validated['last_name'];
        }
        if (array_key_exists('phone', $validated)) {
            $customer->phone = $validated['phone'];
        }

        if (isset($validated['city'])) {
            $customer->city = $validated['city'];
        }

        if (isset($validated['country'])) {
            $customer->country = $validated['country'];
        }

        if (isset($validated['date_of_birth'])) {
            $customer->date_of_birth = $validated['date_of_birth'];
            // Calculate age from date of birth
            $dob = new \DateTime($validated['date_of_birth']);
            $now = new \DateTime();
            $customer->age = $dob->diff($now)->y;
        }

        // Update password if provided
        if (isset($validated['current_password']) && isset($validated['new_password'])) {
            if (!Hash::check($validated['current_password'], $customer->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Parola curentă este incorectă.'],
                ]);
            }
            $customer->password = Hash::make($validated['new_password']);
        }

        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Profilul a fost actualizat cu succes',
            'data' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'city' => $customer->city,
                'country' => $customer->country,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'age' => $customer->age,
            ],
        ]);
    }

    /**
     * Delete customer account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Delete all customer tokens
        CustomerToken::where('customer_id', $customer->id)->delete();

        // Soft delete customer
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cont șters cu succes',
        ]);
    }

    // ==========================================
    // GAMIFICATION / LOYALTY POINTS
    // ==========================================

    /**
     * Get customer's points summary
     */
    public function points(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tenant = $request->attributes->get('tenant');

        // Check if gamification is enabled
        $hasGamification = $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasGamification) {
            return response()->json([
                'success' => false,
                'enabled' => false,
                'message' => 'Gamification not enabled',
            ]);
        }

        $summary = $this->gamificationService->getCustomerSummary($tenant->id, $customer->id);
        $config = $this->gamificationService->getConfig($tenant->id);

        return response()->json([
            'success' => true,
            'enabled' => true,
            'data' => array_merge($summary, [
                'points_name' => $config?->points_name ?? 'Points',
                'points_name_singular' => $config?->points_name_singular ?? 'Point',
                'point_value_cents' => $config?->point_value_cents ?? 1,
                'currency' => $config?->currency ?? 'RON',
                'earn_percentage' => $config?->earn_percentage ?? 0,
                'min_redeem_points' => $config?->min_redeem_points ?? 0,
                'icon' => $config?->icon ?? 'star',
            ]),
        ]);
    }

    /**
     * Get customer's points transaction history
     */
    public function pointsHistory(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tenant = $request->attributes->get('tenant');

        // Check if gamification is enabled
        $hasGamification = $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasGamification) {
            return response()->json([
                'success' => false,
                'enabled' => false,
                'message' => 'Gamification not enabled',
            ]);
        }

        $limit = min($request->input('limit', 20), 100);
        $offset = $request->input('offset', 0);

        $history = $this->gamificationService->getPointsHistory($tenant->id, $customer->id, $limit, $offset);
        $config = $this->gamificationService->getConfig($tenant->id);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $history['transactions'],
                'pagination' => $history['pagination'],
                'points_name' => $config?->points_name ?? 'Points',
                'currency' => $config?->currency ?? 'RON',
            ],
        ]);
    }

    /**
     * Get customer's referral information and stats
     */
    public function referrals(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tenant = $request->attributes->get('tenant');

        // Check if gamification is enabled
        $hasGamification = $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasGamification) {
            return response()->json([
                'success' => false,
                'enabled' => false,
                'message' => 'Gamification not enabled',
            ]);
        }

        $config = $this->gamificationService->getConfig($tenant->id);
        $customerPoints = $this->gamificationService->getCustomerPoints($tenant->id, $customer->id);

        // Get referral stats
        $referrals = \App\Models\Gamification\Referral::where('tenant_id', $tenant->id)
            ->where('referrer_customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $customerPoints->referral_code,
                'referral_link' => $customerPoints->getReferralLink(),
                'total_referrals' => $customerPoints->referral_count,
                'points_earned_from_referrals' => $customerPoints->referral_points_earned,
                'referral_bonus_points' => $config?->referral_bonus_points ?? 0,
                'referred_bonus_points' => $config?->referred_bonus_points ?? 0,
                'points_name' => $config?->points_name ?? 'Points',
                'recent_referrals' => $referrals->map(fn ($r) => [
                    'status' => $r->status,
                    'status_label' => $r->getStatusLabel(),
                    'points_awarded' => $r->referrer_points_awarded,
                    'created_at' => $r->created_at->toIso8601String(),
                    'converted_at' => $r->converted_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Get customer's tier information and progress
     */
    public function tier(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tenant = $request->attributes->get('tenant');

        // Check if gamification is enabled
        $hasGamification = $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasGamification) {
            return response()->json([
                'success' => false,
                'enabled' => false,
                'message' => 'Gamification not enabled',
            ]);
        }

        $config = $this->gamificationService->getConfig($tenant->id);
        $customerPoints = $this->gamificationService->getCustomerPoints($tenant->id, $customer->id);

        // Get tier configuration
        $tiers = $config?->tiers ?? [];
        $currentTier = $customerPoints->current_tier;
        $tierPoints = $customerPoints->tier_points;

        // Find next tier
        $nextTier = null;
        $pointsToNextTier = 0;

        $sortedTiers = collect($tiers)->sortBy('min_points')->values();
        foreach ($sortedTiers as $index => $tier) {
            if ($tier['slug'] === $currentTier && isset($sortedTiers[$index + 1])) {
                $nextTier = $sortedTiers[$index + 1];
                $pointsToNextTier = max(0, $nextTier['min_points'] - $tierPoints);
                break;
            }
        }

        // Find current tier details
        $currentTierDetails = collect($tiers)->firstWhere('slug', $currentTier);

        return response()->json([
            'success' => true,
            'data' => [
                'current_tier' => [
                    'slug' => $currentTier,
                    'name' => $currentTierDetails['name'] ?? ucfirst($currentTier),
                    'color' => $currentTierDetails['color'] ?? '#6B7280',
                    'multiplier' => $currentTierDetails['multiplier'] ?? 1.0,
                    'benefits' => $currentTierDetails['benefits'] ?? [],
                ],
                'tier_points' => $tierPoints,
                'next_tier' => $nextTier ? [
                    'slug' => $nextTier['slug'],
                    'name' => $nextTier['name'],
                    'color' => $nextTier['color'] ?? '#6B7280',
                    'min_points' => $nextTier['min_points'],
                    'points_needed' => $pointsToNextTier,
                ] : null,
                'all_tiers' => $sortedTiers->map(fn ($t) => [
                    'slug' => $t['slug'],
                    'name' => $t['name'],
                    'color' => $t['color'] ?? '#6B7280',
                    'min_points' => $t['min_points'],
                    'multiplier' => $t['multiplier'] ?? 1.0,
                    'benefits' => $t['benefits'] ?? [],
                    'is_current' => $t['slug'] === $currentTier,
                ]),
                'points_name' => $config?->points_name ?? 'Points',
            ],
        ]);
    }

    /**
     * Helper: Get order status label
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'În așteptare',
            'paid' => 'Plătită',
            'cancelled' => 'Anulată',
            'refunded' => 'Rambursată',
            default => ucfirst($status),
        };
    }

    /**
     * Helper: Get ticket status label
     */
    private function getTicketStatusLabel(string $status): string
    {
        return match ($status) {
            'valid' => 'Valabil',
            'used' => 'Folosit',
            'cancelled' => 'Anulat',
            'refunded' => 'Rambursat',
            default => ucfirst($status),
        };
    }

    // ==========================================
    // AFFILIATE STATUS (quick check)
    // ==========================================

    /**
     * Get customer's affiliate status (quick check for account page)
     */
    public function affiliateStatus(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $tenant = $request->attributes->get('tenant');

        // Check if affiliates microservice is enabled
        $hasAffiliates = $tenant->microservices()
            ->where('slug', 'affiliates')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasAffiliates) {
            return response()->json([
                'success' => true,
                'enabled' => false,
                'has_affiliate' => false,
            ]);
        }

        // Check if customer has an affiliate account
        $affiliate = \App\Models\Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        $settings = \App\Models\AffiliateSettings::where('tenant_id', $tenant->id)->first();

        if (!$affiliate) {
            return response()->json([
                'success' => true,
                'enabled' => true,
                'has_affiliate' => false,
                'can_register' => $settings?->allow_self_registration ?? true,
            ]);
        }

        // Return quick summary
        return response()->json([
            'success' => true,
            'enabled' => true,
            'has_affiliate' => true,
            'data' => [
                'code' => $affiliate->code,
                'status' => $affiliate->status,
                'status_label' => $affiliate->getStatusLabel(),
                'is_active' => $affiliate->isActive(),
                'available_balance' => (float) $affiliate->available_balance,
                'pending_balance' => (float) $affiliate->pending_balance,
                'total_earned' => $affiliate->total_commission,
                'currency' => $settings?->currency ?? 'RON',
            ],
        ]);
    }
}
