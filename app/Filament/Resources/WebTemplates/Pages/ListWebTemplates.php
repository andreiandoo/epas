<?php

namespace App\Filament\Resources\WebTemplates\Pages;

use App\Filament\Resources\WebTemplates\WebTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListWebTemplates extends ListRecords
{
    protected static string $resource = WebTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Template Nou'),
        ];
    }
}
