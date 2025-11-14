<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
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
            Actions\EditAction::make()
                ->label('Edit Ticket'),

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

            Actions\Action::make('see_event')
                ->label('See Event')
                ->icon('heroicon-o-calendar')
                ->url(fn ($record) => $record->ticketType && $record->ticketType->event
                    ? \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $record->ticketType->event])
                    : null
                )
                ->visible(fn ($record) => $record->ticketType && $record->ticketType->event),

            Actions\Action::make('see_tenant')
                ->label('See Tenant')
                ->icon('heroicon-o-building-office')
                ->url(fn ($record) => $record->order && $record->order->tenant
                    ? \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $record->order->tenant])
                    : null
                )
                ->visible(fn ($record) => $record->order && $record->order->tenant),

            Actions\Action::make('see_order')
                ->label('See Order')
                ->icon('heroicon-o-shopping-cart')
                ->url(fn ($record) => $record->order
                    ? \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record->order])
                    : null
                )
                ->visible(fn ($record) => $record->order !== null),

            Actions\Action::make('see_customer')
                ->label('See Customer')
                ->icon('heroicon-o-user')
                ->url(fn ($record) => $record->order && $record->order->customer
                    ? \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->order->customer])
                    : null
                )
                ->visible(fn ($record) => $record->order && $record->order->customer),
        ];
    }
}
