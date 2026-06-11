<x-filament-panels::page>
    {{-- Import Form (hidden during import) --}}
    @if(!$isImporting)
        <form wire:submit="import">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="import">Import Artists</span>
                    <span wire:loading wire:target="import">Se parsează CSV...</span>
                </x-filament::button>
            </div>
        </form>
    @endif

    {{-- Live Progress --}}
    @if($isImporting)
        <div wire:poll.500ms="processNextBatch">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Import în curs...
                    </div>
                </x-slot>

                {{-- Counter --}}
                <div class="flex items-center justify-between mb-3">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="text-2xl font-bold text-primary">{{ $importProgress }}</span>
                        <span class="text-lg text-gray-400"> / {{ $importTotal }}</span>
                    </div>
                    <div class="text-sm font-medium text-gray-500">
                        {{ $importTotal > 0 ? round(($importProgress / $importTotal) * 100) : 0 }}%
                    </div>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                    <div
                        class="bg-primary h-3 rounded-full transition-all duration-300 ease-out"
                        style="width: {{ $importTotal > 0 ? ($importProgress / $importTotal * 100) : 0 }}%"
                    ></div>
                </div>

                {{-- Current artist name --}}
                @if($currentArtistName)
                    <div class="mt-2 text-xs text-gray-400 truncate">
                        Procesez: {{ $currentArtistName }}
                    </div>
                @endif

                {{-- Cancel button --}}
                <div class="mt-4 flex justify-end">
                    <x-filament::button color="danger" size="sm" wire:click="cancelImport">
                        Oprește importul
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    @endif

    {{-- Results --}}
    @if(!empty($importResults))
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">Import Results</x-slot>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $importResults['imported'] }}</div>
                        <div class="text-sm text-green-700 dark:text-green-400">Imported</div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $importResults['updated'] }}</div>
                        <div class="text-sm text-blue-700 dark:text-blue-400">Updated</div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $importResults['skipped'] }}</div>
                        <div class="text-sm text-gray-700 dark:text-gray-400">Skipped</div>
                    </div>
                </div>

                @if(!empty($importResults['errors']))
                    <div class="mt-4">
                        <h4 class="font-medium text-red-600 mb-2">Errors ({{ count($importResults['errors']) }})</h4>
                        <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                            @foreach($importResults['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
