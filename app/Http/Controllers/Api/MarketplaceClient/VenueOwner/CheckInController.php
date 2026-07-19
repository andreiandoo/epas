<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Http\Controllers\Api\MarketplaceClient\VenueOwner\Concerns\FormatsVenueOwnerTicket;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Venue Owner check-in / undo. Same shape as the organizer's check-in endpoints
 * but scoped to tickets whose event is hosted at one of the venue owner's
 * venues, and partnered with the current marketplace.
 *
 * `checked_in_by` is stored as "Venue: {tenant.name}" so the organizer's
 * dashboards can distinguish venue-owner scans from their own.
 */
class CheckInController extends BaseController
{
    use FormatsVenueOwnerTicket;

    /**
     * Check in a ticket by scanned code. Looks across all events at venues
     * owned by this tenant and partnered with the marketplace.
     */
    public function checkInByCode(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');
        $user = $request->user();

        if (!$tenant instanceof Tenant || !$user instanceof User) {
            return $this->error('Venue owner context not resolved', 500);
        }

        $validated = $request->validate([
            'ticket_code' => 'required|string',
        ]);

        $code = $this->normalizeTicketCode($validated['ticket_code']);

        $allowedEventIds = $this->allowedEventIds($client->id, (int) $tenant->id);
        if (empty($allowedEventIds)) {
            return $this->error('No events at your venue for this marketplace', 403);
        }

        $ticket = Ticket::with('ticketType', 'order.marketplaceCustomer')
            ->where(function ($q) use ($code) {
                $q->whereRaw('LOWER(barcode) = ?', [strtolower($code)])
                  ->orWhereRaw('LOWER(code) = ?', [strtolower($code)]);
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
        }

        $isInvitation = is_array($ticket->meta) && !empty($ticket->meta['is_invitation']);

        $resolvedEventId = $ticket->event_id ?? $ticket->ticketType?->event_id;
        if (!$resolvedEventId || !in_array((int) $resolvedEventId, $allowedEventIds, true)) {
            return $this->error('Ticket is not for an event at your venue', 403);
        }

        if (!$isInvitation) {
            $validOrderStatuses = ['paid', 'confirmed', 'completed'];
            if (!$ticket->order || !in_array($ticket->order->status, $validOrderStatuses, true)) {
                return $this->error('Ticket order is not in a valid status', 400);
            }
        }

        if ($ticket->status === 'pending_installments') {
            return $this->error('This ticket is not yet fully paid (installment plan in progress).', 400);
        }

        if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        $scannerLabel = $this->scannerLabel($tenant, $user);

        if ($ticket->checked_in_at) {
            $payload = $this->buildScanPayload($ticket, $isInvitation);
            return response()->json(array_merge([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
            ], $payload), 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $scannerLabel,
            'checked_in_via' => 'venue_app',
        ]);

        return $this->success($this->buildScanPayload($ticket, $isInvitation), 'Ticket checked in successfully');
    }

    /**
     * Per-event check-in. Mirrors OrganizerEventsController::checkIn so the
     * mobile CheckInScreen can call the identical URL shape via the path
     * rewriter. Event scope is validated by EnsureVenueOwner middleware
     * (which sets venue_owner_event on the request).
     */
    public function checkInPerEvent(Request $request, int $event, string $barcode): JsonResponse
    {
        $venueEvent = $request->attributes->get('venue_owner_event');
        if (!$venueEvent instanceof Event) {
            return $this->error('Event not accessible', 403);
        }
        $tenant = $request->attributes->get('venue_owner_tenant');
        $user = $request->user();
        if (!$tenant instanceof Tenant || !$user instanceof User) {
            return $this->error('Venue owner context not resolved', 500);
        }

        $normalized = $this->normalizeTicketCode($barcode);

        $ticket = Ticket::with('ticketType', 'order.marketplaceCustomer')
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(barcode) = ?', [strtolower($normalized)])
                  ->orWhereRaw('LOWER(code) = ?', [strtolower($normalized)]);
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
        }

        $isInvitation = is_array($ticket->meta) && !empty($ticket->meta['is_invitation']);
        $resolvedEventId = $ticket->event_id ?? $ticket->ticketType?->event_id;

        // Hard scope: ticket MUST belong to this specific event. Different from
        // the cross-event lookup endpoint.
        if ($resolvedEventId === null || (int) $resolvedEventId !== (int) $venueEvent->id) {
            return $this->error('Biletul nu este pentru acest eveniment', 400);
        }

        if (!$isInvitation) {
            $validOrderStatuses = ['paid', 'confirmed', 'completed'];
            if (!$ticket->order || !in_array($ticket->order->status, $validOrderStatuses, true)) {
                return $this->error('Ticket order is not in a valid status', 400);
            }
        }

        if ($ticket->status === 'pending_installments') {
            return $this->error('This ticket is not yet fully paid (installment plan in progress).', 400);
        }

        if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return $this->error('This ticket has been ' . $ticket->status, 400);
        }

        $scannerLabel = $this->scannerLabel($tenant, $user);

