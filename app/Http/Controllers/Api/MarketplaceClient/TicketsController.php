<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Order;
use App\Models\Ticket;
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

        $order = Order::with(['tickets.ticketType', 'event'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if ($order->status !== 'completed' || $order->payment_status !== 'paid') {
            return $this->error('Tickets are only available for completed orders', 400);
        }

        $tickets = $order->tickets->map(function ($ticket) use ($order) {
            return [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'qr_code' => $ticket->qr_code_url ?? route('api.marketplace-client.tickets.qr', [
                    'ticket' => $ticket->id,
                ]),
                'status' => $ticket->status,
                'ticket_type' => $ticket->ticketType?->name,
                'event' => [
                    'id' => $order->event->id,
                    'title' => $order->event->title,
                    'date' => $order->event->start_date?->toIso8601String(),
                    'venue' => $order->event->venue?->name,
                    'address' => $order->event->venue?->address,
                ],
                'attendee' => [
                    'name' => $ticket->attendee_name ?? $order->customer_name,
                    'email' => $ticket->attendee_email ?? $order->customer_email,
                ],
                'download_url' => route('api.marketplace-client.tickets.download', [
                    'ticket' => $ticket->id,
                ]),
            ];
        });

        return $this->success([
            'order_number' => $order->order_number,
            'tickets' => $tickets,
            'download_all_url' => route('api.marketplace-client.orders.tickets.download', [
                'order' => $order->id,
            ]),
        ]);
    }

    /**
     * Download a single ticket PDF
     */
    public function download(Request $request, int $ticketId): mixed
    {
        $client = $this->requireClient($request);

        $ticket = Ticket::with(['order', 'ticketType', 'event'])
            ->where('id', $ticketId)
            ->whereHas('order', function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id)
                    ->where('status', 'completed')
                    ->where('payment_status', 'paid');
            })
            ->first();

        if (!$ticket) {
            return $this->error('Ticket not found or not available for download', 404);
        }

        // Check if PDF already exists
        $pdfPath = "tickets/{$ticket->id}.pdf";

        if (!Storage::disk('local')->exists($pdfPath)) {
            // Generate PDF (you'll need to implement this based on your ticket generation system)
            $this->generateTicketPdf($ticket, $pdfPath);
        }

        Log::channel('marketplace')->info('Ticket downloaded', [
            'ticket_id' => $ticket->id,
            'order_id' => $ticket->order_id,
            'client_id' => $client->id,
        ]);

        return Storage::disk('local')->download($pdfPath, "ticket-{$ticket->barcode}.pdf");
    }

    /**
     * Download all tickets for an order as ZIP
     */
    public function downloadAll(Request $request, int $orderId): mixed
    {
        $client = $this->requireClient($request);

        $order = Order::with(['tickets.ticketType', 'event'])
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->first();

        if (!$order) {
            return $this->error('Order not found or not available for download', 404);
        }

        // Generate ZIP file with all tickets
        $zipPath = "tickets/orders/{$order->order_number}.zip";

        if (!Storage::disk('local')->exists($zipPath)) {
            $this->generateOrderTicketsZip($order, $zipPath);
        }

        Log::channel('marketplace')->info('All tickets downloaded', [
            'order_id' => $order->id,
            'client_id' => $client->id,
            'ticket_count' => $order->tickets->count(),
        ]);

        return Storage::disk('local')->download($zipPath, "tickets-{$order->order_number}.zip");
    }

    /**
     * Get QR code for a ticket
     */
    public function qrCode(Request $request, int $ticketId): mixed
    {
        $client = $this->requireClient($request);

        $ticket = Ticket::whereHas('order', function ($query) use ($client) {
            $query->where('marketplace_client_id', $client->id)
                ->where('status', 'completed')
                ->where('payment_status', 'paid');
        })->find($ticketId);

        if (!$ticket) {
            return $this->error('Ticket not found', 404);
        }

        // Generate QR code
        $qrCodePath = "tickets/qr/{$ticket->id}.png";

        if (!Storage::disk('local')->exists($qrCodePath)) {
            $this->generateQrCode($ticket, $qrCodePath);
        }

        return response()->file(Storage::disk('local')->path($qrCodePath), [
            'Content-Type' => 'image/png',
        ]);
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

        $ticket = Ticket::with(['order', 'ticketType', 'event'])
            ->where('barcode', $request->barcode)
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

        $isValid = $ticket->status === 'valid' && $ticket->order->status === 'completed';

        return $this->success([
            'valid' => $isValid,
            'status' => $ticket->status,
            'message' => $isValid ? 'Ticket is valid' : "Ticket status: {$ticket->status}",
            'ticket' => $isValid ? [
                'id' => $ticket->id,
                'barcode' => $ticket->barcode,
                'ticket_type' => $ticket->ticketType?->name,
                'event' => $ticket->event->title,
                'attendee_name' => $ticket->attendee_name ?? $ticket->order->customer_name,
                'checked_in' => $ticket->checked_in_at !== null,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * Generate ticket PDF (stub - implement based on your system)
     */
    protected function generateTicketPdf(Ticket $ticket, string $path): void
    {
        // This would integrate with your existing ticket PDF generation system
        // For example, using the TicketTemplateController or a dedicated service

        // Placeholder - you'll need to implement this
        Storage::disk('local')->put($path, 'PDF content placeholder');
    }

    /**
     * Generate ZIP with all order tickets (stub)
     */
    protected function generateOrderTicketsZip(Order $order, string $path): void
    {
        // Create a ZIP file with all ticket PDFs
        // Placeholder - implement based on your needs

        $zip = new \ZipArchive();
        $tempPath = storage_path('app/' . $path);

        Storage::disk('local')->makeDirectory(dirname($path));

        if ($zip->open($tempPath, \ZipArchive::CREATE) === true) {
            foreach ($order->tickets as $ticket) {
                $ticketPdfPath = "tickets/{$ticket->id}.pdf";
                if (!Storage::disk('local')->exists($ticketPdfPath)) {
                    $this->generateTicketPdf($ticket, $ticketPdfPath);
                }
                $zip->addFile(
                    Storage::disk('local')->path($ticketPdfPath),
                    "ticket-{$ticket->barcode}.pdf"
                );
            }
            $zip->close();
        }
    }

    /**
     * Generate QR code for ticket (stub)
     */
    protected function generateQrCode(Ticket $ticket, string $path): void
    {
        // Use a QR code library to generate the code
        // For example: SimpleSoftwareIO/simple-qrcode

        Storage::disk('local')->makeDirectory(dirname($path));

        // Placeholder - implement with actual QR generation
        // Example with simple-qrcode:
        // $qrCode = QrCode::format('png')->size(300)->generate($ticket->barcode);
        // Storage::disk('local')->put($path, $qrCode);

        Storage::disk('local')->put($path, 'QR placeholder');
    }
}
