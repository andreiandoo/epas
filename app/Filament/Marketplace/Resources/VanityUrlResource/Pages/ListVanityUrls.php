<?php

namespace App\Filament\Marketplace\Resources\VanityUrlResource\Pages;

use App\Filament\Marketplace\Resources\VanityUrlResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVanityUrls extends ListRecords
{
    protected static string $resource = VanityUrlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
