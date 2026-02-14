<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function generatePdfData(): string
    {
        $ticket = $this->ticket;
        $event = $this->resolvedEvent;

        // Resolve the best template: event → marketplace client default → any active
        $template = $this->resolveTicketTemplate($ticket, $event);

        Log::channel('marketplace')->debug('TicketEmail PDF: template resolution', [
            'ticket_id' => $ticket->id,
            'event_id' => $event?->id,
            'template_id' => $template?->id,
            'template_name' => $template?->name,
            'using_custom' => !is_null($template),
        ]);

        if ($template) {
            try {
                return $this->generateCustomPdfData($ticket, $template);
            } catch (\Throwable $e) {
                Log::channel('marketplace')->error('TicketEmail custom PDF failed, using generic', [
                    'ticket_id' => $ticket->id,
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->generateGenericPdfData($ticket, $event);
    }

    /**
     * Resolve the best ticket template for a ticket.
     * Priority: event's assigned template → marketplace client's default → any active
     */
    protected function resolveTicketTemplate(Ticket $ticket, $event): ?TicketTemplate
    {
        // 1. Try the event's directly assigned template
        $template = $event?->ticketTemplate;
        if ($template && $this->isTemplateUsable($template)) {
            return $template;
        }

        // 2. Fall back to marketplace client's default active template
        $clientId = $ticket->marketplace_client_id
            ?? $event?->marketplace_client_id
            ?? $ticket->order?->marketplace_client_id;

        if ($clientId) {
            $defaultTemplate = TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();

            if ($defaultTemplate && $this->isTemplateUsable($defaultTemplate)) {
                return $defaultTemplate;
            }

            // 3. Try any active template with layers for this marketplace client
            $anyTemplate = TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->get()
                ->first(fn ($t) => $this->isTemplateUsable($t));

            if ($anyTemplate) {
                return $anyTemplate;
            }
        }

        return null;
    }

    /**
     * Check if a template is usable (active, has template_data with visible layers)
     */
    protected function isTemplateUsable(?TicketTemplate $template): bool
    {
        if (!$template || $template->status !== 'active' || empty($template->template_data)) {
            return false;
        }

        $layers = $template->template_data['layers'] ?? [];
        if (empty($layers)) {
            return false;
        }

        $visibleLayers = array_filter($layers, fn($l) => !isset($l['visible']) || $l['visible'] !== false);
        return !empty($visibleLayers);
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

        // If content is empty, fall back to generic
        if (empty(trim($content))) {
            Log::channel('marketplace')->warning('TicketEmail custom PDF: empty content, using generic', [
                'ticket_id' => $ticket->id,
                'template_id' => $template->id,
            ]);
            return $this->generateGenericPdfData($ticket, $this->resolvedEvent);
        }

        $size = $template->getSize();
        $widthPt = round($size['width'] * 2.8346, 2);
        $heightPt = round($size['height'] * 2.8346, 2);
        $bgColor = $template->template_data['meta']['background']['color'] ?? '#ffffff';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; size: {$widthPt}pt {$heightPt}pt; }
        * { margin: 0; padding: 0; }
        body { margin: 0; padding: 0; width: {$widthPt}pt; height: {$heightPt}pt; background-color: {$bgColor}; font-family: 'DejaVu Sans', sans-serif; overflow: hidden; }
    </style>
</head>
<body>
{$content}
</body>
</html>
HTML;

        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, $widthPt, $heightPt])
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $pdfOutput = $pdf->output();

        if (empty($pdfOutput)) {
            return $this->generateGenericPdfData($ticket, $this->resolvedEvent);
        }

        $template->markAsUsed();

        return $pdfOutput;
    }

    /**
     * Generate PDF data using the generic template (fallback)
     */
    protected function generateGenericPdfData(Ticket $ticket, $event): string
    {
        $venue = $event?->venue;
        $tenant = $ticket->order?->tenant ?? $ticket->order?->marketplaceClient;

        // Generate QR code as base64 data URI
        $qrCodeDataUri = $this->generateQrCodeDataUri($ticket->getVerifyUrl());

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
