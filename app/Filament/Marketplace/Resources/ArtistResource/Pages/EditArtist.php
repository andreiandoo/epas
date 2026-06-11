<?php

namespace App\Filament\Marketplace\Resources\ArtistResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ArtistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArtist extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = ArtistResource::class;

    protected bool $shouldClose = false;

    /**
     * Resolve the record without applying the country filter from getEloquentQuery().
     * Only enforce that the artist is actually a partner of the current marketplace.
     */
    public function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        $marketplace = static::getMarketplaceClient();

        $artist = \App\Models\Artist::whereHas(
            'marketplaceClients',
            fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $marketplace?->id)
        )->find($key);

        if (!$artist) {
            abort(404);
        }

        return $artist;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $marketplace = static::getMarketplaceClient();
        if ($marketplace) {
            $pivot = $this->record->marketplaceClients()
                ->where('marketplace_artist_partners.marketplace_client_id', $marketplace->id)
                ->first();
            $data['partner_notes'] = $pivot?->pivot?->partner_notes;
        }
        return $data;
    }

    protected function afterSave(): void
    {
        $marketplace = static::getMarketplaceClient();
        if ($marketplace) {
            $this->record->marketplaceClients()->syncWithoutDetaching([
                $marketplace->id => [
                    'is_partner' => true,
                    'partner_notes' => $this->data['partner_notes'] ?? null,
                ],
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('saveAndClose')
                ->label('Salvează și închide')
                ->action(function () {
                    $this->shouldClose = true;
                    $this->save();
                })
                ->color('gray')
                ->icon('heroicon-o-check'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->shouldClose) {
            return $this->getResource()::getUrl('index');
        }

        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
