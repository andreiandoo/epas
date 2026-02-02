<?php

namespace App\Filament\Marketplace\Resources\GroupBookingResource\Pages;

use App\Filament\Marketplace\Resources\GroupBookingResource;
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
