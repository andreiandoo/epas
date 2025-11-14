<x-filament-panels::page>
    <div class="ep-page-title">Add Venue</div>
    <div class="ep-page-sub">Completează câmpurile de mai jos, apoi salvează.</div>

    <div class="ep-card" style="display:grid; grid-template-columns: 320px 1fr; gap: 16px; padding:16px;">
        {{-- Stânga: meta/ajutor --}}
        <aside class="ep-card ep-card--p">
            <div class="ep-card-title" style="margin-bottom:8px;">Detalii utile</div>
            <div style="color:var(--muted); font-size:14px;">
                Numele și adresa sunt suficiente pentru început. Poți reveni oricând cu detalii.
            </div>
        </aside>

        {{-- Dreapta: form --}}
        <section class="ep-card ep-card--p">
            <x-filament-panels::form wire:submit="create">
                {{ $this->form }}

                <div style="height:16px;"></div>
                <div class="fi-sc fi-grid">
                    <div>
                        <x-filament::button type="submit">Create Venue</x-filament::button>
                        <x-filament::button color="gray" type="button" wire:click="cancel">Cancel</x-filiment::button>
                    </div>
                </div>
            </x-filament-panels::form>
        </section>
    </div>
</x-filament-panels::page>
