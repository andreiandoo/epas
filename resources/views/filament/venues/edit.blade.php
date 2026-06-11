<x-filament-panels::page>
    <div class="ep-page-title">Edit Venue</div>
    <div class="ep-page-sub">Actualizează informațiile. Acțiunile de salvare rămân „sticky”.</div>

    <div class="ep-card" style="display:grid; grid-template-columns: 320px 1fr; gap: 16px; padding:16px;">
        <aside class="ep-card ep-card--p">
            <div class="ep-card-title" style="margin-bottom:8px;">Status</div>
            <div class="ep-chip">ID: {{ $this->record->getKey() }}</div>
            <div style="height:1px;background:var(--border);margin:12px 0;"></div>
            <div class="ep-card-title" style="margin-bottom:8px;">Ajutor</div>
            <div style="color:var(--muted); font-size:14px;">
                Secțiunile de formular au ancore sus pentru navigare rapidă.
            </div>
        </aside>

        <section class="ep-card ep-card--p">
            <form wire:submit="save">
                {{ $this->form }}

                <div style="height:16px;"></div>
                <div class="fi-sc fi-grid">
                    <div>
                        <x-filament::button type="submit">Save changes</x-filament::button>
                        <x-filament::button color="gray" type="button" wire:click="cancel">Cancel</x-filament::button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
