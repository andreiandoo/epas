<?php

namespace App\Filament\Marketplace\Resources\TicketTypeResource\Pages;

use App\Filament\Marketplace\Resources\TicketTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketTypes extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = TicketTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
