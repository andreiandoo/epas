<?php

namespace App\Filament\Marketplace\Resources\ActivityBookingResource\Pages;

use App\Filament\Marketplace\Resources\ActivityBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityBookings extends ListRecords
{
    protected static string $resource = ActivityBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
