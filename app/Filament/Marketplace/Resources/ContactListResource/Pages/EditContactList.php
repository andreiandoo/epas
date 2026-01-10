<?php

namespace App\Filament\Marketplace\Resources\ContactListResource\Pages;

use App\Filament\Marketplace\Resources\ContactListResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactList extends EditRecord
{
    protected static string $resource = ContactListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_subscribers')
                ->label('Sync Subscribers')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sync Subscribers')
                ->modalDescription('This will add all customers matching the current rules to this list. Existing subscribers will not be removed.')
                ->visible(fn () => $this->record->isDynamic())
                ->action(function () {
                    $added = $this->record->syncSubscribers();
                    \Filament\Notifications\Notification::make()
                        ->title('Sync Complete')
                        ->body("{$added} new subscribers added to the list.")
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Handle adding subscribers from the form
        $data = $this->form->getState();

        if (!empty($data['add_subscribers'])) {
            foreach ($data['add_subscribers'] as $customerId) {
                $this->record->addSubscriber($customerId);
            }
        }

        // If dynamic list, sync after save
        if ($this->record->isDynamic()) {
            $this->record->syncSubscribers();
        }
    }
}
