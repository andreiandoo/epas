<?php

namespace App\Filament\Tenant\Resources\GroupBookingResource\Pages;

use App\Filament\Tenant\Resources\GroupBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGroupBookings extends ListRecords
{
    protected static string $resource = GroupBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
