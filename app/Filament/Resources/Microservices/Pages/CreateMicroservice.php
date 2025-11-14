<?php

namespace App\Filament\Resources\Microservices\Pages;

use App\Filament\Resources\Microservices\MicroserviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMicroservice extends CreateRecord
{
    protected static string $resource = MicroserviceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
