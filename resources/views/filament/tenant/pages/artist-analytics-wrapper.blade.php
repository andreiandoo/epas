<x-filament-panels::page>
    @php
        $tenantEditUrl = url('/tenant/artist-profile');
    @endphp
    <style>
        /* Force hide page header */
        .fi-page > .fi-header,
        .fi-page-header-ctn,
        header.fi-header { display: none !important; }
        /* Full-width analytics dashboard */
        .db { max-width: 100% !important; width: 100% !important; }
    </style>
    @include('filament.artists.pages.view-artist', ['tenantEditUrl' => $tenantEditUrl])
</x-filament-panels::page>
