<?php

namespace App\Filament\Marketplace\Resources\ArtistResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ArtistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArtist extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ArtistResource::class;

    protected function afterCreate(): void
    {
        $marketplace = static::getMarketplaceClient();
        if ($marketplace) {
            $this->record->marketplaceClients()->syncWithoutDetaching([
                $marketplace->id => ['is_partner' => true],
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
