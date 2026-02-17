<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Order;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\MarketplaceTransaction;
use App\Notifications\MarketplaceOrderNotification;
use App\Services\MarketplaceWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrdersController extends BaseController
{
    /**
     * Create a new order (reserve tickets)
     */
    public function create(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'tickets' => 'required|array|min:1',
            'tickets.*.ticket_type_id' => 'required|integer|exists:ticket_types,id',
            'tickets.*.quantity' => 'required|integer|min:1|max:20',
            'customer' => 'required|array',
            'customer.email' => 'required|email',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'required|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
        ]);

        $event = Event::find($request->event_id);

        if (!$event || $event->status !== 'published') {
            return $this->error('Event not available', 400);
        }

        // Derive tenant_id: prefer event's, then fallback chain
        $tenantId = $event->tenant_id;
        if (!$tenantId) {
            // Try other events from same organizer that have tenant_id
            if ($event->marketplace_organizer_id) {
                $tenantId = Event::where('marketplace_organizer_id', $event->marketplace_organizer_id)
                    ->whereNotNull('tenant_id')
                    ->value('tenant_id');
            }
        }
        if (!$tenantId) {
            // Try the client's tenant list (active or any)
            $tenantId = $client->activeTenants()->first()?->id
                ?? $client->tenants()->first()?->id;
        }
        if (!$tenantId) {
            // Last resort: find any tenant in the system (single-tenant setups)
            $tenantId = \App\Models\Tenant::first()?->id;
        }
        if (!$tenantId) {
            return $this->error('No tenant configured. Please contact support.', 400);
        }

        if (!$client->canSellForTenant($tenantId)) {
            return $this->error('Not authorized to sell tickets for this event', 403);
        }

        try {
            DB::beginTransaction();

            // Find or create customer
            $customer = Customer::firstOrCreate(
                [
                    'email' => $request->input('customer.email'),
                    'tenant_id' => $tenantId,
                ],
                [
                    'first_name' => $request->input('customer.first_name'),
                    'last_name' => $request->input('customer.last_name'),
                    'phone' => $request->input('customer.phone'),
                ]
            );

            // Calculate totals and validate availability
            $orderItems = [];
            $subtotal = 0;
            $commission = $client->getCommissionForTenant($tenantId);

            foreach ($request->tickets as $ticketRequest) {
                $ticketType = TicketType::where('id', $ticketRequest['ticket_type_id'])
                    ->where('event_id', $event->id)
                    ->whereIn('status', ['active', 'on_sale', 'published'])
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType) {
                    throw new \Exception("Ticket type {$ticketRequest['ticket_type_id']} not available");
                }

                $quantity = (int) $ticketRequest['quantity'];

                if ($ticketType->available_quantity < $quantity) {
                    throw new \Exception("Not enough tickets available for {$ticketType->name}");
                }

                if ($ticketType->max_per_order && $quantity > $ticketType->max_per_order) {
                    throw new \Exception("Maximum {$ticketType->max_per_order} tickets per order for {$ticketType->name}");
                }

                $itemTotal = $ticketType->price * $quantity;
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'ticket_type' => $ticketType,
                    'quantity' => $quantity,
                    'unit_price' => $ticketType->price,
                    'total' => $itemTotal,
                ];

                // Reserve tickets (increment quota_sold; available_quantity is computed)
                $ticketType->increment('quota_sold', $quantity);
            }

            // Calculate commission
            $commissionAmount = $subtotal * ($commission / 100);
            $total = $subtotal + $commissionAmount;

            // Create order
            $order = Order::create([
                'tenant_id' => $tenantId,
                'event_id' => $event->id,
                'customer_id' => $customer->id,
                'order_number' => 'MPC-' . strtoupper(Str::random(8)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'commission_rate' => $commission,
                'commission_amount' => $commissionAmount,
                'total' => $total,
                'currency' => 'RON',
                'source' => $request->input('source', 'marketplace'),
                'marketplace_client_id' => $client->id,
                'customer_email' => $customer->email,
                'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                'customer_phone' => $customer->phone,
                'expires_at' => now()->addMinutes(15), // 15 minute reservation
                'metadata' => [
                    'marketplace_client' => $client->name,
                    'ip_address' => $request->ip(),
                ],
            ]);

            // Create order items and tickets
            foreach ($orderItems as $item) {
                $orderItem = $order->items()->create([
                    'ticket_type_id' => $item['ticket_type']->id,
                    'name' => $item['ticket_type']->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                ]);

                // Create pending tickets
                for ($i = 0; $i < $item['quantity']; $i++) {
                    Ticket::create([
                        'tenant_id' => $tenantId,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'event_id' => $event->id,
                        'ticket_type_id' => $item['ticket_type']->id,
                        'customer_id' => $customer->id,
                        'barcode' => Str::uuid()->toString(),
                        'status' => 'pending',
                        'price' => $item['unit_price'],
                    ]);
                }
            }

            // Auto-confirm POS cash orders immediately
            $paymentMethod = $request->input('payment_method');
            $source = $request->input('source', 'marketplace');
            if ($paymentMethod === 'cash' && $source === 'pos_app') {
                $order->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
                // Mark tickets as valid
                $order->tickets()->update(['status' => 'valid']);
            }

            DB::commit();

            // Reload order to get updated status
            $order->refresh();

            Log::info('Marketplace order created', [
                'order_id' => $order->id,
                'marketplace_client_id' => $client->id,
                'tenant_id' => $tenantId,
                'total' => $total,
            ]);

            // Send webhook notification (async)
            dispatch(function () use ($client, $order) {
                app(MarketplaceWebhookService::class)->orderCreated($client, [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'currency' => $order->currency,
                ]);
            })->afterResponse();

            return $this->success([
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'subtotal' => $order->subtotal,
                    'commission_amount' => $order->commission_amount,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'expires_at' => $order->expires_at->toIso8601String(),
                ],
                'payment_url' => route('marketplace.payment', ['order' => $order->id]),
            ], 'Order created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create marketplace order', [
                'marketplace_client_id' => $client->id,
                'event_id' => $request->event_id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Get order status
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::with(['items.ticketType', 'tickets'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal,
                'commission_amount' => $order->commission_amount,
                'total' => $order->total,
                'currency' => $order->currency,
                'customer' => [
                    'email' => $order->customer_email,
                    'name' => $order->customer_name,
                    'phone' => $order->customer_phone,
                ],
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                    ];
                }),
                'tickets' => $order->status === 'completed' ? $order->tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'barcode' => $ticket->barcode,
                        'status' => $ticket->status,
                        'ticket_type' => $ticket->ticketType?->name,
                    ];
                }) : [],
                'created_at' => $order->created_at->toIso8601String(),
                'expires_at' => $order->expires_at?->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * List orders for this marketplace client
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Order::where('marketplace_client_id', $client->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders);
    }

    /**
     * Cancel an order (if still pending)
     */
    public function cancel(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if ($order->status !== 'pending') {
            return $this->error('Only pending orders can be cancelled', 400);
        }

        try {
            DB::beginTransaction();

            // Restore ticket availability
            foreach ($order->items as $item) {
                TicketType::where('id', $item->ticket_type_id)
                    ->increment('available_quantity', $item->quantity);
            }

            // Update order status
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Update ticket statuses
            $order->tickets()->update(['status' => 'cancelled']);

            DB::commit();

            Log::info('Marketplace order cancelled', [
                'order_id' => $order->id,
                'marketplace_client_id' => $client->id,
            ]);

            // Send webhook notification (async)
            dispatch(function () use ($client, $order) {
                app(MarketplaceWebhookService::class)->orderCancelled($client, [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => 'cancelled',
                ]);
            })->afterResponse();

            return $this->success(null, 'Order cancelled successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to cancel order', 500);
        }
    }

    /**
     * Refund a completed order
     */
    public function refund(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
            'refund_type' => 'nullable|in:full,partial',
        ]);

        $order = Order::with('marketplaceOrganizer')
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if ($order->status !== 'completed') {
            return $this->error('Only completed orders can be refunded', 400);
        }

        if ($order->refunded_at) {
            return $this->error('Order has already been refunded', 400);
        }

        $refundType = $request->input('refund_type', 'full');
        $refundAmount = $refundType === 'full'
            ? (float) $order->total
            : (float) $request->input('amount', $order->total);

        if ($refundAmount > (float) $order->total) {
            return $this->error('Refund amount cannot exceed order total', 400);
        }

        try {
            DB::beginTransaction();

            // Calculate commission refund proportionally
            $refundRatio = $refundAmount / (float) $order->total;
            $commissionRefund = round((float) $order->commission_amount * $refundRatio, 2);
            $netRefund = $refundAmount - $commissionRefund;

            // Update order
            $order->update([
                'status' => $refundType === 'full' ? 'refunded' : 'partially_refunded',
                'refunded_at' => now(),
                'refund_amount' => $refundAmount,
                'refund_reason' => $request->reason,
            ]);

            // Invalidate tickets if full refund
            if ($refundType === 'full') {
                $order->tickets()->update(['status' => 'refunded']);

                // Restore ticket availability
                foreach ($order->items as $item) {
                    TicketType::where('id', $item->ticket_type_id)
                        ->increment('available_quantity', $item->quantity);
                }
            }

            // Record transaction to deduct from organizer balance
            if ($order->marketplace_organizer_id && $order->marketplaceOrganizer) {
                MarketplaceTransaction::recordRefund(
                    $order->marketplace_client_id,
                    $order->marketplace_organizer_id,
                    $netRefund,
                    $commissionRefund,
                    $order->id,
                    $order->currency
                );

                // Update organizer stats
                $order->marketplaceOrganizer->updateStats();
            }

            DB::commit();

            Log::info('Marketplace order refunded', [
                'order_id' => $order->id,
                'marketplace_client_id' => $client->id,
                'refund_amount' => $refundAmount,
                'refund_type' => $refundType,
            ]);

            // Send webhook notification (async)
            dispatch(function () use ($client, $order, $refundAmount, $refundType) {
                app(MarketplaceWebhookService::class)->orderRefunded($client, [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'refund_amount' => $refundAmount,
                    'refund_type' => $refundType,
                    'refund_reason' => $order->refund_reason,
                    'refunded_at' => $order->refunded_at->toIso8601String(),
                ]);
            })->afterResponse();

            // Send refund notification email to customer
            if ($order->customer_email) {
                dispatch(function () use ($order) {
                    Notification::route('mail', $order->customer_email)
                        ->notify(new MarketplaceOrderNotification($order->fresh(), 'refunded'));
                })->afterResponse();
            }

            return $this->success([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'refund_amount' => $refundAmount,
                'refund_type' => $refundType,
            ], 'Order refunded successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to refund marketplace order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to refund order: ' . $e->getMessage(), 500);
        }
    }
}
