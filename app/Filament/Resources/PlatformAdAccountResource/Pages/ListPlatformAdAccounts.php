<?php

namespace App\Filament\Resources\PlatformAdAccountResource\Pages;

use App\Filament\Resources\PlatformAdAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAdAccounts extends ListRecords
{
    protected static string $resource = PlatformAdAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
