<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Tax Calculator
        </x-slot>
        <x-slot name="description">
            Preview how taxes will be calculated for a given amount and location
        </x-slot>

        <form wire:submit="calculateTaxes">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Calculate
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if ($this->result)
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Calculation Results
            </x-slot>

            <div class="space-y-6">
                {{-- Summary --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Subtotal</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($this->result['subtotal'], 2) }}
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        <p class="text-sm text-blue-600 dark:text-blue-400">Total Tax</p>
                        <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">
                            {{ number_format($this->result['total_tax'], 2) }}
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Effective Rate</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            @if ($this->result['subtotal'] > 0)
                                {{ number_format(($this->result['total_tax'] / $this->result['subtotal']) * 100, 2) }}%
                            @else
                                0.00%
                            @endif
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
                        <p class="text-sm text-green-600 dark:text-green-400">Grand Total</p>
                        <p class="text-2xl font-bold text-green-700 dark:text-green-300">
                            {{ number_format($this->result['total'], 2) }}
                        </p>
                    </div>
                </div>

                {{-- Tax Breakdown --}}
                @if (!empty($this->result['breakdown']))
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Tax Breakdown</h4>
                        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tax</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rate</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($this->result['breakdown'] as $item)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $item['name'] }}
                                                </div>
                                                @if (!empty($item['location']))
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ collect([$item['location']['city'], $item['location']['county'], $item['location']['country']])->filter()->implode(', ') }}
                                                    </div>
                                                @endif
                                                @if ($item['exemption_applied'])
                                                    <div class="text-xs text-orange-600 dark:text-orange-400">
                                                        Exemption: {{ $item['exemption_applied'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                    {{ $item['type'] === 'general' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/20 dark:text-primary-400' : 'bg-success-100 text-success-700 dark:bg-success-900/20 dark:text-success-400' }}">
                                                    {{ ucfirst($item['type']) }}
                                                </span>
                                                @if ($item['is_compound'])
                                                    <span class="ml-1 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-900/20 dark:text-warning-400">
                                                        Compound
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-white">
                                                @if ($item['rate_type'] === 'percent')
                                                    {{ number_format($item['rate'], 2) }}%
                                                @else
                                                    {{ number_format($item['rate'], 2) }} {{ $item['currency'] ?? '' }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">
                                                @if ($item['exemption_applied'] && $item['original_amount'])
                                                    <span class="line-through text-gray-400">{{ number_format($item['original_amount'], 2) }}</span>
                                                @endif
                                                {{ number_format($item['amount'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Total Tax:
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-bold text-gray-900 dark:text-white">
                                            {{ number_format($this->result['total_tax'], 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-receipt-percent class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2">No taxes applicable for the selected criteria.</p>
                    </div>
                @endif

                @if ($this->result['exemptions_applied'])
                    <div class="p-3 rounded-lg bg-orange-50 dark:bg-orange-900/20">
                        <div class="flex items-center gap-2 text-orange-800 dark:text-orange-200">
                            <x-heroicon-o-shield-check class="w-5 h-5" />
                            <span class="text-sm font-medium">Tax exemptions were applied to this calculation.</span>
                        </div>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="mt-6">
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-calculator class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600" />
                <p class="mt-4 text-lg">Enter an amount and click "Calculate" to preview taxes.</p>
                <p class="mt-2 text-sm">You can optionally specify a location and event type for more accurate results.</p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
