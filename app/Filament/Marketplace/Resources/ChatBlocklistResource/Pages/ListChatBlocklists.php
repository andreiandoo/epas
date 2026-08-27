<?php

namespace App\Filament\Marketplace\Resources\ChatBlocklistResource\Pages;

use App\Filament\Marketplace\Resources\ChatBlocklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatBlocklists extends ListRecords
{
    protected static string $resource = ChatBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
