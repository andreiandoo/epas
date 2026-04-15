<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TicketsController extends BaseController
{
    /**
     * Get tickets for an order
     */
    public function index(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::with(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if (!in_array($order->status, ['completed', 'confirmed', 'paid'])) {
            return $this->error('Tickets are only available for completed orders', 400);
        }

        $tickets = $order->tickets->map(function ($ticket) use ($order) {
            $event = $ticket->marketplaceEvent;

            return [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'code' => $ticket->code,
                'status' => $ticket->status,
                'ticket_type' => $ticket->marketplaceTicketType?->name,
                'attendee' => [
                    'name' => $ticket->attendee_name ?? $order->customer_name,
                    'email' => $ticket->attendee_email ?? $order->customer_email,
                ],
                'event' => $event ? [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->starts_at?->toIso8601String(),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                ] : null,
                'seat' => method_exists($ticket, 'getSeatDetails') ? $ticket->getSeatDetails() : null,
                'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                    'size' => '180x180',
                    'data' => method_exists($ticket, 'getVerifyUrl') ? $ticket->getVerifyUrl() : ($ticket->code ?? $ticket->barcode),
                    'color' => '1a1a2e',
                    'margin' => '0',
                ]),
            ];
        });

        return $this->success([
            'order_number' => $order->order_number,
            'tickets' => $tickets,
        ]);
    }

    /**
     * Download all tickets for an order as PDF.
     * Uses custom template if available, falls back to generic.
     * Public endpoint — authenticated by marketplace API key + order reference number.
     */
    public function downloadPdf(Request $request): mixed
    {
        $client = $this->requireClient($request);

        $orderRef = $request->query('order');
        if (!$orderRef) {
            return $this->error('Missing order reference', 400);
        }

        $order = Order::with(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType', 'tickets.ticketType', 'tickets.event', 'tickets.event.venue', 'tickets.event.marketplaceOrganizer'])
            ->where('marketplace_client_id', $client->id)
            ->where('order_number', $orderRef)
            ->whereIn('status', ['completed', 'confirmed', 'paid'])
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        $tickets = $order->tickets;
        if ($tickets->isEmpty()) {
            return $this->error('No tickets found for this order', 404);
        }

        $safeOrderNum = preg_replace('/[^a-zA-Z0-9_\-]/', '-', $order->order_number);
        $filename = "bilete-{$safeOrderNum}.pdf";

        // Try custom template — generate one page per ticket
        $firstTicket = $tickets->first();
        $template = $this->resolveTicketTemplate($firstTicket, $client);

        if ($template) {
            try {
                $variableService = app(TicketVariableService::class);
                $generator = app(TicketPreviewGenerator::class);

                $size = $template->getSize();
                $widthPt = round($size['width'] * 2.8346, 2);
                $heightPt = round($size['height'] * 2.8346, 2);
                $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

                $pages = [];
                foreach ($tickets as $ticket) {
                    $data = $variableService->resolveTicketData($ticket);
                    $content = $generator->renderToHtml($template->template_data, $data);
                    if (!empty(trim($content))) {
                        $pages[] = $content;
                    }
                }

                if (!empty($pages)) {
                    $pagesHtml = implode('<div style="page-break-after: always;"></div>', $pages);
                    $html = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><style>@page { margin: 0; size: {$widthPt}pt {$heightPt}pt; } * { margin: 0; padding: 0; } body { margin: 0; padding: 0; width: {$widthPt}pt; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; overflow: hidden; }</style></head><body>{$pagesHtml}</body></html>";

                    $pdf = Pdf::loadHTML($html)
                        ->setPaper([0, 0, $widthPt, $heightPt])
                        ->setOption('isRemoteEnabled', true)
                        ->setOption('isHtml5ParserEnabled', true);

                    $pdfOutput = $pdf->output();
                    if (!empty($pdfOutput)) {
                        $template->markAsUsed();
                        return response()->streamDownload(fn () => print($pdfOutput), $filename, [
                            'Content-Type' => 'application/pdf',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('marketplace')->warning('Order PDF custom template failed, using generic', [
                    'order_id' => $order->id,
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to generic template
        $eventName = $firstTicket->marketplaceEvent?->name ?? 'Eveniment';
        $marketplaceName = $client->public_name ?? $client->name ?? 'Marketplace';
        $primaryColor = $client->settings['theme']['primary_color'] ?? '#1a1a2e';

        $pdf = Pdf::loadView('marketplace-tickets-pdf', [
            'order' => $order,
            'tickets' => $tickets,
            'eventName' => $eventName,
            'marketplaceName' => $marketplaceName,
            'primaryColor' => $primaryColor,
        ])
            ->setOption('isRemoteEnabled', true)
            ->setPaper([0, 0, 396, 700], 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Download a single ticket PDF — uses custom template if available, falls back to generic.
     */
    public function download(Request $request, int $ticketId): mixed
    {
        $client = $this->requireClient($request);

        $ticket = Ticket::with(['order', 'marketplaceEvent', 'marketplaceTicketType', 'ticketType', 'event', 'event.venue', 'event.marketplaceOrganizer'])
            ->where('id', $ticketId)
            ->whereHas('order', function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id)
                    ->whereIn('status', ['completed', 'confirmed', 'paid']);
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found or not available for download', 404);
        }

        $ticketCode = $ticket->code ?? $ticket->barcode ?? $ticket->id;
        $filename = "bilet-{$ticketCode}.pdf";

        // Try custom template
        $template = $this->resolveTicketTemplate($ticket, $client);
        if ($template) {
            try {
                $variableService = app(TicketVariableService::class);
                $generator = app(TicketPreviewGenerator::class);

                $data = $variableService->resolveTicketData($ticket);
                $content = $generator->renderToHtml($template->template_data, $data);

                if (!empty(trim($content))) {
                    $size = $template->getSize();
                    $widthPt = round($size['width'] * 2.8346, 2);
                    $heightPt = round($size['height'] * 2.8346, 2);
                    $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

                    $html = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><style>@page { margin: 0; size: {$widthPt}pt {$heightPt}pt; } * { margin: 0; padding: 0; } body { margin: 0; padding: 0; width: {$widthPt}pt; height: {$heightPt}pt; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; overflow: hidden; }</style></head><body>{$content}</body></html>";

                    $pdf = Pdf::loadHTML($html)
                        ->setPaper([0, 0, $widthPt, $heightPt])
                        ->setOption('isRemoteEnabled', true)
                        ->setOption('isHtml5ParserEnabled', true);

                    $pdfOutput = $pdf->output();
                    if (!empty($pdfOutput)) {
                        $template->markAsUsed();
                        return response()->streamDownload(fn () => print($pdfOutput), $filename);
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('marketplace')->warning('Ticket custom PDF failed, using generic', [
                    'ticket_id' => $ticket->id,
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to generic template
        $order = $ticket->order;
        $eventName = $ticket->marketplaceEvent?->name ?? 'Eveniment';
        $marketplaceName = $client->public_name ?? $client->name ?? 'Marketplace';
        $primaryColor = $client->settings['theme']['primary_color'] ?? '#1a1a2e';

        $pdf = Pdf::loadView('marketplace-tickets-pdf', [
            'order' => $order,
            'tickets' => collect([$ticket]),
            'eventName' => $eventName,
            'marketplaceName' => $marketplaceName,
            'primaryColor' => $primaryColor,
        ])
            ->setOption('isRemoteEnabled', true)
            ->setPaper([0, 0, 396, 700], 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Resolve the best ticket template for a ticket.
     * Priority: event's assigned template → marketplace client default → any active template.
     */
    protected function resolveTicketTemplate(Ticket $ticket, $client): ?TicketTemplate
    {
        // 1. Event's assigned template
        $event = $ticket->event ?? $ticket->marketplaceEvent;
        if ($event && $event->ticket_template_id) {
            $template = TicketTemplate::find($event->ticket_template_id);
            if ($template && $this->isTemplateUsable($template)) {
                return $template;
            }
        }

        // 2. Marketplace client default
        $clientId = $ticket->marketplace_client_id ?? $client->id;
        $default = TicketTemplate::where('marketplace_client_id', $clientId)
            ->where('status', 'active')
            ->where('is_default', true)
            ->first();
        if ($default && $this->isTemplateUsable($default)) {
            return $default;
        }

        // 3. Any active template
        return TicketTemplate::where('marketplace_client_id', $clientId)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->get()
            ->first(fn ($t) => $this->isTemplateUsable($t));
    }

    protected function isTemplateUsable(?TicketTemplate $template): bool
    {
        if (!$template || $template->status !== 'active' || empty($template->template_data)) {
            return false;
        }
        $layers = $template->template_data['layers'] ?? [];
        $visible = array_filter($layers, fn ($l) => !isset($l['visible']) || $l['visible'] !== false);
        return !empty($visible);
    }

    /**
     * Download all tickets for an order as ZIP
     */
    public function downloadAll(Request $request, int $orderId): mixed
    {
        $client = $this->requireClient($request);

        $order = Order::with(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->whereIn('status', ['completed', 'confirmed', 'paid'])
            ->first();

        if (!$order) {
            return $this->error('Order not found or not available for download', 404);
        }

        $eventName = $order->tickets->first()?->marketplaceEvent?->name ?? 'Eveniment';
        $marketplaceName = $client->public_name ?? $client->name ?? 'Marketplace';
        $primaryColor = $client->settings['theme']['primary_color'] ?? '#1a1a2e';

        // For simplicity, return all tickets in a single PDF (better UX than ZIP)
        $pdf = Pdf::loadView('marketplace-tickets-pdf', [
            'order' => $order,
            'tickets' => $order->tickets,
            'eventName' => $eventName,
            'marketplaceName' => $marketplaceName,
            'primaryColor' => $primaryColor,
        ])
            ->setOption('isRemoteEnabled', true)
            ->setPaper([0, 0, 396, 700], 'portrait');

        $filename = "bilete-{$order->order_number}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Get QR code for a ticket
     */
    public function qrCode(Request $request, int $ticketId): mixed
    {
        $client = $this->requireClient($request);

        $ticket = Ticket::whereHas('order', function ($query) use ($client) {
            $query->where('marketplace_client_id', $client->id)
                ->whereIn('status', ['completed', 'confirmed', 'paid']);
        })->find($ticketId);

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
        }

        // Redirect to QR code API
        $verifyUrl = method_exists($ticket, 'getVerifyUrl') ? $ticket->getVerifyUrl() : ($ticket->code ?? $ticket->barcode);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => '300x300',
            'data' => $verifyUrl,
            'color' => '1a1a2e',
            'margin' => '1',
            'format' => 'png',
        ]);

        return redirect($qrUrl);
    }

    /**
     * Validate a ticket barcode
     */
    public function validate(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $request->validate([
            'barcode' => 'required|string',
        ]);

        $ticket = Ticket::with(['order', 'marketplaceTicketType', 'marketplaceEvent'])
            ->where(function ($q) use ($request) {
                $q->where('barcode', $request->barcode)
                    ->orWhere('code', $request->barcode);
            })
            ->whereHas('order', function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id);
            })
            ->first();

        if (!$ticket) {
            return $this->success([
                'valid' => false,
                'message' => 'Ticket not found',
            ]);
        }

        $isValid = $ticket->status === 'valid' && in_array($ticket->order->status, ['completed', 'confirmed', 'paid']);

        return $this->success([
            'valid' => $isValid,
            'status' => $ticket->status,
            'message' => $isValid ? 'Ticket is valid' : "Ticket status: {$ticket->status}",
            'ticket' => $isValid ? [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->marketplaceTicketType?->name,
                'event' => $ticket->marketplaceEvent?->name,
                'attendee_name' => $ticket->attendee_name ?? $ticket->order->customer_name,
                'checked_in' => $ticket->checked_in_at !== null,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
