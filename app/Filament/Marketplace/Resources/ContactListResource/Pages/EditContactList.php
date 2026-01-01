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
    }
}
