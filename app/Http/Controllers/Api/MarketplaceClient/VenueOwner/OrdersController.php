<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\OrdersController as MarketplaceOrdersController;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Venue Owner POS endpoints. The mobile app reuses the organizer SalesScreen
 * verbatim — only the URL gets rewritten via the path-rewriter when userType
 * is venue_owner. So this controller accepts the SAME payload as
 * MarketplaceOrdersController::create (placeholder POS customer, no buyer
 * details required, only is_entry_ticket types are sold from the mobile UI).
 *
 * Scoping invariants:
 *  - The event MUST be hosted at a venue owned by this tenant AND partnered
 *    with the current marketplace. Enforced before any DB writes.
 *  - The order is always tagged source='venue_owner_pos' + meta.sold_by
 *    "Venue: {tenant}" so the organizer's existing sales-breakdown surfaces
 *    venue-owner revenue as a distinct row in `by_user`.
 *
 * For order-level operations (generate-claim-url, pos-complete, send-tickets,
 * sales-breakdown) we delegate to MarketplaceOrdersController after a venue
 * scope check, so they stay in lockstep with the organizer side.
 */
class OrdersController extends BaseController
{
    public function create(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');
        $user = $request->user();

        if (!$tenant instanceof Tenant || !$user instanceof User) {
            return $this->error('Venue owner context not resolved', 500);
        }

        $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'tickets' => 'required|array|min:1',
            'tickets.*.ticket_type_id' => 'required|integer|exists:ticket_types,id',
            'tickets.*.quantity' => 'required|integer|min:1|max:20',
            'payment_method' => 'nullable|in:cash,card,tap',
            // Customer object kept for forward compat with the organizer
            // SalesScreen payload — but every field is optional here. The
            // venue owner UI sends the same placeholder ("pos@ambilet.ro"
            // / "POS" / "Numerar") and we honour it as-is.
            'customer' => 'nullable|array',
            'customer.email' => 'nullable|email',
            'customer.first_name' => 'nullable|string|max:255',
            'customer.last_name' => 'nullable|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
        ]);

        $event = Event::with('venue')->find($request->event_id);
        if (!$event || $event->status !== 'published') {
            return $this->error('Event not available', 400);
        }

        $scopeError = $this->ensureEventAtVenue($event, $client->id, (int) $tenant->id);
        if ($scopeError) {
            return $scopeError;
        }

        // Tenant of the order is the organizer's tenant (so the sale lands in
        // the organizer's books). The venue owner is recorded only via meta.
        $organizerTenantId = $event->tenant_id;
        if (!$organizerTenantId && $event->marketplace_organizer_id) {
            $organizerTenantId = Event::where('marketplace_organizer_id', $event->marketplace_organizer_id)
                ->whereNotNull('tenant_id')
                ->value('tenant_id');
        }
        if (!$organizerTenantId) {
            return $this->error('Organizer tenant not configured for this event', 400);
        }

        if (!$client->canSellForTenant($organizerTenantId)) {
            return $this->error('Not authorized to sell tickets for this event', 403);
        }

        // Placeholder customer (matches organizer SalesScreen behavior). The
        // operator can later attach a real email via the send-tickets flow.
        $payment = $request->input('payment_method', 'cash');
        $customerEmail = strtolower(trim($request->input('customer.email', 'pos@ambilet.ro')));
        $customerFirstName = $request->input('customer.first_name', 'POS');
        $customerLastName = $request->input('customer.last_name', $payment === 'cash' ? 'Numerar' : 'Card');

        $customer = Customer::where('email', $customerEmail)
            ->where('tenant_id', $organizerTenantId)
            ->first();

        if ($customer) {
            $customer->update([
                'first_name' => $customerFirstName,
                'last_name' => $customerLastName,
                'phone' => $request->input('customer.phone') ?: $customer->phone,
            ]);
        } else {
            $customer = Customer::create([
                'email' => $customerEmail,
                'tenant_id' => $organizerTenantId,
                'primary_tenant_id' => $organizerTenantId,
                'first_name' => $customerFirstName,
                'last_name' => $customerLastName,
                'phone' => $request->input('customer.phone'),
            ]);
        }

        DB::table('customer_tenant')->insertOrIgnore([
            'customer_id' => $customer->id,
            'tenant_id' => $organizerTenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantName = $tenant->public_name ?? $tenant->company_name ?? $tenant->name ?? 'Venue';
        $soldByLabel = 'Venue: ' . $tenantName;

        try {
            DB::beginTransaction();

            $orderItems = [];
            $subtotal = 0;
            $commission = $client->getCommissionForTenant($organizerTenantId);

            foreach ($request->tickets as $ticketRequest) {
                $ticketType = TicketType::where('id', $ticketRequest['ticket_type_id'])
                    ->where('event_id', $event->id)
                    ->whereIn('status', ['active', 'on_sale', 'published'])
                    ->lockForUpdate()
                    ->first();

                if (!$ticketType) {
                    throw new \Exception("Ticket type {$ticketRequest['ticket_type_id']} not available");
                }

                // Mobile-only constraint: only entry tickets are sold from the
                // POS / venue-owner sales screen. Same rule the organizer
                // SalesScreen enforces client-side; we enforce it server-side
                // too so a malicious client can't push add-on / merchandise
                // tickets through this endpoint.
                if (!($ticketType->is_entry_ticket ?? true)) {
                    throw new \Exception("Ticket type {$ticketType->name} cannot be sold from the POS");
                }

                $quantity = (int) $ticketRequest['quantity'];

                if ($ticketType->available_quantity < $quantity) {
                    throw new \Exception("Not enough tickets for {$ticketType->name}");
                }

                $unitPrice = $ticketType->display_price ?? (($ticketType->price_cents ?? 0) / 100);
                $itemTotal = $unitPrice * $quantity;
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'ticket_type' => $ticketType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $itemTotal,
                ];

                $ticketType->increment('quota_sold', $quantity);
            }

            $total = $subtotal;
            $commissionAmount = round($subtotal * ($commission / 100), 2);

            $meta = [
                'marketplace_client' => $client->name,
                'ip_address' => $request->ip(),
                'sold_by' => $soldByLabel,
                'venue_owner_user_id' => $user->id,
                'venue_owner_tenant_id' => $tenant->id,
            ];

            $orderId = DB::table('orders')->insertGetId([
                'tenant_id' => $organizerTenantId,
                'event_id' => $event->id,
                'customer_id' => $customer->id,
                'order_number' => 'VEN-' . strtoupper(Str::random(8)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'commission_rate' => $commission,
                'commission_amount' => $commissionAmount,
                'total' => $total,
                'currency' => 'RON',
                'source' => 'venue_owner_pos',
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $event->marketplace_organizer_id,
                'customer_email' => $customer->email,
                'customer_name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                'customer_phone' => $customer->phone,
                'expires_at' => now()->addMinutes(15),
                'meta' => json_encode($meta),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $order = Order::find($orderId);

            foreach ($orderItems as $item) {
                $orderItem = $order->items()->create([
                    'ticket_type_id' => $item['ticket_type']->id,
                    'name' => $item['ticket_type']->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                ]);

                for ($i = 0; $i < $item['quantity']; $i++) {
                    Ticket::create([
                        'tenant_id' => $organizerTenantId,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'event_id' => $event->id,
                        'ticket_type_id' => $item['ticket_type']->id,
                        'customer_id' => $customer->id,
                        'code' => strtoupper(Str::random(12)),
                        'barcode' => Str::uuid()->toString(),
                        'status' => 'pending',
                        'price' => $item['unit_price'],
                    ]);
                }
            }

            // Auto-confirm cash sales — same rule the organizer endpoint
            // applies for source='pos_app'. Raw DB updates to bypass
            // OrderObserver (matches MarketplaceOrdersController::create).
            if ($payment === 'cash') {
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('tickets')->where('order_id', $order->id)->update([
                    'status' => 'valid',
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Venue owner POS order create failed', [
                'tenant_id' => $tenant->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Nu s-a putut crea comanda: ' . $e->getMessage(), 400);
        }

        // Mirror the organizer response shape so SalesScreen can read
        // response.data.order.id / .payment_url / etc. uniformly.
        $order = $order->fresh();
        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => (float) $order->subtotal,
                'commission_amount' => (float) $order->commission_amount,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'source' => $order->source,
                'tickets_count' => $order->tickets()->count(),
            ],
        ], 'Order created');
    }

    public function generateClaimUrl(Request $request, int $order): JsonResponse
    {
        if ($err = $this->scopeOrder($request, $order)) {
            return $err;
        }
        return app(MarketplaceOrdersController::class)->generateClaimUrl($request, $order);
    }

    public function posComplete(Request $request, int $order): JsonResponse
    {
        if ($err = $this->scopeOrder($request, $order)) {
            return $err;
        }
        // Default checked_in_by to the venue tag if the client didn't pass one
        // (the organizer SalesScreen forwards user?.name which for a venue
        // owner is the User's name — we want the venue label instead).
        $tenant = $request->attributes->get('venue_owner_tenant');
        $tenantName = $tenant?->public_name ?? $tenant?->company_name ?? $tenant?->name ?? 'Venue';
        if (!$request->input('checked_in_by')) {
            $request->merge(['checked_in_by' => 'Venue: ' . $tenantName]);
        }
        return app(MarketplaceOrdersController::class)->posComplete($request, $order);
    }

    public function sendTickets(Request $request, int $order): JsonResponse
    {
        if ($err = $this->scopeOrder($request, $order)) {
            return $err;
        }
        return app(MarketplaceOrdersController::class)->sendTickets($request, $order);
    }

    public function salesBreakdown(Request $request, int $event): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');
        $eventModel = $request->attributes->get('venue_owner_event');
        if (!$tenant instanceof Tenant || !$eventModel instanceof Event) {
            return $this->error('Venue owner context not resolved', 500);
        }
        return app(MarketplaceOrdersController::class)->salesBreakdown($request, $event);
    }

    /**
     * Make sure the order belongs to an event at this venue. Returns a
     * JsonResponse with an error or null when scope is valid.
     */
    protected function scopeOrder(Request $request, int $orderId): ?JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');
        if (!$tenant instanceof Tenant) {
            return $this->error('Venue owner context not resolved', 500);
        }

        $order = Order::where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        $event = Event::with('venue')->find($order->event_id);
        if (!$event) {
            return $this->error('Event not found', 404);
        }
        return $this->ensureEventAtVenue($event, $client->id, (int) $tenant->id);
    }

    /**
     * Verify the event's venue is owned by this tenant AND partnered with
     * the marketplace. Returns null when ok, JsonResponse with error on fail.
     */
    protected function ensureEventAtVenue(Event $event, int $marketplaceClientId, int $tenantId): ?JsonResponse
    {
        if ((int) $event->marketplace_client_id !== $marketplaceClientId) {
            return $this->error('Event does not belong to this marketplace', 403);
        }
        $venue = $event->venue;
        if (!$venue || (int) $venue->tenant_id !== $tenantId) {
            return $this->error('Event is not hosted at your venue', 403);
        }
        $venueIsPartner = Venue::where('id', $venue->id)
            ->partnerOfMarketplace($marketplaceClientId)
            ->exists();
        if (!$venueIsPartner) {
            return $this->error('This venue is not partnered with this marketplace', 403);
        }
        return null;
    }
}
