<x-filament-panels::page>
    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-4">
        Cifrele din listă se actualizează la fiecare plată și se recalculează complet în fiecare noapte. Pagina fiecărui organizator arată valorile live (inclusiv vânzările de azi), deci pot diferi ușor.
    </p>

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
