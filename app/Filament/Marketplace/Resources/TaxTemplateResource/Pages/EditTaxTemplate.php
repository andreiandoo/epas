<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TaxTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxTemplate extends EditRecord
{
    protected static string $resource = TaxTemplateResource::class;

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
