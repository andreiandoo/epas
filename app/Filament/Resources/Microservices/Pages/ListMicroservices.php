<?php

namespace App\Filament\Resources\Microservices\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use Filament\Resources\Pages\ListRecords;

class ListMicroservices extends ListRecords
{
    protected static string $resource = MicroserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
