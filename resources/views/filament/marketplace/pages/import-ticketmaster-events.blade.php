<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Search Form --}}
        <form wire:submit.prevent="preview">
            {{ $this->form }}

            <div class="mt-6 flex gap-3">
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    Caută evenimente
                </x-filament::button>

                @if($this->previewEvents && count($this->previewEvents) > 0)
                    <x-filament::button wire:click="import" color="success" icon="heroicon-o-cloud-arrow-down">
                        Importă {{ count($this->previewEvents) }} evenimente
                    </x-filament::button>
                @endif
            </div>
        </form>

        {{-- Import Result --}}
        @if($this->importResult)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Rezultat Import</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-4 rounded-lg bg-success-50 dark:bg-success-900/20">
                        <div class="text-3xl font-bold text-success-600 dark:text-success-400">{{ $this->importResult['imported'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Importate</div>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20">
                        <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $this->importResult['skipped'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Omise (duplicate)</div>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-gray-50 dark:bg-gray-900/20">
                        <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">{{ $this->importResult['total_available'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total disponibile</div>
                    </div>
                </div>

                @if(!empty($this->importResult['errors']))
                    <div class="mt-4 p-4 rounded-lg bg-danger-50 dark:bg-danger-900/20">
                        <h4 class="font-medium text-danger-600 dark:text-danger-400 mb-2">Erori ({{ count($this->importResult['errors']) }})</h4>
                        <ul class="list-disc pl-5 text-sm text-danger-500 space-y-1">
                            @foreach($this->importResult['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{-- Preview Table --}}
        @if($this->previewEvents && count($this->previewEvents) > 0)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Previzualizare - {{ count($this->previewEvents) }} evenimente găsite
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Verifică evenimentele și apasă "Importă" pentru a le adăuga în marketplace. Evenimentele deja importate vor fi omise automat.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Eveniment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Locație</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Categorie</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Preț</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Link</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->previewEvents as $event)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @if($event['image'])
                                                <img src="{{ $event['image'] }}" alt="" class="w-12 h-8 rounded object-cover flex-shrink-0">
                                            @endif
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $event['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                        {{ $event['date'] }}
                                        @if($event['time'])
                                            <span class="text-gray-400">{{ substr($event['time'], 0, 5) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                        {{ $event['venue'] }}
                                        @if($event['city'])
                                            <span class="text-gray-400 text-xs block">{{ $event['city'] }}{{ $event['country'] ? ', ' . $event['country'] : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400">
                                            {{ $event['category'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                        @if($event['price_min'])
                                            {{ number_format($event['price_min'], 0) }}
                                            @if($event['price_max'] && $event['price_max'] != $event['price_min'])
                                                - {{ number_format($event['price_max'], 0) }}
                                            @endif
                                            {{ $event['currency'] }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($event['url'])
                                            <a href="{{ $event['url'] }}" target="_blank" class="text-primary-500 hover:text-primary-600">
                                                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif($this->isPreview)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 text-center">
                <x-heroicon-o-magnifying-glass class="w-12 h-12 text-gray-400 mx-auto mb-3" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Niciun eveniment găsit</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Încearcă cu alte filtre sau un alt cuvânt cheie.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
