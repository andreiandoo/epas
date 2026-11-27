<?php

namespace App\Filament\Marketplace\Resources\RegionResource\Pages;

use App\Filament\Marketplace\Resources\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegions extends ListRecords
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
