<?php

namespace App\Filament\Resources\Gamification\BadgeResource\Pages;

use App\Filament\Resources\Gamification\BadgeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBadges extends ListRecords
{
    protected static string $resource = BadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
