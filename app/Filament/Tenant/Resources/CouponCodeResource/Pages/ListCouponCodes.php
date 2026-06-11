<?php

namespace App\Filament\Tenant\Resources\CouponCodeResource\Pages;

use App\Filament\Tenant\Resources\CouponCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCouponCodes extends ListRecords
{
    protected static string $resource = CouponCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
