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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove virtual fields that shouldn't be persisted
        unset(
            $data['page1_source_mode'],
            $data['page2_source_mode'],
            $data['html_content_source'],
            $data['html_content_page_2_source'],
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
