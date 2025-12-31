<?php

namespace App\Filament\Tenant\Resources\Tracking\EventFunnelResource\Pages;

use App\Filament\Tenant\Resources\Tracking\EventFunnelResource;
use Filament\Resources\Pages\ListRecords;

class ListEventFunnels extends ListRecords
{
    protected static string $resource = EventFunnelResource::class;
}
