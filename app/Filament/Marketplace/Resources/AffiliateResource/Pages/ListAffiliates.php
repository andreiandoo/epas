<?php

namespace App\Filament\Marketplace\Resources\AffiliateResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAffiliates extends ListRecords
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
