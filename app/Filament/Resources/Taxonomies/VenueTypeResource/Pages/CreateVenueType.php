<?php

namespace App\Filament\Resources\Taxonomies\VenueTypeResource\Pages;

use App\Filament\Resources\Taxonomies\VenueTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateVenueType extends CreateRecord
{
    protected static string $resource = VenueTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate slug from English name if not provided
        if (empty($data['slug']) && !empty($data['name']['en'])) {
            $data['slug'] = Str::slug($data['name']['en']);
        }

        return $data;
    }
}
