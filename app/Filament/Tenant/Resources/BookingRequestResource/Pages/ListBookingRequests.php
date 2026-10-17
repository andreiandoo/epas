<?php

namespace App\Filament\Tenant\Resources\BookingRequestResource\Pages;

use App\Filament\Tenant\Resources\BookingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingRequests extends ListRecords
{
    protected static string $resource = BookingRequestResource::class;
}
