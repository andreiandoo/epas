<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Renders the seating-map "second page" HTML block for a ticket, ready to
 * be embedded into any custom-template PDF document body.
 *
 * Used by every place that generates a ticket PDF via DomPDF::loadHTML
 * with a TicketTemplate-driven layout — there are at least 5 such call
 * sites today (admin ViewTicket, web.php closure, two paths in the
 * marketplace API TicketsController, and OrderResource bulk download).
 * Centralizing here means every path picks up the same gate + the same
 * defensive try/catch, and we cannot miss one.
 *
 * Returns '' when:
 *   - the gate is closed (default everywhere — see SeatingPdfGate)
 *   - the renderer throws for any reason (logged + swallowed)
 *
 * When non-empty, the returned HTML starts with a `page-break-before`
 * div — caller can append it after the ticket content (inside <body>)
 * and DomPDF will paginate.
 */
class SeatingPdfInjector
{
    public static function renderPageFor(Ticket $ticket, float $pageWidthPt, float $pageHeightPt): string
    {
        if (!SeatingPdfGate::shouldRenderFor($ticket)) {
            return '';
        }

        try {
            $event = method_exists($ticket, 'resolveEvent') ? $ticket->resolveEvent() : $ticket->event;
            return view('pdf.ticket-seating-page', [
                'ticket' => $ticket,
                'event' => $event,
                'pageWidthPt' => $pageWidthPt,
                'pageHeightPt' => $pageHeightPt,
            ])->render();
        } catch (\Throwable $e) {
            Log::warning('SeatingPdfInjector render failed — falling back to plain PDF', [
                'ticket_id' => $ticket->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return '';
        }
    }
}
