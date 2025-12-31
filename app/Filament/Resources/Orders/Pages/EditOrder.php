<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View Order'),

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

            Actions\DeleteAction::make(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        // Only super-admin and admin can edit Orders in Core app
        $user = auth()->user();
        if (!$user || (!$user->isSuperAdmin() && !$user->isAdmin())) {
            Notification::make()
                ->title('Unauthorized')
                ->body('Only Super Admin and Admin users can edit orders in the Core application.')
                ->danger()
                ->send();

            $this->redirect(OrderResource::getUrl('index'));
        }
    }

    protected function beforeSave(): void
    {
        // TODO: Implement "sync to tenant system" functionality when tenant websites are ready
        // For now, just log that changes were made in Core
        \Log::info('Order edited in Core app', [
            'order_id' => $this->record->id,
            'edited_by' => auth()->id(),
        ]);
    }
}
