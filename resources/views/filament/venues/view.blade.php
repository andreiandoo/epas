<x-filament-panels::page>
    <div class="ep-page-title">Venue Details</div>
    <div class="ep-page-sub">Informații și statistici pentru locație.</div>

    {{-- Header widgets (așa cum le-ai definit în ViewVenue::getHeaderWidgets) --}}
    @if (method_exists($this, 'getHeaderWidgets') && count($this->getHeaderWidgets()) > 0)
        <div class="ep-card ep-card--p" style="margin-bottom:16px;">
            <div class="grid gap-6 lg:grid-cols-3">
                @foreach ($this->getHeaderWidgets() as $widget)
                    @livewire($widget, ['lazy' => true])
                @endforeach
            </div>
        </div>
    @endif

    {{-- Infolist-ul paginii (dacă e definit în Resource/Page) --}}
    @if (method_exists($this, 'getInfolist') || isset($this->infolist))
        <div class="ep-card ep-card--p">
            {{ $this->infolist ?? '' }}
        </div>
    @endif
</x-filament-panels::page>
