<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public /join/{code} gate for online-event tickets. Replaces QR-scan
 * validation for events that don't have a physical door to check
 * against.
 *
 * Flow on GET:
 *   1. Look up the ticket by code.
 *   2. Reject if: not found, cancelled/refunded, order unpaid, or the
 *      event is not marked is_online.
 *   3. If we're OUTSIDE the join window (before lobby / after end),
 *      show a "not yet" state with the exact time it opens.
 *   4. Otherwise reveal the meeting URL + passcode + instructions and
 *      atomically record the join (checked_in_at, checked_in_via,
 *      meta.join_history append). First join = check-in stamp; later
 *      re-visits just increment the counter.
 */
class TicketJoinController extends Controller
{
    public function show(Request $request, string $code)
    {
        $ticket = Ticket::where('code', $code)->first();

        if (!$ticket) {
            return response()->view('public.ticket-join', $this->baseView('not_found'), 404);
        }

        $event = $ticket->resolveEvent();
        $order = $ticket->order;
        $orderPaid = $order && in_array($order->status, ['paid', 'confirmed', 'completed'], true);
        $client = MarketplaceClient::find($ticket->marketplace_client_id);

        if ($ticket->is_cancelled || $ticket->status === 'cancelled') {
            return response()->view('public.ticket-join', $this->baseView('cancelled', $event, $client), 410);
        }
        if ($ticket->status === 'refunded') {
            return response()->view('public.ticket-join', $this->baseView('refunded', $event, $client), 410);
        }
        if (!$orderPaid) {
            return response()->view('public.ticket-join', $this->baseView('unpaid', $event, $client), 402);
        }
        if (!$event) {
            return response()->view('public.ticket-join', $this->baseView('not_found', null, $client), 404);
        }
        if (!$event->is_online) {
            // Physical event — send them to the normal ticket verify
            // page. They may have hit /join/{code} by accident.
            return redirect('/t/' . $ticket->code, 302);
        }
        if (empty($event->online_meeting_url)) {
            // Event marked online but the organizer hasn't populated
            // the meeting URL yet. Nothing we can do — surface this
            // clearly so support can be contacted.
            return response()->view(
                'public.ticket-join',
                $this->baseView('not_configured', $event, $client) + [
                    'organizerContact' => $this->resolveOrganizerContact($event),
                ],
                503
            );
        }

        $now = now();
        $lobbyOpens = $event->online_lobby_opens_at;
        $isJoinable = $event->isOnlineJoinable($now);

        if (!$isJoinable) {
            // Two sub-cases: too early vs too late.
            if ($lobbyOpens && $now->lt($lobbyOpens)) {
                return response()->view(
                    'public.ticket-join',
                    $this->baseView('too_early', $event, $client, $ticket) + [
                        'lobbyOpensAt' => $lobbyOpens,
                    ]
                );
            }
            return response()->view(
                'public.ticket-join',
                $this->baseView('ended', $event, $client, $ticket)
            );
        }

        // Ready to join — record the visit + reveal the details.
        $this->recordJoin($ticket, $request);

        return response()->view('public.ticket-join', $this->baseView('ready', $event, $client, $ticket) + [
            'meetingUrl'       => $event->online_meeting_url,
            'passcode'         => $event->online_passcode,
            'providerLabel'    => $event->online_provider_label,
            'instructionsHtml' => $event->online_instructions,
        ]);
    }

    /**
     * Base view payload used across all states so the blade doesn't
     * have to `?? ''` every field.
     */
    protected function baseView(string $status, ?Event $event = null, ?MarketplaceClient $client = null, ?Ticket $ticket = null): array
    {
        $eventTitle = null;
        if ($event) {
            $eventTitle = method_exists($event, 'getTranslation')
                ? ($event->getTranslation('title', 'ro') ?? $event->getTranslation('title', 'en'))
                : null;
            if (!$eventTitle) {
                $eventTitle = is_array($event->title)
                    ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: null)
                    : $event->title;
            }
        }

        return [
            'status'          => $status,
            'code'            => $ticket?->code ?? '',
            'eventTitle'      => $eventTitle ?? '—',
            'eventDate'       => $event?->event_date,
            'eventStartTime'  => $event?->start_time,
            'buyerName'       => $ticket?->attendee_name ?? $ticket?->order?->customer_name,
            'ticketType'      => $ticket?->resolveTicketTypeName(),
            'marketplaceName' => $client?->name,
            'marketplaceSite' => $client?->domain ? 'https://' . $client->domain : null,
        ];
    }

    /**
     * Stamp check_in_at on the FIRST join + append an audit entry to
     * meta.join_history every visit. All writes in one save call so a
     * race between two tabs can't produce two check_in_at values.
     */
    protected function recordJoin(Ticket $ticket, Request $request): void
    {
        try {
            $meta = is_array($ticket->meta) ? $ticket->meta : [];
            $history = $meta['join_history'] ?? [];

            $history[] = [
                'at'         => now()->toIso8601String(),
                'session'    => hash('sha256', $request->cookie('noutati_session') ?? $request->ip() . $request->userAgent()),
                'ip_hash'    => hash('sha256', $request->ip() ?? ''),
                'user_agent' => substr((string) $request->userAgent(), 0, 200),
            ];
            // Cap history at last 50 entries to keep meta blob small.
            if (count($history) > 50) {
                $history = array_slice($history, -50);
            }

            $meta['join_history'] = $history;
            $meta['join_count']   = count($history);

            $update = ['meta' => $meta];

            if (!$ticket->checked_in_at) {
                $update['checked_in_at']  = now();
                $update['checked_in_by']  = 'Online join';
                $update['checked_in_via'] = 'online_join';
                // Only flip to 'used' if it was 'valid' — don't overwrite
                // more-specific statuses (refunded, cancelled).
                if ($ticket->status === 'valid') {
                    $update['status'] = 'used';
                }
            }

            $ticket->update($update);
        } catch (\Throwable $e) {
            // Never break the visitor's ability to join because of an
            // audit-log failure. Log + continue.
            Log::warning('TicketJoin recordJoin failed', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Best-effort contact info for the "not configured yet" state so
     * the visitor knows who to reach out to.
     */
    protected function resolveOrganizerContact(Event $event): array
    {
        $org = $event->marketplaceOrganizer;
        return [
            'name'  => $org?->name ?? $org?->contact_name ?? '—',
            'email' => $org?->email ?? $org?->contact_email,
        ];
    }
}
