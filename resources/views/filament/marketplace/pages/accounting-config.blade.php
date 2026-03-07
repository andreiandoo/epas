<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Connection Status --}}
        @if($connectionStatus)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @if($connectionStatus === 'connected')
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            <span class="font-medium text-green-700 dark:text-green-400">Conectat la {{ ucfirst($provider) }}</span>
                        @else
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="font-medium text-red-700 dark:text-red-400">Eroare conexiune {{ ucfirst($provider) }}</span>
                        @endif
                    </div>
                    <button wire:click="disconnect" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                        Deconectare
                    </button>
                </div>
                @if($lastError)
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $lastError }}</p>
                @endif
            </div>
        @endif

        {{-- Configuration Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Configurare Provider Contabilitate</h3>

            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-6 flex items-center gap-3">
                    <x-filament::button type="submit" color="primary">
                        Salvează și Conectează
                    </x-filament::button>

                    @if($provider)
                        <x-filament::button type="button" color="gray" wire:click="testConnection">
                            Testează Conexiunea
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
