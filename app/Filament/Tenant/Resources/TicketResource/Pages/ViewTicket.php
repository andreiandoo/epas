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
                ->label('Download Ticket')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $ticket = $this->record;
                    $event = $ticket->ticketType?->event;
                    $venue = $event?->venue;
                    $tenant = $ticket->order?->tenant;

                    $eventTitle = is_array($event?->title)
                        ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title))
                        : ($event?->title ?? 'Event');

                    $venueName = $venue?->getTranslation('name', app()->getLocale()) ?? null;

                    $pdf = Pdf::loadView('pdf.ticket', [
                        'ticket' => $ticket,
                        'event' => $event,
                        'eventTitle' => $eventTitle,
                        'venue' => $venue,
                        'venueName' => $venueName,
                        'beneficiary' => $ticket->meta['beneficiary'] ?? null,
                        'tenant' => $tenant,
                        'ticketTerms' => $tenant?->ticket_terms ?? null,
                    ]);

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        "ticket-{$ticket->code}.pdf"
                    );
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
                            ->title('No Email Address')
                            ->body('No email address available for this ticket.')
                            ->danger()
                            ->send();
                        return;
                    }

                    try {
                        Mail::to($email)->send(new TicketEmail($ticket));

                        Notification::make()
                            ->title('Ticket Sent!')
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
                            ->title('Email Failed')
                            ->body('Failed to send email: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
