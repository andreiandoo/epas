<?php

namespace App\Filament\Marketplace\Resources\TaxRegistryResource\Pages;

use App\Filament\Marketplace\Resources\TaxRegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxRegistry extends EditRecord
{
    protected static string $resource = TaxRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
