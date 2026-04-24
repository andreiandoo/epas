<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\VenueOwnerNote;

trait FormatsVenueOwnerTicket
{
    /**
     * Attach all notes visible for a ticket's context (ticket + order + customer)
     * scoped to the given tenant. Returns an array shaped for the API response.
     */
    protected function notesForTicket(int $tenantId, Ticket $ticket): array
    {
        return VenueOwnerNote::forTicketContext($tenantId, $ticket)
            ->map(function (VenueOwnerNote $n) {
                $a = $n->author;
                return [
                    'id' => (string) $n->id,
                    'target_type' => $n->target_type,
                    'target_id' => (string) $n->target_id,
                    'note' => $n->note,
                    'created_at' => $n->created_at?->toIso8601String(),
                    'updated_at' => $n->updated_at?->toIso8601String(),
                    'author' => $a ? [
                        'id' => (string) $a->id,
                        'name' => $a->name,
                    ] : null,
                ];
            })->values()->toArray();
    }


    /**
     * Shape a ticket for venue-owner consumption. Never includes email/phone.
     */
    protected function formatTicket(Ticket $ticket, ?Event $event = null, bool $includeEvent = false): array
    {
        $order = $ticket->order;
        $customer = $this->resolveTicketCustomer($ticket);
        $seat = $ticket->getSeatDetails();

        $data = [
            'id' => (string) $ticket->id,
            'code' => $ticket->code,
            'barcode' => $ticket->barcode,
            'status' => $ticket->status,
            'is_cancelled' => (bool) $ticket->is_cancelled,
            'cancelled_at' => $ticket->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $ticket->cancellation_reason,
            'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            'checked_in_by' => $ticket->checked_in_by,
            'price' => $ticket->price !== null ? (float) $ticket->price : null,
            'attendee_name' => $ticket->attendee_name,
            'ticket_type' => $ticket->ticketType ? [
                'id' => (string) $ticket->ticketType->id,
                'name' => $ticket->ticketType->name,
            ] : null,
            'seat' => $seat ?: null,
            'order' => $order ? [
                'id' => (string) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total !== null ? (float) $order->total : null,
                'currency' => $order->currency,
                'placed_at' => ($order->paid_at ?? $order->created_at)?->toIso8601String(),
            ] : null,
            'customer' => $customer,
        ];

        if ($includeEvent && $event) {
            $data['event'] = [
                'id' => (string) $event->id,
                'title' => $event->getTranslation('title'),
                'slug' => $event->slug,
                'venue' => $event->venue ? [
                    'id' => (string) $event->venue->id,
                    'name' => $event->venue->getTranslation('name'),
                    'city' => $event->venue->city ?? null,
                ] : null,
            ];
        }

        return $data;
    }

    /**
     * Extract customer identity from ticket → order → (marketplace|core) customer.
     * Includes phone (venue owners may contact guests) but never email — email is
     * considered private to the marketplace relationship.
     *
     * `id` + `type` identify the customer for note-grouping ("adaugă mențiune pe
     * toate biletele clientului"): mobile sends them back as target_id on a
     * customer-level note.
     */
    protected function resolveTicketCustomer(Ticket $ticket): ?array
    {
        $order = $ticket->order;
        if (!$order) {
            return null;
        }

        if ($order->relationLoaded('marketplaceCustomer') && $order->marketplaceCustomer) {
            $c = $order->marketplaceCustomer;
            return [
                'id' => (string) $c->id,
                'type' => 'marketplace_customer',
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'phone' => $c->phone ?? $order->customer_phone,
                'full_name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: null,
            ];
        }

        if ($order->relationLoaded('customer') && $order->customer) {
            $c = $order->customer;
            return [
                'id' => (string) $c->id,
                'type' => 'customer',
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'phone' => $c->phone ?? $order->customer_phone,
                'full_name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: null,
            ];
        }

        $name = trim((string) $order->customer_name);
        if ($name === '' && empty($order->customer_phone)) {
            return null;
        }

        $parts = preg_split('/\s+/', $name, 2);
        return [
            'id' => null,
            'type' => null,
            'first_name' => $parts[0] ?? null,
            'last_name' => $parts[1] ?? null,
            'phone' => $order->customer_phone,
            'full_name' => $name ?: null,
        ];
    }
}
