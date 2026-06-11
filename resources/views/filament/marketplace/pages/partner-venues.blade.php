<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <x-heroicon-o-building-library class="w-8 h-8 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-primary-900 dark:text-primary-100">
                        Locații Partenere
                    </h3>
                    <p class="mt-1 text-sm text-primary-700 dark:text-primary-300">
                        Aici poți selecta locații existente din baza de date și le poți adăuga ca parteneri pentru marketplace-ul tău.
                        Locațiile partenere vor fi disponibile pentru evenimentele tale.
                    </p>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full dark:bg-green-900/50 dark:text-green-300">
                            <x-heroicon-s-check-badge class="w-4 h-4" />
                            Partener = locație selectată din baza de date
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900/50 dark:text-blue-300">
                            <x-heroicon-s-plus-circle class="w-4 h-4" />
                            Pentru locații noi, folosește "Adaugă locație nouă"
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
