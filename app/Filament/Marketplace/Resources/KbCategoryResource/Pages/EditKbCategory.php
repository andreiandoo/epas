<?php

namespace App\Filament\Marketplace\Resources\KbCategoryResource\Pages;

use App\Filament\Marketplace\Resources\KbCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKbCategory extends EditRecord
{
    protected static string $resource = KbCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
