<x-filament-panels::page>
    @if(!$this->editionId)
        <div class="text-center py-12">
            <x-heroicon-o-document-chart-bar class="w-16 h-16 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Active Edition</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">No festival edition available for reports.</p>
        </div>
    @else
        <div class="space-y-6">
            {{-- Filters --}}
            <x-filament::section>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Type</label>
                        <select wire:model.live="reportType" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            @foreach($this->getReportTypes() as $group => $types)
                                <optgroup label="{{ $group }}">
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                        <input type="date" wire:model.live="date" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vendor ID</label>
                        <input type="number" wire:model.live.debounce.500ms="vendorId" placeholder="All vendors" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm" />
                    </div>
                    <div class="flex items-end">
                        <button wire:click="loadReport" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700">
                            Refresh
                        </button>
                    </div>
                </div>
            </x-filament::section>

            {{-- Report Data --}}
            <x-filament::section heading="{{ collect($this->getReportTypes())->flatten()->search(fn($v, $k) => true) ? ucfirst(str_replace('_', ' ', $reportType)) : 'Report' }}">
                @if(empty($reportData))
                    <p class="text-gray-500 dark:text-gray-400 text-center py-8">No data available for this report.</p>
                @elseif(is_array($reportData) && !isset($reportData[0]))
                    {{-- Key-value display --}}
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach($reportData as $key => $value)
                            @if(!is_array($value))
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center">
                                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                                        @if(str_contains($key, '_cents'))
                                            {{ number_format($value / 100, 2) }} RON
                                        @elseif(str_contains($key, '_rate'))
                                            {{ $value }}%
                                        @else
                                            {{ number_format($value) }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    {{-- Table display --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    @foreach(array_keys(is_array($reportData[0] ?? null) ? $reportData[0] : []) as $header)
                                        <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">
                                            {{ ucfirst(str_replace('_', ' ', $header)) }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reportData as $row)
                                    @if(is_array($row))
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            @foreach($row as $key => $value)
                                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                                    @if(is_array($value))
                                                        <span class="text-xs text-gray-500">{{ json_encode($value) }}</span>
                                                    @elseif(str_contains($key, '_cents'))
                                                        {{ number_format($value / 100, 2) }}
                                                    @else
                                                        {{ $value }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
