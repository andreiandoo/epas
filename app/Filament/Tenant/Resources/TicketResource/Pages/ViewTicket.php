<?php

namespace App\Filament\Tenant\Resources\TicketResource\Pages;

use App\Filament\Tenant\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

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
                    // TODO: Implement PDF generation and download
                    \Filament\Notifications\Notification::make()
                        ->title('Coming Soon')
                        ->body('PDF download will be implemented once packages are installed.')
                        ->info()
                        ->send();
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
                ->action(function (array $data, $record) {
                    // TODO: Implement email sending
                    $email = $data['recipient_type'] === 'customer'
                        ? $record->order?->customer_email
                        : $data['custom_email'];

                    \Filament\Notifications\Notification::make()
                        ->title('Coming Soon')
                        ->body("Email functionality will be implemented soon. Would send to: {$email}")
                        ->info()
                        ->send();
                }),
        ];
    }
}
