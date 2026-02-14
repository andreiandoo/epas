<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
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
    public string $ticketTypeName;
    public ?string $venueName;
    public $resolvedEvent;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->resolvedEvent = $ticket->resolveEvent();
        $this->eventTitle = $this->resolveEventTitle();
        $this->ticketTypeName = $ticket->resolveTicketTypeName();
        $venue = $this->resolvedEvent?->venue;
        $this->venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;
    }

    private function resolveEventTitle(): string
    {
        $event = $this->resolvedEvent;
        if (!$event) {
            return 'Event';
        }

        if (method_exists($event, 'getTranslation')) {
            $title = $event->getTranslation('title', 'ro')
                ?? $event->getTranslation('title', 'en');
            if ($title) {
                return $title;
            }
        }

        if (is_array($event->title)) {
            return $event->title['ro'] ?? $event->title['en'] ?? reset($event->title) ?: 'Event';
        }

        return $event->title ?? 'Event';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Biletul tău pentru {$this->eventTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket',
            with: [
                'ticket' => $this->ticket,
                'eventTitle' => $this->eventTitle,
                'ticketTypeName' => $this->ticketTypeName,
                'venueName' => $this->venueName,
                'event' => $this->resolvedEvent,
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
        $event = $this->resolvedEvent;

        // Check for custom ticket template
        $template = $event?->ticketTemplate;

        if ($template && !empty($template->template_data) && $template->status === 'active') {
            return $this->generateCustomPdfData($ticket, $template);
        }

        return $this->generateGenericPdfData($ticket, $event);
    }

    /**
     * Generate PDF data using a custom TicketTemplate (HTML-based)
     */
    protected function generateCustomPdfData(Ticket $ticket, $template): string
    {
        $variableService = app(TicketVariableService::class);
        $generator = app(TicketPreviewGenerator::class);

        $data = $variableService->resolveTicketData($ticket);
        $content = $generator->renderToHtml($template->template_data, $data);

        $size = $template->getSize();
        $widthPt = round($size['width'] * 2.8346, 2);
        $heightPt = round($size['height'] * 2.8346, 2);
        $widthMm = $size['width'];
        $heightMm = $size['height'];

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: {$widthMm}mm {$heightMm}mm; margin: 0; }
        body { width: {$widthMm}mm; height: {$heightMm}mm; overflow: hidden; }
        img { display: block; }
    </style>
</head>
<body>
{$content}
</body>
</html>
HTML;

        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, $widthPt, $heightPt]);

        $template->markAsUsed();

        return $pdf->output();
    }

    /**
     * Generate PDF data using the generic template (fallback)
     */
    protected function generateGenericPdfData(Ticket $ticket, $event): string
    {
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
