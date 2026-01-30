<?php

namespace App\Filament\Marketplace\Resources\KbArticleResource\Pages;

use App\Filament\Marketplace\Resources\KbArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKbArticle extends EditRecord
{
    protected static string $resource = KbArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
