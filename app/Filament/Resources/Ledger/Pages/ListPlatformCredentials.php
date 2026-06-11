<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\PlatformCredentialResource;
use Filament\Resources\Pages\ListRecords;

class ListPlatformCredentials extends ListRecords
{
    protected static string $resource = PlatformCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
