<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.orders.pages.view-order';

    protected function hasInfolist(): bool
    {
        // We use a custom blade view instead of infolist
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Order'),

            Actions\Action::make('see_tenant')
                ->label('See Tenant')
                ->icon('heroicon-o-building-office')
                ->url(fn ($record) => $record->tenant
                    ? \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $record->tenant])
                    : null
                )
                ->visible(fn ($record) => $record->tenant !== null),

            Actions\Action::make('see_customer')
                ->label('See Customer')
                ->icon('heroicon-o-user')
                ->url(fn ($record) => $record->customer
                    ? \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $record->customer])
                    : null
                )
                ->visible(fn ($record) => $record->customer !== null),

            Actions\Action::make('see_event')
                ->label('See Event')
                ->icon('heroicon-o-calendar')
                ->url(function ($record) {
                    $firstTicket = $record->tickets()->with('ticketType.event')->first();
                    if ($firstTicket && $firstTicket->ticketType && $firstTicket->ticketType->event) {
                        return \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $firstTicket->ticketType->event]);
                    }
                    return null;
                })
                ->visible(function ($record) {
                    $firstTicket = $record->tickets()->with('ticketType.event')->first();
                    return $firstTicket && $firstTicket->ticketType && $firstTicket->ticketType->event;
                }),
        ];
    }
}
