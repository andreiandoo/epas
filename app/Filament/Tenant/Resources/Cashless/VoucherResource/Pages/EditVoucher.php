<?php

namespace App\Filament\Tenant\Resources\Cashless\VoucherResource\Pages;

use App\Filament\Tenant\Resources\Cashless\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
