<?php

namespace App\Filament\Marketplace\Resources\VaultEntryResource\Pages;

use App\Filament\Marketplace\Resources\VaultEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVaultEntries extends ListRecords
{
    protected static string $resource = VaultEntryResource::class;

    public function getTitle(): string
    {
        return 'Seif';
    }

    public function getSubheading(): ?string
    {
        return 'Date de acces sensibile. Vezi doar intrările la care ai primit acces.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Adaugă intrare')
                ->icon('heroicon-o-plus'),
        ];
    }
}
