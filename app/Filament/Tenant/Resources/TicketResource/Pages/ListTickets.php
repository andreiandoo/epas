<?php

namespace App\Filament\Tenant\Resources\TicketResource\Pages;

use App\Filament\Tenant\Resources\TicketResource;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;
}
