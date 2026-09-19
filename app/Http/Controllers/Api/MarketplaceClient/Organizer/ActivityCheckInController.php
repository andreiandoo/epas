<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\ActivityBooking;
use App\Models\MarketplaceOrganizer;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Activity booking check-in API for organizers.
 *
 * Two scan modes, both handled by the same endpoint based on what the
 * code resolves to:
 *
 *   1. confirmation_code (10 char alphanumeric on activity_bookings) →
 *      check in the WHOLE booking + every ticket on it. The QR most
 *      operators use is the booking confirmation, not per-ticket.
 *
 *   2. ticket.code or ticket.barcode (one row in `tickets`) → check in
 *      just that one participant. If the scan completes the booking
 *      (no remaining un-checked tickets), the parent booking moves to
 *      status=checked_in too.
 *
 * Mirrors EventsController::checkIn semantics for response shape so the
 * existing gate-scanner UI can talk to either flavour with one client.
 */
class ActivityCheckInController extends BaseController
{
    private const VALID_BOOKING_STATUSES_FOR_CHECKIN = [
        ActivityBooking::STATUS_PAID,
        ActivityBooking::STATUS_CONFIRMED,
    ];

    /**
     * A scanned ticket becomes 'used', like event tickets, so sales figures
     * that count valid + used keep counting it. 'checked_in' is what earlier
     * scans wrote; undo still recognises it.
     */
    private const CHECKED_TICKET_STATUSES = ['used', 'checked_in'];

    /**
     * POST /organizer/activity-bookings/check-in/{code}
     */
    public function checkIn(Request $request, string $code): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $normalized = $this->normalizeCode($code);

        if ($normalized === '') {
            return $this->error('Cod gol', 400);
        }

        // ?force=1: staff admits a ticket outside its date on purpose.
        $force = $request->boolean('force');

        // Try as booking confirmation_code FIRST — that's the natural QR
        // a customer presents. If it matches we check in everything.
        $booking = $this->resolveBookingByCode($normalized, $organizer);
        if ($booking) {
            return $this->processBookingCheckIn($booking, $organizer, $force);
        }

        // Fall back: per-ticket code/barcode.
        $ticket = $this->resolveTicketByCode($normalized, $organizer);
        if ($ticket) {
            return $this->processTicketCheckIn($ticket, $organizer, $force);
        }

