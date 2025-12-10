<?php

namespace App\Filament\Tenant\Resources\GroupBookingResource\Pages;

use App\Filament\Tenant\Resources\GroupBookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGroupBooking extends CreateRecord
{
    protected static string $resource = GroupBookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;

        return $data;
    }
}
