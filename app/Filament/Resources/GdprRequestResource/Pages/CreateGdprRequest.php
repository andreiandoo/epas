<?php

namespace App\Filament\Resources\GdprRequestResource\Pages;

use App\Filament\Resources\GdprRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGdprRequest extends CreateRecord
{
    protected static string $resource = GdprRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_at'] = now();
        $data['status'] = 'pending';
        return $data;
    }
}
