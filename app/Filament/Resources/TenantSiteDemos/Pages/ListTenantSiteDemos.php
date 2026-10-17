<?php

namespace App\Filament\Resources\TenantSiteDemos\Pages;

use App\Filament\Resources\TenantSiteDemos\TenantSiteDemoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantSiteDemos extends ListRecords
{
    protected static string $resource = TenantSiteDemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
