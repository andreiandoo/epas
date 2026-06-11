<?php

namespace App\Filament\Tenant\Resources\MerchandiseItemResource\Pages;

use App\Filament\Tenant\Resources\MerchandiseItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseItems extends ListRecords
{
    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('bulk_add')
                ->label('Adaugare in bulk')
                ->icon('heroicon-o-squares-plus')
                ->color('info')
                ->url('/tenant/merchandise-items/bulk-add'),
        ];
    }
}
