<?php

namespace App\Filament\Marketplace\Resources\PartnerArtistResource\Pages;

use App\Filament\Marketplace\Resources\PartnerArtistResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerArtist extends EditRecord
{
    protected static string $resource = PartnerArtistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
