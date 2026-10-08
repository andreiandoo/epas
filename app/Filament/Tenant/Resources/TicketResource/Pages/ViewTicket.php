<?php

namespace App\Filament\Tenant\Resources\TicketResource\Pages;

use App\Filament\Tenant\Resources\TicketResource;
use App\Mail\TicketEmail;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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
                ->label(__('Download Ticket'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $ticket = $this->record;

                    // Biletul cu designul tenantului (Ticket Customizer), același pe care îl primește cumpărătorul
                    $order = $ticket->order;
                    $designed = $order ? \App\Http\Controllers\Api\TenantClient\DemoStorefrontController::ticketsPdfUrl($order) : null;
                    if ($designed) {
                        return redirect()->away($designed . '&code=' . urlencode((string) $ticket->code));
                    }

                    $event = $ticket->ticketType?->event;
                    $venue = $event?->venue;
                    $tenant = $ticket->order?->tenant;

                    $eventTitle = is_array($event?->title)
                        ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
                        : ($event?->title ?? 'Event');

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
                        'tenant' => $tenant,
                        'ticketTerms' => $tenant?->ticket_terms ?? null,
                        'qrCodeDataUri' => $qrCodeDataUri,
                    ]);

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        "ticket-{$ticket->code}.pdf"
                    );
                }),

            Actions\Action::make('email')
                ->label(__('Email Ticket'))
                ->icon('heroicon-o-envelope')
                ->form([
                    \Filament\Forms\Components\Radio::make('recipient_type')
                        ->label(__('Send to'))
                        ->options([
                            'customer' => __('Customer Email'),
                            'custom' => __('Custom Email'),
                        ])
                        ->default('customer')
                        ->live()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('custom_email')
                        ->label(__('Email Address'))
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
                            ->title(__('No Email Address'))
                            ->body(__('No email address available for this ticket.'))
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        Mail::to($email)->send(new TicketEmail($ticket));

                        Notification::make()
                            ->title(__('Ticket Sent!'))
                            ->body("Ticket has been sent to {$email}")
                            ->success()
                            ->send();

                        // Log activity
                        activity('tenant')
                            ->performedOn($ticket)
                            ->withProperties([
                                'sent_to' => $email,
                                'ticket_code' => $ticket->code,
                            ])
                            ->log('Ticket emailed');

                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('Email Failed'))
                            ->body('Failed to send email: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('change_status')
                ->label('Schimbă starea')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label('Stare nouă')
                        ->options([
                            'valid' => 'Valid',
                            'used' => 'Folosit',
                            'cancelled' => 'Anulat',
                            'pending' => 'În așteptare',
                        ])
                        ->default(fn () => $this->record->status)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Schimbă starea biletului')
                ->modalDescription('Un bilet anulat nu mai e acceptat la intrare.')
                ->action(function (array $data) {
                    $ticket = $this->record;
                    $oldStatus = $ticket->status;
                    $newStatus = $data['status'];
                    $hadCheckIn = $ticket->checked_in_at !== null || $ticket->scanned_at !== null;

                    // Un bilet scanat rămâne „valid” și primește doar checked_in_at, deci
                    // „valid + scanat → valid” e o schimbare reală (șterge scanarea).
                    if ($oldStatus === $newStatus && ! ($newStatus === 'valid' && $hadCheckIn)) {
                        Notification::make()->title('Starea nu s-a schimbat')->info()->send();

                        return;
                    }

                    $ticket->update(['status' => $newStatus] + ($newStatus === 'valid' ? self::clearedCheckInAttributes() : []));

                    activity('tenant')
                        ->performedOn($ticket)
                        ->withProperties(['old_status' => $oldStatus, 'new_status' => $newStatus, 'ticket_code' => $ticket->code])
                        ->log("Ticket status changed: {$oldStatus} → {$newStatus}");

                    Notification::make()->title('Stare actualizată')->success()->send();
                }),

            // Scanarea ține de coloanele de check-in, nu de stare: un bilet scanat din greșeală se repune aici
            Actions\Action::make('undo_checkin')
                ->label('Anulează check-in')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => $this->record->checked_in_at !== null || $this->record->scanned_at !== null)
                ->requiresConfirmation()
                ->modalHeading('Anulează check-in-ul biletului')
                ->modalDescription('Biletul redevine nescanat și poate fi folosit din nou la intrare.')
                ->action(function () {
                    $ticket = $this->record;
                    $oldStatus = $ticket->status;
                    $attributes = self::clearedCheckInAttributes();
                    if ($oldStatus === 'used') {
                        $attributes['status'] = 'valid';
                    }
                    $ticket->update($attributes);

                    activity('tenant')
                        ->performedOn($ticket)
                        ->withProperties(['ticket_code' => $ticket->code, 'old_status' => $oldStatus, 'new_status' => $ticket->status])
                        ->log('Ticket check-in undone');

                    Notification::make()->title('Check-in anulat')->body('Biletul poate fi scanat din nou la intrare.')->success()->send();
                }),
        ];
    }

    /** Toate coloanele care marchează trecerea biletului pe la intrare (aplicațiile de scanare + panoul de operator). */
    protected static function clearedCheckInAttributes(): array
    {
        return [
            'checked_in_at' => null,
            'checked_in_by' => null,
            'checked_in_via' => null,
            'scanned_at' => null,
            'scanned_by_user_id' => null,
        ];
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
