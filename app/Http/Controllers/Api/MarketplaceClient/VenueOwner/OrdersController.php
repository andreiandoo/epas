<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
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
 * Venue Owner POS order creation. Mirrors the core flow of
 * MarketplaceOrdersController::create but is scoped strictly to events
 * hosted at the venue-owner's partner venues. Sets `source='venue_owner_pos'`
 * and `meta.sold_by='Venue: {tenant}'` so the organizer's existing sales
 * breakdown surfaces these sales as a distinct "by_user" entry.
 *
 * Supports a single-event, multi-ticket-type, non-seated, cash/invitation
 * flow. Seated and online-pay flows intentionally aren't exposed here.
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
            'customer' => 'required|array',
            'customer.email' => 'required|email',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'nullable|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
            'payment_method' => 'nullable|in:cash,card',
            'is_invitation' => 'nullable|boolean',
        ]);

        $event = Event::with('venue')->find($request->event_id);
        if (!$event || $event->status !== 'published') {
            return $this->error('Event not available', 400);
        }

        if ((int) $event->marketplace_client_id !== (int) $client->id) {
            return $this->error('Event does not belong to this marketplace', 403);
        }

        // Verify the event's venue is one of this tenant's partner venues.
        $venue = $event->venue;
        if (!$venue || (int) $venue->tenant_id !== (int) $tenant->id) {
            return $this->error('Event is not hosted at your venue', 403);
        }

        $venueIsPartner = Venue::where('id', $venue->id)
            ->partnerOfMarketplace($client->id)
            ->exists();
        if (!$venueIsPartner) {
            return $this->error('This venue is not partnered with this marketplace', 403);
        }

        // Tenant of the order is the organizer's tenant (so the sale lands in
        // the organizer's books, NOT the venue owner's). The venue owner is
        // recorded only via meta.
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

        $customerEmail = strtolower(trim($request->input('customer.email')));
        $customer = Customer::where('email', $customerEmail)
            ->where('tenant_id', $organizerTenantId)
            ->first();

        if ($customer) {
            $customer->update([
                'first_name' => $request->input('customer.first_name'),
                'last_name' => $request->input('customer.last_name') ?: $customer->last_name,
                'phone' => $request->input('customer.phone') ?: $customer->phone,
            ]);
        } else {
            $customer = Customer::create([
                'email' => $customerEmail,
                'tenant_id' => $organizerTenantId,
                'primary_tenant_id' => $organizerTenantId,
                'first_name' => $request->input('customer.first_name'),
                'last_name' => $request->input('customer.last_name'),
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
        $soldByLabel = sprintf('Venue: %s', $tenantName);

        $isInvitation = (bool) $request->input('is_invitation', false);
        $paymentMethod = $isInvitation ? null : ($request->input('payment_method', 'cash'));

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

                $quantity = (int) $ticketRequest['quantity'];

                if ($ticketType->available_quantity < $quantity) {
                    throw new \Exception("Not enough tickets for {$ticketType->name}");
                }

                $unitPrice = $isInvitation ? 0 : ($ticketType->display_price ?? (($ticketType->price_cents ?? 0) / 100));
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

            // POS sales always charge the displayed price — no commission on top.
            $total = $subtotal;
            $commissionAmount = round($subtotal * ($commission / 100), 2);

            $meta = [
                'marketplace_client' => $client->name,
                'ip_address' => $request->ip(),
                'sold_by' => $soldByLabel,
                'venue_owner_user_id' => $user->id,
                'venue_owner_tenant_id' => $tenant->id,
            ];
            if ($isInvitation) {
                $meta['is_invitation'] = true;
            }

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

            // Auto-confirm cash sales + invitations on POS. Use raw DB update
            // to bypass OrderObserver — same workaround used by the organizer
            // POS endpoint (see OrdersController::create for context).
            if ($paymentMethod === 'cash' || $isInvitation) {
                DB::table('orders')->where('id', $order->id)->update([
                    'status' => 'confirmed',
                    'payment_status' => $isInvitation ? 'free' : 'paid',
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

        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->fresh()->status,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'tickets_count' => $order->tickets()->count(),
            ],
        ], 'Comandă creată');
    }

    /**
     * List ticket types available for an event at this venue. Used by the
     * mobile POS to display the buy buttons.
     */
    public function ticketTypes(Request $request, int $eventId): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');

        if (!$tenant instanceof Tenant) {
            return $this->error('Venue owner context not resolved', 500);
        }

        $event = Event::with('venue')->find($eventId);
        if (!$event) {
            return $this->error('Event not found', 404);
        }
        if ((int) $event->marketplace_client_id !== (int) $client->id) {
            return $this->error('Event does not belong to this marketplace', 403);
        }
        if (!$event->venue || (int) $event->venue->tenant_id !== (int) $tenant->id) {
            return $this->error('Event is not at your venue', 403);
        }

        $types = TicketType::where('event_id', $event->id)
            ->whereIn('status', ['active', 'on_sale', 'published'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TicketType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'price' => (float) ($t->display_price ?? (($t->price_cents ?? 0) / 100)),
                'available' => (int) $t->available_quantity,
                'quantity' => (int) ($t->quantity ?? 0),
                'sold' => (int) ($t->quota_sold ?? 0),
                'max_per_order' => $t->max_per_order,
                'is_entry_ticket' => (bool) ($t->is_entry_ticket ?? true),
            ]);

        return $this->success(['ticket_types' => $types]);
    }
}
