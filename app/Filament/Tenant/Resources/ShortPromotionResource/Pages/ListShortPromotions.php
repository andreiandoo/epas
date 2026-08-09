<?php

namespace App\Filament\Tenant\Resources\ShortPromotionResource\Pages;

use App\Filament\Tenant\Resources\ShortPromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShortPromotions extends ListRecords
{
    protected static string $resource = ShortPromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Promovează un short'),
        ];
    }
}
