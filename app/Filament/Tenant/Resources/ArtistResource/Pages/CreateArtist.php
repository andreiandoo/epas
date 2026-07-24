<?php

namespace App\Filament\Tenant\Resources\ArtistResource\Pages;

use App\Filament\Tenant\Resources\ArtistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArtist extends CreateRecord
{
    protected static string $resource = ArtistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        return $data;
    }
}
