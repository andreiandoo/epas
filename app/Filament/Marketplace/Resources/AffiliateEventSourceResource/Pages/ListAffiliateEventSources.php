<?php

namespace App\Filament\Marketplace\Resources\AffiliateEventSourceResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateEventSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateEventSources extends ListRecords
{
    protected static string $resource = AffiliateEventSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
