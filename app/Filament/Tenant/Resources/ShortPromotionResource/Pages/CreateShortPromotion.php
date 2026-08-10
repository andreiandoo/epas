<?php

namespace App\Filament\Tenant\Resources\ShortPromotionResource\Pages;

use App\Filament\Tenant\Resources\ShortPromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShortPromotion extends CreateRecord
{
    protected static string $resource = ShortPromotionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ShortPromotionResource::stampOwnership($data);
    }
}
