<?php

namespace App\Filament\Marketplace\Resources\TicketTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TicketTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketTemplates extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
