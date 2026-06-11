<?php

namespace App\Filament\Tenant\Resources\GroupBookingResource\Pages;

use App\Filament\Tenant\Resources\GroupBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGroupBooking extends EditRecord
{
    protected static string $resource = GroupBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
