<?php

namespace App\Filament\Marketplace\Resources\VaultEntryResource\Pages;

use App\Filament\Marketplace\Resources\VaultEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVaultEntry extends ViewRecord
{
    protected static string $resource = VaultEntryResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
