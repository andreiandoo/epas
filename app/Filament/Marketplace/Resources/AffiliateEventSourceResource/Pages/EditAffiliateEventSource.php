<?php

namespace App\Filament\Marketplace\Resources\AffiliateEventSourceResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateEventSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateEventSource extends EditRecord
{
    protected static string $resource = AffiliateEventSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
