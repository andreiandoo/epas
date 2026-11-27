<?php

namespace App\Filament\Marketplace\Resources\ChatCannedResponseResource\Pages;

use App\Filament\Marketplace\Resources\ChatCannedResponseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatCannedResponse extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ChatCannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
