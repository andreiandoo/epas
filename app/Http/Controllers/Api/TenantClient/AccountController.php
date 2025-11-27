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
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
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
            return [
                'id' => $order->id,
                'order_number' => '#' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
                'date' => $order->created_at->format('d M Y'),
                'total' => number_format($order->total_cents / 100, 2),
                'currency' => 'EUR',
                'status' => $order->status,
                'status_label' => $this->getStatusLabel($order->status),
                'items_count' => $order->tickets->count(),
                'tickets' => $order->tickets->map(function ($ticket) {
                    $event = $ticket->ticketType?->event;
                    return [
                        'event_name' => $event?->getTranslation('title', 'ro') ?? 'Unknown Event',
                        'ticket_type' => $ticket->ticketType?->name ?? 'Unknown Type',
                        'quantity' => 1,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders,
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
                'qr_code' => url("/api/tickets/{$ticket->code}/qr"),
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
