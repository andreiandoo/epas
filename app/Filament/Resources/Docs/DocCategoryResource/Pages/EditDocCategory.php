<?php

namespace App\Filament\Resources\Docs\DocCategoryResource\Pages;

use App\Filament\Resources\Docs\DocCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocCategory extends EditRecord
{
    protected static string $resource = DocCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
