<?php

namespace App\Filament\Marketplace\Resources\TaxTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TaxTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxTemplates extends ListRecords
{
    protected static string $resource = TaxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
