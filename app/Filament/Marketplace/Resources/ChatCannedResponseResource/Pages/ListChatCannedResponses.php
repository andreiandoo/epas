<?php

namespace App\Filament\Marketplace\Resources\ChatCannedResponseResource\Pages;

use App\Filament\Marketplace\Resources\ChatCannedResponseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatCannedResponses extends ListRecords
{
    protected static string $resource = ChatCannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
