<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerToken;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{

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
                    'price_from' => $event->ticketTypes->min('price_max'),
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
                'meta' => [
                    'customer_name' => $order->meta['customer_name'] ?? null,
                    'customer_email' => $order->customer_email,
                    'payment_method' => $order->meta['payment_method'] ?? 'Card',
                ],
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
}
