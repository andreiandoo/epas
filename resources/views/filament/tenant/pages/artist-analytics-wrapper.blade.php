<x-filament-panels::page>
    @php
        $tenantEditUrl = url('/tenant/artist-profile');
    @endphp
    <style>
        /* Hide redundant page header on analytics */
        .fi-page > .fi-header { display: none !important; }
        /* Full-width analytics dashboard */
        .db { max-width: 100% !important; width: 100% !important; }
        .db-header-inner { max-width: 100% !important; padding: 12px 0 !important; }
        /* Hide Refresh and Edit buttons */
        .db-header-inner > div:last-child { display: none !important; }
    </style>
    @include('filament.artists.pages.view-artist', ['tenantEditUrl' => $tenantEditUrl])
</x-filament-panels::page>
