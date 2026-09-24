<x-filament-panels::page>
    @include('filament.marketplace.manual.assets')

    <div class="epm-page">
        <div class="epm-page-top">
            <p class="epm-lead">{{ $manual['description'] }}</p>
            <input type="search" class="epm-search" data-epm-search placeholder="Caută: reducere, sold out, comision, serie…" aria-label="Caută în manual" autocomplete="off">
        </div>

        <div class="epm-page-grid">
            <nav class="epm-toc" aria-label="Cuprins">
                @include('filament.marketplace.manual.toc', ['manual' => $manual])
            </nav>
            <div class="epm-content" data-epm-content>
                @include('filament.marketplace.manual.content', ['manual' => $manual])
            </div>
        </div>
    </div>
</x-filament-panels::page>
