<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Filament\Marketplace\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Adaugă venue owner'),
        ];
    }
}
