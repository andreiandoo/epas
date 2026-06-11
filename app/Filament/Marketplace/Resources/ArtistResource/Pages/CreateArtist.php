<?php

namespace App\Filament\Marketplace\Resources\ArtistResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ArtistResource;
use App\Models\Artist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateArtist extends CreateRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ArtistResource::class;

    /**
     * Add an existing artist as partner to the current marketplace.
     * Called via wire:click from the existing artists search placeholder.
     */
    public function addArtistAsPartner(int $artistId): void
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return;

        $artist = Artist::find($artistId);
        if (!$artist) return;

        $artist->marketplaceClients()->syncWithoutDetaching([
            $marketplace->id => ['is_partner' => true],
        ]);

        Notification::make()
            ->success()
            ->title(e($artist->name) . ' adăugat ca partener!')
            ->send();

        $this->redirect(ArtistResource::getUrl('edit', ['record' => $artist]));
    }

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