        if ($ticket->checked_in_at) {
            $payload = $this->buildScanPayload($ticket, $isInvitation);
            return response()->json(array_merge([
                'success' => false,
                'message' => 'Ticket already checked in at ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
            ], $payload), 400);
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $scannerLabel,
            'checked_in_via' => 'venue_app',
        ]);

        return $this->success($this->buildScanPayload($ticket, $isInvitation), 'Ticket checked in successfully');
    }

    /**
     * Undo per-event check-in. Mirrors OrganizerEventsController::undoCheckIn.
     */
    public function undoCheckInPerEvent(Request $request, int $event, string $barcode): JsonResponse
    {
        $venueEvent = $request->attributes->get('venue_owner_event');
        if (!$venueEvent instanceof Event) {
            return $this->error('Event not accessible', 403);
        }

        $normalized = $this->normalizeTicketCode($barcode);

        $ticket = Ticket::where(function ($q) use ($normalized) {
            $q->whereRaw('LOWER(barcode) = ?', [strtolower($normalized)])
              ->orWhereRaw('LOWER(code) = ?', [strtolower($normalized)]);
        })->first();

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
        }
        $resolvedEventId = $ticket->event_id ?? $ticket->ticketType?->event_id;
        if ($resolvedEventId === null || (int) $resolvedEventId !== (int) $venueEvent->id) {
            return $this->error('Biletul nu este pentru acest eveniment', 400);
        }
        if (!$ticket->checked_in_at) {
            return $this->error('Ticket is not checked in', 400);
        }

        $ticket->update(['checked_in_at' => null, 'checked_in_by' => null]);

        return $this->success(null, 'Check-in undone');
    }

    /**
     * Undo check-in for a ticket scoped to this venue. Used to recover from
     * a mistaken scan.
     */
    public function undoCheckIn(Request $request, string $barcode): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');

        if (!$tenant instanceof Tenant) {
            return $this->error('Venue owner context not resolved', 500);
        }

        $normalized = $this->normalizeTicketCode($barcode);
        $allowedEventIds = $this->allowedEventIds($client->id, (int) $tenant->id);

        $ticket = Ticket::where(function ($q) use ($normalized) {
            $q->whereRaw('LOWER(barcode) = ?', [strtolower($normalized)])
              ->orWhereRaw('LOWER(code) = ?', [strtolower($normalized)]);
        })->first();

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
        }

        $resolvedEventId = $ticket->event_id ?? $ticket->ticketType?->event_id;
        if (!$resolvedEventId || !in_array((int) $resolvedEventId, $allowedEventIds, true)) {
            return $this->error('Ticket is not for an event at your venue', 403);
        }

        if (!$ticket->checked_in_at) {
            return $this->error('Ticket is not checked in', 400);
        }

        $ticket->update(['checked_in_at' => null, 'checked_in_by' => null]);

        return $this->success(null, 'Check-in undone');
    }

    /**
     * Build the list of event ids hosted at venues owned by this tenant and
     * partnered with the marketplace. Returned as a plain int array for the
     * `whereIn` checks above.
     */
    protected function allowedEventIds(int $marketplaceClientId, int $tenantId): array
    {
        $venueIds = Venue::where('tenant_id', $tenantId)
            ->partnerOfMarketplace($marketplaceClientId)
            ->pluck('id');

        if ($venueIds->isEmpty()) {
            return [];
        }

        return Event::whereIn('venue_id', $venueIds)
            ->where('marketplace_client_id', $marketplaceClientId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Strip whitespace and any /t/{code} or /verify/{code} URL wrapper, just
     * in case the mobile sends the raw QR payload.
     */
    protected function normalizeTicketCode(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('#/t/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        if (preg_match('#/verify/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        return $trimmed;
    }

    /**
     * Format the venue-owner-tagged scanner label written to checked_in_by.
     * Stays a plain string column on tickets — organizer dashboards already
     * surface this value as-is.
     */
    protected function scannerLabel(Tenant $tenant, User $user): string
    {
        $tenantName = $tenant->public_name ?? $tenant->company_name ?? $tenant->name ?? 'Venue';
        $userName = $user->name ?? null;
        return $userName
            ? sprintf('Venue: %s (%s)', $tenantName, $userName)
            : 'Venue: ' . $tenantName;
    }

    /**
     * Lightweight scan payload mirroring the organizer endpoint's shape so the
     * mobile app can use one renderer for both flows.
     */
    protected function buildScanPayload(Ticket $ticket, bool $isInvitation): array
    {
        $seatDetails = method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null;
        $beneficiary = is_array($ticket->meta) ? ($ticket->meta['beneficiary'] ?? []) : [];

        $customerName = null;
        $customerEmail = null;
        if (!$isInvitation && $ticket->order) {
            $marketplaceCustomer = $ticket->order->marketplaceCustomer;
            $customerName = $marketplaceCustomer
                ? trim(($marketplaceCustomer->first_name ?? '') . ' ' . ($marketplaceCustomer->last_name ?? ''))
                : $ticket->order->customer_name;
            $customerEmail = $marketplaceCustomer?->email ?? $ticket->order->customer_email;
        } else {
            $customerName = $beneficiary['name'] ?? $ticket->attendee_name ?? null;
            $customerEmail = $beneficiary['email'] ?? null;
        }

        return [
            'ticket' => [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->ticketType?->name,
                'status' => $ticket->status,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                'checked_in_by' => $ticket->checked_in_by,
                'seat_label' => $ticket->seat_label,
                'section' => $seatDetails['section_name'] ?? null,
                'row' => $seatDetails['row_label'] ?? null,
                'seat' => $seatDetails['seat_number'] ?? null,
                'attendee_name' => $ticket->attendee_name,
                'is_invitation' => $isInvitation,
            ],
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
            ],
            'order' => $ticket->order ? [
                'source' => $ticket->order->source ?? 'online',
                'customer_name' => $ticket->order->customer_name,
            ] : [
                'source' => 'invitation',
                'customer_name' => $beneficiary['name'] ?? null,
            ],
        ];
    }
}
