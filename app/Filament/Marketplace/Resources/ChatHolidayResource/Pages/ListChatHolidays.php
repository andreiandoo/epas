<?php

namespace App\Filament\Marketplace\Resources\ChatHolidayResource\Pages;

use App\Filament\Marketplace\Resources\ChatHolidayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChatHolidays extends ListRecords
{
    protected static string $resource = ChatHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
