<?php

namespace App\Filament\Tenant\Resources\TenantTaxRegistryResource\Pages;

use App\Filament\Tenant\Resources\TenantTaxRegistryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantTaxRegistry extends EditRecord
{
    protected static string $resource = TenantTaxRegistryResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
