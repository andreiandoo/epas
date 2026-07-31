<?php

namespace App\Http\Controllers\Api\TenantApp;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ticket validation / check-in, scoped to the tenant's own tickets
 * (Ticket.tenant_id). Mirrors the marketplace organizer check-in flow but
 * marketplace-independent: it reuses the shared Ticket model and sets
 * checked_in_via = 'tenant_app'. Gated by the `tickets.scan` permission.
 */
class CheckInController extends BaseController
{
    private const OK_ORDER_STATES = ['paid', 'confirmed', 'completed', 'free'];

    /** Scan by code — accepts raw QR URL wrappers (/t/, /v/, /verify/). */
    public function byCode(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'tickets.scan');
        $data = $request->validate([
            'ticket_code' => ['required', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer'],
        ]);

        $code = $this->normalize($data['ticket_code']);
        $tid = $this->tenantId($request);

        $query = Ticket::where('tenant_id', $tid)
            ->where(function ($q) use ($code) {
                $q->whereRaw('LOWER(barcode) = ?', [mb_strtolower($code)])
                    ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($code)]);
            });
        if (! empty($data['event_id'])) {
            $query->where('event_id', $data['event_id']);
        }
        $ticket = $query->with(['order', 'ticketType'])->first();

        if (! $ticket) {
            return $this->error('Bilet negăsit sau nu aparține acestui tenant.', 404);
        }

        return $this->performCheckIn($request, $ticket);
    }

    /** Scan scoped to a specific event + barcode. */
    public function checkIn(Request $request, int $eventId, string $barcode): JsonResponse
    {
        $this->requirePermission($request, 'tickets.scan');
        $tid = $this->tenantId($request);

        $ticket = Ticket::where('tenant_id', $tid)
            ->where('event_id', $eventId)
            ->where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)->orWhere('code', $barcode);
            })
            ->with(['order', 'ticketType'])
            ->first();

        if (! $ticket) {
            return $this->error('Bilet negăsit pentru acest eveniment.', 404);
        }

        return $this->performCheckIn($request, $ticket);
    }

    /** Undo a check-in (clears the check-in stamp). */
    public function undo(Request $request, int $eventId, string $barcode): JsonResponse
    {
        $this->requirePermission($request, 'tickets.scan');
        $tid = $this->tenantId($request);

        $ticket = Ticket::where('tenant_id', $tid)
            ->where('event_id', $eventId)
            ->where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)->orWhere('code', $barcode);
            })
            ->first();

        if (! $ticket) {
            return $this->error('Bilet negăsit.', 404);
        }

        $ticket->update([
            'checked_in_at' => null,
            'checked_in_by' => null,
            'checked_in_via' => null,
        ]);

        return $this->success(['barcode' => $ticket->barcode], 'Check-in anulat.');
    }

    private function performCheckIn(Request $request, Ticket $ticket): JsonResponse
    {
        if (in_array($ticket->status, ['cancelled', 'refunded'], true) || $ticket->is_cancelled) {
            return $this->error('Bilet anulat sau rambursat.', 400);
        }

        $order = $ticket->order;
        $isInvitation = (bool) ($ticket->is_invitation ?? false);
        if ($order && ! $isInvitation) {
            $state = $order->payment_status ?: $order->status;
            if (! in_array($order->status, self::OK_ORDER_STATES, true)
                && ! in_array($order->payment_status, self::OK_ORDER_STATES, true)) {
                return $this->error('Comanda nu este plătită/confirmată.', 400, ['order_state' => $state]);
            }
        }

        if ($ticket->checked_in_at) {
            return $this->error(
                'Bilet deja validat la ' . $ticket->checked_in_at->format('H:i, d.m.Y') . '.',
                400,
                ['ticket' => $this->payload($ticket, false)],
            );
        }

        $operator = $request->user()?->name ?? 'Tenant';
        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => $operator,
            'checked_in_via' => 'tenant_app',
        ]);

        return $this->success(['ticket' => $this->payload($ticket, true)], 'Check-in valid.');
    }

    /** Strip POS/verify URL wrappers, returning the bare code. */
    private function normalize(string $raw): string
    {
        $code = trim($raw);
        if (preg_match('#/(?:t|v|verify)/([^/?\s]+)#i', $code, $m)) {
            return $m[1];
        }
        return $code;
    }

    /** @return array<string,mixed> */
    private function payload(Ticket $ticket, bool $justEntered): array
    {
        return [
            'id' => $ticket->id,
            'barcode' => $ticket->barcode,
            'code' => $ticket->code,
            'ticket_type' => $ticket->ticketType?->name,
            'status' => $ticket->status,
            'attendee_name' => $ticket->attendee_name,
            'seat_label' => $ticket->seat_label,
            'checked_in_at' => optional($ticket->checked_in_at)->toIso8601String(),
            'checked_in_by' => $ticket->checked_in_by,
            'just_entered' => $justEntered,
        ];
    }
}
