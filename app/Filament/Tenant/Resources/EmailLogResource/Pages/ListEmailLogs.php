<?php

namespace App\Filament\Tenant\Resources\EmailLogResource\Pages;

use App\Filament\Tenant\Resources\EmailLogResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailLogs extends ListRecords
{
    protected static string $resource = EmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - logs are automatic
        ];
    }
}
