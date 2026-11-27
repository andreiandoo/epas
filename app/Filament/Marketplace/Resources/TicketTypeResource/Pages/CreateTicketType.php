<?php

namespace App\Filament\Marketplace\Resources\TicketTypeResource\Pages;

use App\Filament\Marketplace\Resources\TicketTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketType extends CreateRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = TicketTypeResource::class;
}
