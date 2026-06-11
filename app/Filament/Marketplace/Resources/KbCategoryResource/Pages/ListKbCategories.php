<?php

namespace App\Filament\Marketplace\Resources\KbCategoryResource\Pages;

use App\Filament\Marketplace\Resources\KbCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKbCategories extends ListRecords
{
    protected static string $resource = KbCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
