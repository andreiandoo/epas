<?php

namespace App\Filament\Marketplace\Resources\ChatOperatorScheduleResource\Pages;

use App\Filament\Marketplace\Resources\ChatOperatorScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChatOperatorSchedule extends EditRecord
{
    protected static string $resource = ChatOperatorScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
