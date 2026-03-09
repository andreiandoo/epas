<?php

namespace App\Filament\Tenant\Resources\MerchandiseItemResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseItems extends ListRecords
{
    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
