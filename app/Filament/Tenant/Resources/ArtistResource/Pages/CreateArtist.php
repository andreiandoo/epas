<?php

namespace App\Filament\Tenant\Resources\ArtistResource\Pages;

use App\Filament\Tenant\Resources\ArtistResource;
use App\Models\Artist;
use App\Models\TenantArtist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateArtist extends CreateRecord
{
    protected static string $resource = ArtistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant?->id;
        return $data;
    }

    /**
     * Importă un artist din biblioteca globală (core Artist) în roster-ul
     * tenantului, preluând nume, slug, foto și biografie. Declanșat via
     * wire:click din placeholder-ul de căutare din formularul de creare.
     */
    public function importFromLibrary(int $artistId): void
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return;
        }

        $artist = Artist::find($artistId);
        if (!$artist) {
            Notification::make()->danger()->title('Artist inexistent în bibliotecă.')->send();
            return;
        }

        // Slug unic per tenant
        $base = $artist->slug ?: Str::slug($artist->name);
        $slug = $base;
        $i = 2;
        while (TenantArtist::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        // Biografie: core folosește bio_html (translatable), tenant folosește bio (array)
        $bio = null;
        $rawBio = $artist->getRawOriginal('bio_html');
        if ($rawBio) {
            $decoded = json_decode($rawBio, true);
            $bio = is_array($decoded) ? $decoded : ['ro' => (string) $rawBio];
        }

        $tenantArtist = TenantArtist::create([
            'tenant_id'  => $tenant->id,
            'artist_id'  => $artist->id,
            'name'       => $artist->name,
            'slug'       => $slug,
            'bio'        => $bio,
            'photo_url'  => $artist->main_image_url ?: $artist->portrait_url,
            'email'      => $artist->email,
            'phone'      => $artist->phone,
            'is_resident' => true,
            'status'     => 'active',
        ]);

        Notification::make()
            ->success()
            ->title(e($artist->name) . ' importat din bibliotecă!')
            ->send();

        $this->redirect(ArtistResource::getUrl('edit', ['record' => $tenantArtist]));
    }
}
