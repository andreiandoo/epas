<?php

namespace App\Mail;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $eventTitle;
    public ?string $venueName;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $event = $ticket->ticketType?->event;
        $this->eventTitle = is_array($event?->title)
            ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
            : ($event?->title ?? 'Event');
        $venue = $event?->venue;
        $this->venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Ticket for {$this->eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket',
            with: [
                'ticket' => $this->ticket,
                'eventTitle' => $this->eventTitle,
                'venueName' => $this->venueName,
                'event' => $this->ticket->ticketType?->event,
            ],
        );
    }

    public function attachments(): array
    {
        $pdfData = $this->generatePdfData();

        return [
            Attachment::fromData(fn () => $pdfData, "ticket-{$this->ticket->code}.pdf")
                ->withMime('application/pdf'),
        ];
    }

    protected function generatePdfData(): string
    {
        $ticket = $this->ticket;
        $event = $ticket->ticketType?->event;
        $venue = $event?->venue;
        $tenant = $ticket->order?->tenant;

        // Generate QR code as base64 data URI
        $qrCodeDataUri = $this->generateQrCodeDataUri($ticket->code);

        $pdf = Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'event' => $event,
            'eventTitle' => $this->eventTitle,
            'venue' => $venue,
            'venueName' => $this->venueName,
            'beneficiary' => $ticket->meta['beneficiary'] ?? null,
            'tenant' => $tenant,
            'ticketTerms' => $tenant?->ticket_terms ?? null,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);

        return $pdf->output();
    }

    /**
     * Generate a QR code as a base64 data URI
     */
    protected function generateQrCodeDataUri(string $data): string
    {
        try {
            // Fetch QR code from API and convert to base64
            $url = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                'size' => '180x180',
                'data' => $data,
                'color' => '181622',
                'margin' => '0',
                'format' => 'png',
            ]);

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);

            if ($imageData !== false) {
                return 'data:image/png;base64,' . base64_encode($imageData);
            }
        } catch (\Exception $e) {
            // Fallback to a simple placeholder if QR generation fails
        }

        // Return a simple SVG placeholder if external API fails
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180">
            <rect width="180" height="180" fill="#f3f4f6"/>
            <text x="90" y="90" text-anchor="middle" font-family="monospace" font-size="12" fill="#6b7280">QR: ' . htmlspecialchars(substr($data, 0, 10)) . '</text>
        </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
