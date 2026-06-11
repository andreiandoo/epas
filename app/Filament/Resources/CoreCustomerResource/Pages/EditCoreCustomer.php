<?php

namespace App\Filament\Resources\CoreCustomerResource\Pages;

use App\Filament\Resources\CoreCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoreCustomer extends EditRecord
{
    protected static string $resource = CoreCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
