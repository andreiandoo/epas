<x-filament-panels::page>
    @php
        // Override the edit URL to point to tenant artist-profile instead of admin route
        // The admin template at line 176 calls ArtistResource::getUrl('edit', ...) which doesn't exist in tenant panel
        $tenantEditUrl = url('/tenant/artist-profile');
    @endphp
    @include('filament.artists.pages.view-artist', ['tenantEditUrl' => $tenantEditUrl])
</x-filament-panels::page>
