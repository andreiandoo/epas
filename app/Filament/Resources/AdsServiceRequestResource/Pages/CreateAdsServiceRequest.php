<?php

namespace App\Filament\Resources\AdsServiceRequestResource\Pages;

use App\Filament\Resources\AdsServiceRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdsServiceRequest extends CreateRecord
{
    protected static string $resource = AdsServiceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}
