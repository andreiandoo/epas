<?php

namespace App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource\Pages;

use App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventFlexiblePaymentConfig extends CreateRecord
{
    protected static string $resource = EventFlexiblePaymentConfigResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['plan_type'] ?? null)) { unset($data['plan_type']); }
        return $data;
    }
}
