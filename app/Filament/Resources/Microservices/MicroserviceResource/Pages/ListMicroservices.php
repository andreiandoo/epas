<?php

namespace App\Filament\Resources\Microservices\MicroserviceResource\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMicroservices extends ListRecords
{
    protected static string $resource = MicroserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