        return $this->error('Cod invalid sau rezervare necunoscută', 404);
    }

    /**
     * DELETE /organizer/activity-bookings/check-in/{code}
     */
    public function undoCheckIn(Request $request, string $code): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $normalized = $this->normalizeCode($code);

        $booking = $this->resolveBookingByCode($normalized, $organizer);
        if ($booking) {
            if ($booking->status !== ActivityBooking::STATUS_CHECKED_IN) {
                return $this->error('Rezervarea nu este validată', 400);
            }
            DB::transaction(function () use ($booking) {
                // A package booking undoes its components too.
                $ids = array_merge([$booking->id], $booking->componentBookings()->pluck('id')->all());
                ActivityBooking::whereIn('id', $ids)
                    ->where('status', ActivityBooking::STATUS_CHECKED_IN)
                    ->update([
                        'status'        => ActivityBooking::STATUS_PAID,
                        'checked_in_at' => null,
                    ]);
                Ticket::whereIn('activity_booking_id', $ids)
                    ->whereIn('status', self::CHECKED_TICKET_STATUSES)
                    ->update([
                        'status'        => 'valid',
                        'checked_in_at' => null,
                        'checked_in_by' => null,
                    ]);
            });
            return $this->success(null, 'Validare anulată');
        }

        $ticket = $this->resolveTicketByCode($normalized, $organizer);
        if ($ticket) {
            if (! $ticket->checked_in_at) {
                return $this->error('Biletul nu este validat', 400);
            }
            DB::transaction(function () use ($ticket) {
                $ticket->update([
                    'status'        => 'valid',
                    'checked_in_at' => null,
                    'checked_in_by' => null,
                ]);
                // Sync the booking back to paid if it was previously
                // marked checked_in based on this ticket being the
                // last one to flip.
                if ($ticket->activity_booking_id) {
                    $parent = ActivityBooking::find($ticket->activity_booking_id);
                    if ($parent && $parent->status === ActivityBooking::STATUS_CHECKED_IN) {
                        $parent->update([
                            'status'        => ActivityBooking::STATUS_PAID,
                            'checked_in_at' => null,
                        ]);
                    }
                }
            });
            return $this->success(null, 'Validare anulată');
        }

        return $this->error('Cod invalid', 404);
    }

    /**
     * GET /organizer/activity-bookings/lookup/{code}
     *
     * Non-mutating: returns the booking + tickets + customer info so the
     * gate-scanner UI can show a "Confirm check-in" preview before
     * committing (Manual mode in the existing scanner).
     */
    public function lookup(Request $request, string $code): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $normalized = $this->normalizeCode($code);

        if ($normalized === '') {
            return $this->error('Cod gol', 400);
        }

        $booking = $this->resolveBookingByCode($normalized, $organizer);
        if ($booking) {
            return $this->success($this->buildBookingPayload($booking), 'Booking found');
        }

        $ticket = $this->resolveTicketByCode($normalized, $organizer);
        if ($ticket) {
            return $this->success($this->buildTicketPayload($ticket), 'Ticket found');
        }

        return $this->error('Cod invalid', 404);
    }

    // ============================================================
    // INTERNALS
    // ============================================================

    protected function processBookingCheckIn(ActivityBooking $booking, MarketplaceOrganizer $organizer, bool $force = false): JsonResponse
    {
        if ($booking->status === ActivityBooking::STATUS_CANCELLED) {
            return $this->error('Rezervarea a fost anulată', 400);
        }
        if ($booking->status === ActivityBooking::STATUS_NO_SHOW) {
            return $this->error('Rezervarea a fost marcată ca no-show', 400);
        }
        if (! in_array($booking->status, [
            ActivityBooking::STATUS_PAID,
            ActivityBooking::STATUS_CONFIRMED,
            ActivityBooking::STATUS_CHECKED_IN,
        ], true)) {
            return $this->error('Rezervarea nu este într-un status valid pentru check-in (status: ' . $booking->status . ')', 400);
        }

        if ($booking->status === ActivityBooking::STATUS_CHECKED_IN) {
            $payload = $this->buildBookingPayload($booking);
            return response()->json(array_merge([
                'success' => false,
                'message' => 'Rezervare deja validată la ' . optional($booking->checked_in_at)->format('Y-m-d H:i:s'),
            ], $payload), 400);
        }

        if (!$force && ($problem = $this->validityProblem($booking))) {
            return response()->json(array_merge([
                'success'    => false,
                'message'    => $problem,
                'wrong_date' => true,
            ], $this->buildBookingPayload($booking)), 400);
        }

        $checkedInBy = $this->checkInByLabel($organizer);

        DB::transaction(function () use ($booking, $checkedInBy) {
            // A package booking checks in its components (and their tickets) too.
            $ids = array_merge([$booking->id], $booking->componentBookings()
                ->whereIn('status', [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED])
                ->pluck('id')->all());
            ActivityBooking::whereIn('id', $ids)->update([
                'status'        => ActivityBooking::STATUS_CHECKED_IN,
                'checked_in_at' => now(),
            ]);
            Ticket::whereIn('activity_booking_id', $ids)
                ->whereIn('status', ['valid', 'pending'])
                ->update([
                    'status'        => 'used',
                    'checked_in_at' => now(),
                    'checked_in_by' => $checkedInBy,
                ]);
        });

        $booking->refresh();
        Log::channel('marketplace')->info('Activity booking checked in', [
            'organizer_id' => $organizer->id,
            'booking_id'   => $booking->id,
            'activity_id'  => $booking->activity_id,
        ]);

        return $this->success($this->buildBookingPayload($booking), 'Rezervare validată');
    }

    protected function processTicketCheckIn(Ticket $ticket, MarketplaceOrganizer $organizer, bool $force = false): JsonResponse
    {
        if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return $this->error('Biletul a fost ' . $ticket->status, 400);
        }

        if ($ticket->checked_in_at) {
            $payload = $this->buildTicketPayload($ticket);
            return response()->json(array_merge([
                'success' => false,
                'message' => 'Bilet deja validat la ' . $ticket->checked_in_at->format('Y-m-d H:i:s'),
            ], $payload), 400);
        }

        // The parent booking must be in a status that lets us check in.
        $booking = ActivityBooking::find($ticket->activity_booking_id);
        if (! $booking) {
            return $this->error('Bilet fără rezervare asociată', 400);
        }
        if (! in_array($booking->status, [
            ActivityBooking::STATUS_PAID,
            ActivityBooking::STATUS_CONFIRMED,
            ActivityBooking::STATUS_CHECKED_IN,
        ], true)) {
            return $this->error('Rezervarea nu este într-un status valid pentru check-in (' . $booking->status . ')', 400);
        }

        if (!$force && ($problem = $this->validityProblem($booking))) {
            return response()->json(array_merge([
                'success'    => false,
                'message'    => $problem,
                'wrong_date' => true,
            ], $this->buildTicketPayload($ticket)), 400);
        }

        $checkedInBy = $this->checkInByLabel($organizer);

        DB::transaction(function () use ($ticket, $booking, $checkedInBy) {
            $ticket->update([
                'status'        => 'used',
                'checked_in_at' => now(),
                'checked_in_by' => $checkedInBy,
            ]);

            // If this was the last un-checked ticket on the booking, promote
            // the booking to checked_in too. Cheap count + compare.
            $remainingUnchecked = Ticket::where('activity_booking_id', $booking->id)
                ->whereIn('status', ['valid', 'pending'])
                ->count();

            if ($remainingUnchecked === 0 && $booking->status !== ActivityBooking::STATUS_CHECKED_IN) {
                $booking->update([
                    'status'        => ActivityBooking::STATUS_CHECKED_IN,
                    'checked_in_at' => now(),
                ]);
            }
        });

        $ticket->refresh();
        return $this->success($this->buildTicketPayload($ticket), 'Bilet validat');
    }

    /**
     * Resolve a booking by confirmation_code, scoped to:
     *   - the organizer's marketplace_client_id
     *   - an activity owned by this organizer
     */
    protected function resolveBookingByCode(string $normalized, MarketplaceOrganizer $organizer): ?ActivityBooking
    {
        return ActivityBooking::with(['activity', 'customer', 'order', 'tickets'])
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->whereRaw('UPPER(confirmation_code) = ?', [strtoupper($normalized)])
            ->whereHas('activity', fn ($q) => $q->where('marketplace_organizer_id', $organizer->id))
            ->first();
    }

    /**
     * Resolve an activity ticket by `code` or `barcode`, scoped to
     * activities owned by this organizer.
     */
    protected function resolveTicketByCode(string $normalized, MarketplaceOrganizer $organizer): ?Ticket
    {
        return Ticket::with(['order.marketplaceCustomer'])
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->whereNotNull('activity_booking_id')
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(barcode) = ?', [strtolower($normalized)])
                    ->orWhereRaw('LOWER(code) = ?', [strtolower($normalized)]);
            })
            ->whereHas('activityBooking', fn ($q) => $q
                ->whereHas('activity', fn ($q2) => $q2->where('marketplace_organizer_id', $organizer->id)))
            ->first();
    }

    protected function buildBookingPayload(ActivityBooking $booking): array
    {
        $activity = $booking->activity;
        $title = $activity && is_array($activity->title)
            ? ($activity->title['ro'] ?? $activity->title['en'] ?? '—')
            : ($activity->title ?? '—');

        $customerName = null;
        $customerEmail = null;
        if ($booking->customer) {
            $customerName = trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? ''));
            $customerEmail = $booking->customer->email;
        } elseif ($booking->order) {
            $customerName = $booking->order->customer_name;
            $customerEmail = $booking->order->customer_email;
        }

        return [
            'type' => 'activity_booking',
            'booking' => [
                'id'                 => $booking->id,
                'confirmation_code'  => $booking->confirmation_code,
                'status'             => $booking->status,
                'booking_date'       => $booking->booking_date?->toDateString(),
                'slot_start_time'    => is_string($booking->slot_start_time)
                    ? substr($booking->slot_start_time, 0, 5)
                    : $booking->slot_start_time?->format('H:i'),
                'slot_end_time'      => is_string($booking->slot_end_time)
                    ? substr($booking->slot_end_time, 0, 5)
                    : $booking->slot_end_time?->format('H:i'),
                'participants_count' => $booking->participants_count,
                'checked_in_at'      => $booking->checked_in_at?->toIso8601String(),
                'total_cents'        => $booking->total_cents,
                'currency'           => $booking->currency,
                'end_date'           => $booking->end_date?->toDateString(),
                'quantity'           => $booking->quantity,
                'variant'            => $this->plain($booking->variant?->name),
                'vehicle_plate'      => $booking->meta['vehicle_plate'] ?? null,
                'addons'             => $booking->addons ?? [],
                'components'         => $booking->componentBookings()->with(['activity', 'variant'])->get()->map(fn ($c) => [
                    'title'    => $this->plain($c->activity?->title),
                    'variant'  => $this->plain($c->variant?->name),
                    'quantity' => $c->quantity,
                    'slot_start_time' => $c->getRawOriginal('slot_start_time') ? substr($c->getRawOriginal('slot_start_time'), 0, 5) : null,
                ])->values()->all(),
            ],
            'activity' => [
                'id'    => $activity?->id,
                'title' => $title,
                'type'  => $activity?->product_type,
            ],
            'customer' => [
                'name'  => $customerName,
                'email' => $customerEmail,
            ],
            'tickets_summary' => [
                'total'      => Ticket::where('activity_booking_id', $booking->id)->count(),
                'checked_in' => Ticket::where('activity_booking_id', $booking->id)
                    ->whereNotNull('checked_in_at')
                    ->count(),
            ],
        ];
    }

    /**
     * Null when the ticket is valid today (Bucharest time), otherwise the
     * reason for the gate: a ticket is valid on its date, or from its date to
     * its end date for multi-day tickets.
     */
    protected function validityProblem(ActivityBooking $booking): ?string
    {
        $from = $booking->booking_date?->toDateString();
        if (!$from) {
            return null;
        }
        $to    = ($booking->end_date ?? $booking->booking_date)->toDateString();
        $today = now('Europe/Bucharest')->toDateString();
        $fmt   = fn (string $d) => \Carbon\Carbon::parse($d)->format('d.m.Y');

        if ($today < $from) {
            return $from === $to
                ? 'Biletul e pentru ' . $fmt($from) . ', nu pentru azi.'
                : 'Biletul e valabil între ' . $fmt($from) . ' și ' . $fmt($to) . '.';
        }
        if ($today > $to) {
            return 'Biletul a fost valabil ' . ($from === $to ? 'pe ' . $fmt($to) : 'până pe ' . $fmt($to)) . '.';
        }
        return null;
    }

    /** Romanian text of a translatable value. */
    protected function plain($value): ?string
    {
        if (is_array($value)) {
            $value = $value['ro'] ?? $value['en'] ?? (array_values(array_filter($value))[0] ?? null);
        }
        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function buildTicketPayload(Ticket $ticket): array
    {
        $booking = $ticket->activity_booking_id
            ? ActivityBooking::with('activity')->find($ticket->activity_booking_id)
            : null;
        $activity = $booking?->activity;
        $title = $activity && is_array($activity->title)
            ? ($activity->title['ro'] ?? $activity->title['en'] ?? '—')
            : ($activity?->title ?? '—');

        $customerName = null;
        $customerEmail = null;
        if ($ticket->order && $ticket->order->marketplaceCustomer) {
            $c = $ticket->order->marketplaceCustomer;
            $customerName = trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
            $customerEmail = $c->email;
        } elseif ($ticket->order) {
            $customerName = $ticket->order->customer_name;
            $customerEmail = $ticket->order->customer_email;
        }

        return [
            'type' => 'activity_ticket',
            'ticket' => [
                'id'             => $ticket->id,
                'code'           => $ticket->code,
                'barcode'        => $ticket->barcode,
                'status'         => $ticket->status,
                'attendee_name'  => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'checked_in_at'  => $ticket->checked_in_at?->toIso8601String(),
                'price'          => $ticket->price,
                'companion'      => (bool) ($ticket->meta['companion'] ?? false),
                'package'        => $ticket->meta['package'] ?? null,
            ],
            'booking' => $booking ? [
                'id'                => $booking->id,
                'confirmation_code' => $booking->confirmation_code,
                'status'            => $booking->status,
                'booking_date'      => $booking->booking_date?->toDateString(),
                'slot_start_time'   => is_string($booking->slot_start_time)
                    ? substr($booking->slot_start_time, 0, 5)
                    : $booking->slot_start_time?->format('H:i'),
                'participants_count' => $booking->participants_count,
                'end_date'          => $booking->end_date?->toDateString(),
                'variant'           => $this->plain($booking->variant?->name),
                'vehicle_plate'     => $booking->meta['vehicle_plate'] ?? null,
            ] : null,
            'activity' => [
                'id'    => $activity?->id,
                'title' => $title,
            ],
            'customer' => [
                'name'  => $customerName,
                'email' => $customerEmail,
            ],
        ];
    }

    /**
     * Strip whitespace + unwrap any /t/{code} URL wrapper, same shape as
     * EventsController::normalizeTicketCode so a single QR encoder can
     * point at either system.
     */
    protected function normalizeCode(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('#/t/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        if (preg_match('#/verify/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        if (preg_match('#/r/([A-Za-z0-9_-]+)#', $trimmed, $m)) {
            return $m[1];
        }
        return $trimmed;
    }

    protected function checkInByLabel(MarketplaceOrganizer $organizer): string
    {
        $name = $organizer->contact_name ?: $organizer->name ?: ('Org #' . $organizer->id);
        return 'Activity/Organizer: ' . $name;
    }

    /**
     * The organizer behind the Sanctum token. Every organizer controller
     * carries its own copy (BaseController has none); without this one each
     * lookup / check-in / undo call died with "undefined method" (500).
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}
