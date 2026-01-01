<?php

namespace App\Filament\Marketplace\Resources\CouponCodeResource\Pages;

use App\Filament\Marketplace\Resources\CouponCodeResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class CreateCouponCode extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = CouponCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['marketplace_client_id'] = static::getMarketplaceClient()?->id;
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
