<x-filament-panels::page>
    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-4">
        Cifrele din listă se actualizează la fiecare plată și se recalculează complet în fiecare noapte. Pagina fiecărui organizator arată valorile live (inclusiv vânzările de azi), deci pot diferi ușor.
    </p>

    @if ($this->legacyEnabled())
        @php($legacyQueue = $this->legacyQueueCount())
        <div class="ep-legacy-bar" @if ($legacyQueue > 0) wire:poll.10s @endif>
            <div class="ep-legacy-bar__text">
                <strong>Soldurile din trecut</strong> — deconturile rămase deschise pe evenimentele încheiate până pe
                {{ \Carbon\Carbon::parse(\App\Services\Marketplace\LegacySettlementService::CUTOFF_DATE)->format('d.m.Y') }}.
                Analizează, apoi rezolvă din coloana „Trecut”.
                @if ($legacyQueue > 0)
                    <span class="ep-legacy-bar__busy">În lucru în fundal: {{ $legacyQueue }} organizatori.</span>
                @endif
            </div>
            {{ $this->legacyAnalyzeAllAction }}
        </div>
        <style>
            .ep-legacy-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-radius: .75rem; border: 1px solid rgba(3, 7, 18, .08); background: #fff; }
            .dark .ep-legacy-bar { background: rgba(255, 255, 255, .04); border-color: rgba(255, 255, 255, .1); }
            .ep-legacy-bar__text { font-size: .8125rem; color: #4b5563; flex: 1 1 20rem; }
            .dark .ep-legacy-bar__text { color: #d1d5db; }
            .ep-legacy-bar__busy { display: inline-block; margin-left: .25rem; color: #d97706; font-weight: 600; }
        </style>
    @endif

    <x-filament::tabs label="Filtre rapide">
        @foreach($this->quickFilters() as $key => $filter)
            <x-filament::tabs.item
                :active="$quick === $key"
                :badge="$filter['count']"
                wire:click="$set('quick', '{{ $key }}')"
            >
                {{ $filter['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
