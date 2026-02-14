<?php

namespace App\Filament\Marketplace\Resources\TicketResource\Pages;

use App\Filament\Marketplace\Resources\TicketResource;
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

class ViewTicket extends ViewRecord
{
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

                    // Check for custom ticket template
                    $template = $event?->ticketTemplate;

                    Log::channel('marketplace')->info('Ticket download', [
                        'ticket_id' => $ticket->id,
                        'event_id' => $event?->id,
                        'ticket_type_id' => $ticket->ticket_type_id,
                        'event_id_column' => $ticket->event_id,
                        'template_id' => $template?->id,
                        'template_status' => $template?->status,
                        'has_template_data' => !empty($template?->template_data),
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

                    Log::channel('marketplace')->info('Ticket email: starting send', [
                        'ticket_id' => $ticket->id,
                        'ticket_code' => $ticket->code,
                        'to' => $email,
                    ]);

                    try {
                        Mail::to($email)->send(new TicketEmail($ticket));

                        Log::channel('marketplace')->info('Ticket email: sent successfully', [
                            'ticket_id' => $ticket->id,
                            'to' => $email,
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
     * Download ticket using a custom TicketTemplate (SVG-based PDF)
     */
    protected function downloadCustomTemplate(Ticket $ticket, TicketTemplate $template)
    {
        $variableService = app(TicketVariableService::class);
        $generator = app(TicketPreviewGenerator::class);

        // Resolve real ticket data
        $data = $variableService->resolveTicketData($ticket);

        // Generate SVG from template with real data
        $svg = $generator->renderToSvg($template->template_data, $data);

        // Get paper dimensions from template (mm to points: 1mm = 2.8346pt)
        $size = $template->getSize();
        $widthPt = round($size['width'] * 2.8346, 2);
        $heightPt = round($size['height'] * 2.8346, 2);
        $widthMm = $size['width'];
        $heightMm = $size['height'];

        // Wrap SVG in minimal HTML for DomPDF
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: {$widthMm}mm {$heightMm}mm; margin: 0; }
        body { width: {$widthMm}mm; height: {$heightMm}mm; overflow: hidden; }
        svg { display: block; width: {$widthMm}mm; height: {$heightMm}mm; }
    </style>
</head>
<body>
{$svg}
</body>
</html>
HTML;

        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, $widthPt, $heightPt]);

        // Mark template as used
        $template->markAsUsed();

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "ticket-{$ticket->code}.pdf"
        );
    }

    /**
     * Download ticket using the generic PDF template (fallback)
     */
    protected function downloadGenericTemplate(Ticket $ticket, ?Event $event)
    {
        $venue = $event?->venue;
        $marketplace = $ticket->order?->tenant;

        $eventTitle = is_array($event?->title)
            ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
            : ($event?->title ?? 'Event');

        $venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;

        // Generate QR code as base64 data URI
        $qrCodeDataUri = $this->generateQrCodeDataUri($ticket->code);

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
