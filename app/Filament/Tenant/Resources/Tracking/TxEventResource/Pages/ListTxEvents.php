<?php

namespace App\Filament\Tenant\Resources\Tracking\TxEventResource\Pages;

use App\Filament\Tenant\Resources\Tracking\TxEventResource;
use Filament\Resources\Pages\ListRecords;

class ListTxEvents extends ListRecords
{
    protected static string $resource = TxEventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            // Could add stats widgets here
        ];
    }
}
