<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Current Rate --}}
        <div class="bg-gradient-to-r from-blue-500 to-blue-700 rounded-lg shadow p-6 text-white">
            <h3 class="text-lg font-semibold mb-2">Current Exchange Rate</h3>
            <div class="flex items-baseline gap-4">
                <span class="text-4xl font-bold">
                    @if($currentRate)
                        {{ number_format($currentRate, 4) }}
                    @else
                        —
                    @endif
                </span>
                <span class="text-xl text-blue-100">RON / EUR</span>
            </div>
            @if($currentRate)
                <p class="mt-2 text-sm text-blue-100">
                    1 EUR = {{ number_format($currentRate, 4) }} RON
                </p>
            @else
                <p class="mt-2 text-sm text-blue-200">No rate available. Click "Fetch Today's Rate" to get current rate.</p>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Manual Rate Entry --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Manual Rate</h3>
                <form wire:submit="saveManualRate">
                    {{ $this->manualRateForm }}

                    <div class="mt-4">
                        <x-filament::button type="submit">
                            Save Manual Rate
                        </x-filament::button>
                    </div>
                </form>
            </div>

            {{-- Conversion Calculator --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Converter</h3>
                @if($currentRate)
                    <div class="space-y-4" x-data="{
                        eur: 100,
                        rate: {{ $currentRate }},
                        get ron() { return (this.eur * this.rate).toFixed(2) }
                    }">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">EUR</label>
                            <input
                                type="number"
                                x-model="eur"
                                step="0.01"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm"
                            >
                        </div>
                        <div class="text-center text-gray-500">
                            <x-heroicon-o-arrows-up-down class="w-6 h-6 mx-auto" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RON</label>
                            <input
                                type="text"
                                x-bind:value="ron"
                                readonly
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm bg-gray-50 dark:bg-gray-600"
                            >
                        </div>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">No exchange rate available for conversion.</p>
                @endif
            </div>
        </div>

        {{-- Historical Rates --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Historical Rates (Last 30 Days)</h3>
            @if(count($recentRates) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Date</th>
                                <th class="text-right py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Rate (RON/EUR)</th>
                                <th class="text-center py-3 px-4 font-medium text-gray-600 dark:text-gray-400">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentRates as $rate)
                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                    <td class="py-3 px-4 text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($rate['date'])->format('M d, Y') }}
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono text-gray-900 dark:text-white">
                                        {{ number_format($rate['rate'], 4) }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            @if($rate['source'] === 'ecb') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                            @elseif($rate['source'] === 'bnr') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                                            @endif">
                                            {{ strtoupper($rate['source'] ?? 'unknown') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">No historical rates available. Click "Backfill Last 30 Days" to populate.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
