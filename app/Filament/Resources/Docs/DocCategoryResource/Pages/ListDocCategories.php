<?php

namespace App\Filament\Resources\Docs\DocCategoryResource\Pages;

use App\Filament\Resources\Docs\DocCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocCategories extends ListRecords
{
    protected static string $resource = DocCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
