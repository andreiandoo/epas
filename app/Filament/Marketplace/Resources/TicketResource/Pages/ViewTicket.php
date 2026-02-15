<?php

namespace App\Filament\Marketplace\Resources\TicketResource\Pages;

use App\Filament\Marketplace\Resources\TicketResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Mail\TicketEmail;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketTemplate;
use App\Services\TicketCustomizer\TicketPreviewGenerator;
use App\Services\TicketCustomizer\TicketVariableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Address as SymfonyAddress;

class ViewTicket extends ViewRecord
{
    use HasMarketplaceContext;

    protected static string $resource = TicketResource::class;

    protected string $view = 'filament.tickets.pages.view-ticket';

    protected function hasInfolist(): bool
    {
        // We use a custom blade view instead of infolist
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download Ticket')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $ticket = $this->record;
                    $event = $ticket->resolveEvent();

                    // Resolve the best template: event → marketplace client default → none
                    $template = $this->resolveTicketTemplate($ticket, $event);

                    Log::channel('marketplace')->info('Ticket download', [
                        'ticket_id' => $ticket->id,
                        'event_id' => $event?->id,
                        'ticket_type_id' => $ticket->ticket_type_id,
                        'event_id_column' => $ticket->event_id,
                        'template_id' => $template?->id,
                        'template_name' => $template?->name,
                        'template_status' => $template?->status,
                        'has_template_data' => !empty($template?->template_data),
                        'layers_count' => count($template?->template_data['layers'] ?? []),
                        'using_custom' => $template && !empty($template->template_data) && $template->status === 'active',
                    ]);

                    if ($template && !empty($template->template_data) && $template->status === 'active') {
                        return $this->downloadCustomTemplate($ticket, $template);
                    }

                    // Fallback to generic PDF template
                    return $this->downloadGenericTemplate($ticket, $event);
                }),

            Actions\Action::make('email')
                ->label('Email Ticket')
                ->icon('heroicon-o-envelope')
                ->form([
                    \Filament\Forms\Components\Radio::make('recipient_type')
                        ->label('Send to')
                        ->options([
                            'customer' => 'Customer Email',
                            'custom' => 'Custom Email',
                        ])
                        ->default('customer')
                        ->live()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('custom_email')
                        ->label('Email Address')
                        ->email()
                        ->required()
                        ->visible(fn ($get) => $get('recipient_type') === 'custom'),
                ])
                ->action(function (array $data) {
                    $ticket = $this->record;

                    $email = $data['recipient_type'] === 'customer'
                        ? $ticket->order?->customer_email
                        : $data['custom_email'];

                    if (!$email) {
                        Notification::make()
                            ->title('Adresă email lipsă')
                            ->body('Nu există o adresă de email asociată acestui bilet.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Get marketplace client for mail transport
                    $marketplaceClient = static::getMarketplaceClient();

                    if (!$marketplaceClient?->hasMailConfigured()) {
                        Notification::make()
                            ->title('Email neconfigurat')
                            ->body('Configurați SMTP-ul în setările marketplace-ului pentru a trimite emailuri.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $transport = $marketplaceClient->getMailTransport();
                    if (!$transport) {
                        Notification::make()
                            ->title('Eroare transport email')
                            ->body('Nu s-a putut crea transportul de email. Verificați configurarea SMTP.')
                            ->danger()
                            ->send();
                        return;
                    }

                    Log::channel('marketplace')->info('Ticket email: starting send', [
                        'ticket_id' => $ticket->id,
                        'ticket_code' => $ticket->code,
                        'to' => $email,
                        'mail_driver' => $marketplaceClient->getMailSettings()['driver'] ?? 'unknown',
                    ]);

                    try {
                        // Build ticket email content
                        $ticketMail = new TicketEmail($ticket, static::getMarketplaceClientId());

                        // Render email HTML body
                        $emailBody = view('emails.ticket', [
                            'ticket' => $ticket,
                            'eventTitle' => $ticketMail->eventTitle,
                            'ticketTypeName' => $ticketMail->ticketTypeName,
                            'venueName' => $ticketMail->venueName,
                            'event' => $ticketMail->resolvedEvent,
                        ])->render();

                        // Generate PDF attachment
                        $pdfData = $ticketMail->generatePdfData();

                        // Send via marketplace client's mail transport
                        $symfonyEmail = (new SymfonyEmail())
                            ->from(new SymfonyAddress(
                                $marketplaceClient->getEmailFromAddress(),
                                $marketplaceClient->getEmailFromName()
                            ))
                            ->to($email)
                            ->subject("Biletul tău pentru {$ticketMail->eventTitle}")
                            ->html($emailBody)
                            ->attach($pdfData, "ticket-{$ticket->code}.pdf", 'application/pdf');

                        $transport->send($symfonyEmail);

                        Log::channel('marketplace')->info('Ticket email: sent successfully', [
                            'ticket_id' => $ticket->id,
                            'to' => $email,
                            'from' => $marketplaceClient->getEmailFromAddress(),
                        ]);

                        Notification::make()
                            ->title('Bilet trimis!')
                            ->body("Biletul a fost trimis la {$email}")
                            ->success()
                            ->send();

                        activity('tenant')
                            ->performedOn($ticket)
                            ->withProperties([
                                'sent_to' => $email,
                                'ticket_code' => $ticket->code,
                            ])
                            ->log('Ticket emailed');

                    } catch (\Throwable $e) {
                        Log::channel('marketplace')->error('Ticket email: failed', [
                            'ticket_id' => $ticket->id,
                            'to' => $email,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Eroare trimitere email')
                            ->body('Nu s-a putut trimite emailul: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Resolve the best ticket template for a ticket.
     * Priority: event's assigned template → marketplace client's default template
     * Only returns templates with visible layers.
     */
    protected function resolveTicketTemplate(Ticket $ticket, ?Event $event): ?TicketTemplate
    {
        // 1. Try the event's directly assigned template
        $template = $event?->ticketTemplate;
        if ($template && $this->isTemplateUsable($template)) {
            Log::channel('marketplace')->debug('Template resolved from event assignment', [
                'template_id' => $template->id,
                'template_name' => $template->name,
            ]);
            return $template;
        }

        // 2. Fall back to marketplace client's default active template
        $clientId = $ticket->marketplace_client_id
            ?? $event?->marketplace_client_id
            ?? $ticket->order?->marketplace_client_id
            ?? static::getMarketplaceClientId();

        Log::channel('marketplace')->debug('Template resolution: clientId lookup', [
            'ticket_marketplace_client_id' => $ticket->marketplace_client_id,
            'event_marketplace_client_id' => $event?->marketplace_client_id,
            'order_marketplace_client_id' => $ticket->order?->marketplace_client_id,
            'context_marketplace_client_id' => static::getMarketplaceClientId(),
            'resolved_client_id' => $clientId,
        ]);

        if ($clientId) {
            $defaultTemplate = TicketTemplate::where('marketplace_client_id', $clientId)
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();

            if ($defaultTemplate && $this->isTemplateUsable($defaultTemplate)) {
                Log::channel('marketplace')->debug('Template resolved from marketplace client default', [
                    'template_id' => $defaultTemplate->id,
                    'template_name' => $defaultTemplate->name,
                    'marketplace_client_id' => $clientId,
                ]);
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
                Log::channel('marketplace')->debug('Template resolved from marketplace client (any active)', [
                    'template_id' => $anyTemplate->id,
                    'template_name' => $anyTemplate->name,
                    'marketplace_client_id' => $clientId,
                ]);
                return $anyTemplate;
            }
        }

        Log::channel('marketplace')->debug('No usable ticket template found', [
            'ticket_id' => $ticket->id,
            'event_template_id' => $event?->ticket_template_id,
            'marketplace_client_id' => $clientId ?? null,
        ]);

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

        // Check for at least one visible layer
        $visibleLayers = array_filter($layers, fn($l) => !isset($l['visible']) || $l['visible'] !== false);
        return !empty($visibleLayers);
    }

    /**
     * Download ticket using a custom TicketTemplate (HTML-based PDF)
     */
    protected function downloadCustomTemplate(Ticket $ticket, TicketTemplate $template)
    {
        try {
            $variableService = app(TicketVariableService::class);
            $generator = app(TicketPreviewGenerator::class);

            $data = $variableService->resolveTicketData($ticket);
            $content = $generator->renderToHtml($template->template_data, $data);

            // If rendered content is empty/whitespace, fall back to generic
            if (empty(trim($content))) {
                Log::channel('marketplace')->warning('Ticket custom PDF: renderToHtml produced empty content, falling back to generic', [
                    'ticket_id' => $ticket->id,
                    'template_id' => $template->id,
                ]);
                $event = $ticket->resolveEvent();
                return $this->downloadGenericTemplate($ticket, $event);
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

            Log::channel('marketplace')->debug('Ticket PDF HTML generated', [
                'ticket_id' => $ticket->id,
                'template_id' => $template->id,
                'paper_pt' => [$widthPt, $heightPt],
                'html_length' => strlen($html),
                'content_length' => strlen($content),
                'layers_count' => count($layers),
            ]);
            @file_put_contents(storage_path('app/debug-ticket.html'), $html);

            $pdf = Pdf::loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt])
                ->setOption('isRemoteEnabled', true)
                ->setOption('isHtml5ParserEnabled', true);

            $pdfOutput = $pdf->output();

            if (empty($pdfOutput)) {
                Log::channel('marketplace')->error('Ticket custom PDF: DomPDF produced empty output, falling back to generic', [
                    'ticket_id' => $ticket->id,
                    'template_id' => $template->id,
                ]);
                $event = $ticket->resolveEvent();
                return $this->downloadGenericTemplate($ticket, $event);
            }

            $template->markAsUsed();

            return response()->streamDownload(
                fn () => print($pdfOutput),
                "ticket-{$ticket->code}.pdf"
            );
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('Ticket custom PDF: exception, falling back to generic', [
                'ticket_id' => $ticket->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $event = $ticket->resolveEvent();
            return $this->downloadGenericTemplate($ticket, $event);
        }
    }

    /**
     * Download ticket using the generic PDF template (fallback)
     */
    protected function downloadGenericTemplate(Ticket $ticket, ?Event $event)
    {
        $venue = $event?->venue;
        $marketplace = $ticket->order?->tenant ?? $ticket->order?->marketplaceClient;

        $eventTitle = is_array($event?->title)
            ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
            : ($event?->title ?? 'Event');

        Log::channel('marketplace')->debug('Ticket generic PDF: generating', [
            'ticket_id' => $ticket->id,
            'event_id' => $event?->id,
            'event_title' => $eventTitle,
            'has_venue' => !is_null($venue),
            'has_marketplace' => !is_null($marketplace),
        ]);

        $venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;

        // Generate QR code as base64 data URI
        $qrCodeDataUri = $this->generateQrCodeDataUri($ticket->getVerifyUrl());

        $pdf = Pdf::loadView('pdf.ticket', [
            'ticket' => $ticket,
            'event' => $event,
            'eventTitle' => $eventTitle,
            'venue' => $venue,
            'venueName' => $venueName,
            'beneficiary' => $ticket->meta['beneficiary'] ?? null,
            'tenant' => $marketplace,
            'ticketTerms' => $marketplace?->ticket_terms ?? null,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "ticket-{$ticket->code}.pdf"
        );
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
