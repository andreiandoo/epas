<x-filament-panels::page>
    @if($this->newKeyValue)
        <div class="p-4 mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-yellow-600 dark:text-yellow-400 flex-shrink-0" />
                <div class="flex-1">
                    <h3 class="font-semibold text-yellow-800 dark:text-yellow-200">New API Key Generated</h3>
                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                        Copy this key now. You won't be able to see it again!
                    </p>
                    <div class="mt-3 flex items-center gap-2">
                        <code class="flex-1 p-2 bg-yellow-100 dark:bg-yellow-900/40 rounded text-xs font-mono text-yellow-900 dark:text-yellow-100 break-all">
                            {{ $this->newKeyValue }}
                        </code>
                        <button
                            type="button"
                            x-data
                            x-on:click="navigator.clipboard.writeText('{{ $this->newKeyValue }}'); $tooltip('Copied!')"
                            class="p-2 text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200"
                        >
                            <x-heroicon-o-clipboard class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-2">API Usage</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Use these keys to authenticate requests to the public API endpoints.
        </p>
        <div class="text-sm font-mono bg-gray-100 dark:bg-gray-900 p-3 rounded">
            <p class="text-gray-700 dark:text-gray-300">
                <span class="text-blue-600 dark:text-blue-400">GET</span>
                https://core.tixello.com/api/v1/public/stats
            </p>
            <p class="text-gray-500 dark:text-gray-500 mt-1">
                Header: <span class="text-green-600 dark:text-green-400">X-API-Key: your-api-key</span>
            </p>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
            Available endpoints: /stats, /venues, /artists, /tenants, /events
        </p>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
