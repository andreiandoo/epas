<?php

namespace App\Filament\Resources\WebTemplates\Pages;

use App\Filament\Resources\WebTemplates\WebTemplateCustomizationResource;
use Filament\Resources\Pages\ListRecords;

class ListWebTemplateCustomizations extends ListRecords
{
    protected static string $resource = WebTemplateCustomizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Personalizare Nouă'),
        ];
    }
}
