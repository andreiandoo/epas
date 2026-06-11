<?php

namespace App\Filament\Tenant\Resources\FestivalEditionResource\Pages;

use App\Filament\Tenant\Resources\FestivalEditionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateFestivalEdition extends CreateRecord
{
    protected static string $resource = FestivalEditionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
