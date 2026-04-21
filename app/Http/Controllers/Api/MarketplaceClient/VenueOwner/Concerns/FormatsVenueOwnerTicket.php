<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns;

use App\Models\Event;
use App\Models\Ticket;

trait FormatsVenueOwnerTicket
{
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
     * Extract customer identity (names only) from ticket → order → (marketplace|core) customer.
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
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'full_name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: null,
            ];
        }

        if ($order->relationLoaded('customer') && $order->customer) {
            $c = $order->customer;
            return [
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'full_name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: null,
            ];
        }

        $name = trim((string) $order->customer_name);
        if ($name === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $name, 2);
        return [
            'first_name' => $parts[0] ?? null,
            'last_name' => $parts[1] ?? null,
            'full_name' => $name,
        ];
    }
}
