<?php

namespace App\Filament\Resources\Shorts\ShortAdvertiserResource\Pages;

use App\Filament\Resources\Shorts\ShortAdvertiserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShortAdvertisers extends ListRecords
{
    protected static string $resource = ShortAdvertiserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
