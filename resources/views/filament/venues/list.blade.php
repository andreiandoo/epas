<x-filament-panels::page>
    <div class="ep-page-title">Venues</div>
    <div class="ep-page-sub">Toate locațiile din catalog. Poți filtra și sorta după nevoi.</div>

    <div class="ep-card" style="display:grid; grid-template-columns: 320px 1fr; gap: 16px; padding:16px;">
        {{-- Coloana stânga: filtre rapide / meta --}}
        <aside class="ep-card ep-card--p">
            <div class="ep-card-title" style="margin-bottom:8px;">Filtre rapide</div>

            {{-- Slot nativ Filament pentru filters form (dacă ai definit filtre pe tabel) --}}
            @if (filled($this->table->getFilters()))
                <div style="margin-top:8px;">
                    {{ $this->table->getFiltersForm() }}
                </div>
                <div style="height:1px;background:var(--border);margin:12px 0;"></div>
            @endif

            <div class="ep-card-title" style="margin-bottom:8px;">Sfat</div>
            <div style="color:var(--muted); font-size:14px;">
                Folosește căutarea din capul tabelului pentru a identifica rapid un venue.
            </div>
        </aside>

        {{-- Coloana dreapta: tabelul Filament --}}
        <section class="ep-card ep-card--p">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
