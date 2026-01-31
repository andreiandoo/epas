<?php

namespace App\Filament\Resources\CoreCustomerResource\Pages;

use App\Filament\Resources\CoreCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCoreCustomer extends ViewRecord
{
    protected static string $resource = CoreCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit Tags'),
        ];
    }
}
