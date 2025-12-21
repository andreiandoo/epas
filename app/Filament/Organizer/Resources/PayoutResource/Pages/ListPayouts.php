<?php

namespace App\Filament\Organizer\Resources\PayoutResource\Pages;

use App\Filament\Organizer\Resources\PayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
