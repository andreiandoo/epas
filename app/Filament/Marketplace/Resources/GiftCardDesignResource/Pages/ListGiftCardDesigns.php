<?php

namespace App\Filament\Marketplace\Resources\GiftCardDesignResource\Pages;

use App\Filament\Marketplace\Resources\GiftCardDesignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGiftCardDesigns extends ListRecords
{
    protected static string $resource = GiftCardDesignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
