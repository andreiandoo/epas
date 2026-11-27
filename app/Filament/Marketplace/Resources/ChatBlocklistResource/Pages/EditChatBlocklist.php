<?php

namespace App\Filament\Marketplace\Resources\ChatBlocklistResource\Pages;

use App\Filament\Marketplace\Resources\ChatBlocklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatBlocklist extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = ChatBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
