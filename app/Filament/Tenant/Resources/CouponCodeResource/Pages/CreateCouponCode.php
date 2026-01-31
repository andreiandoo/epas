<?php

namespace App\Filament\Tenant\Resources\CouponCodeResource\Pages;

use App\Filament\Tenant\Resources\CouponCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCouponCode extends CreateRecord
{
    protected static string $resource = CouponCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
