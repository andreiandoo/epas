<?php

namespace App\Filament\Marketplace\Resources\CouponCodeResource\Pages;

use App\Filament\Marketplace\Resources\CouponCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCouponCode extends EditRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = CouponCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
