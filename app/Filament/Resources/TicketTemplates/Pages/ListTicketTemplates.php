<?php

namespace App\Filament\Resources\TicketTemplates\Pages;

use App\Filament\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketTemplates extends ListRecords
{
    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
