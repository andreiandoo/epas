<?php

namespace App\Filament\Resources\Costs\Pages;

use App\Filament\Resources\Costs\PlatformCostResource;
use Filament\Resources\Pages\EditRecord;

class EditPlatformCost extends EditRecord
{
    protected static string $resource = PlatformCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
