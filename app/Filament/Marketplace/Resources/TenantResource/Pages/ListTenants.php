<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Filament\Marketplace\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Adaugă venue owner')),
        ];
    }
}
