<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cohort Type</label>
                    <select wire:model.live="cohortType" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm">
                        <option value="month">Monthly</option>
                        <option value="week">Weekly</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cohorts to Show</label>
                    <select wire:model.live="cohortCount" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm">
                        <option value="6">Last 6</option>
                        <option value="12">Last 12</option>
                        <option value="24">Last 24</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Metric</label>
                    <select wire:model.live="metricType" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm">
                        <option value="retention">Retention Rate (%)</option>
                        <option value="revenue">Avg Revenue per User ($)</option>
                    </select>
                </div>
            </div>
        </x-filament::section>

        {{-- Summary Stats --}}
        @if(!empty($summary))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['total_cohorts'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Cohorts Analyzed</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['total_customers'] ?? 0) }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Customers</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ $summary['avg_retention'][1] ?? 0 }}%</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Avg M1 Retention</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">${{ number_format($summary['avg_revenue'][1] ?? 0, 2) }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Avg M1 ARPU</div>
                </div>
            </x-filament::section>
        </div>
        @endif

        {{-- Cohort Table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ $metricType === 'retention' ? 'Retention Rate Matrix' : 'Revenue per User Matrix' }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Cohort</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-700 dark:text-gray-300">Size</th>
                            @foreach($this->getOffsetLabels() as $index => $label)
                                @if($index <= 12)
                                    <th class="px-2 py-2 text-center font-medium text-gray-700 dark:text-gray-300 min-w-[50px]">{{ $label }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cohortData as $cohort)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ $cohort['period_label'] }}
                                </td>
                                <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">
                                    {{ number_format($cohort['base_count']) }}
                                </td>
                                @foreach($cohort[$metricType] as $offset => $value)
                                    @if($offset <= 12)
                                        <td class="px-2 py-2 text-center">
                                            @if($value !== null)
                                                @if($metricType === 'retention')
                                                    <span @class([
                                                        'inline-flex items-center justify-center w-full px-2 py-1 rounded text-xs font-medium',
                                                        'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' => $value >= 60,
                                                        'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200' => $value >= 30 && $value < 60,
                                                        'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' => $value < 30,
                                                    ])>
                                                        {{ $value }}%
                                                    </span>
                                                @else
                                                    <span class="text-gray-700 dark:text-gray-300">
                                                        ${{ number_format($value, 0) }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <x-heroicon-o-users class="w-12 h-12 mx-auto mb-2 opacity-50" />
                                    <p>No cohort data available.</p>
                                    <p class="text-sm">Customers need to have a cohort_month assigned.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    {{-- Average row --}}
                    @if(!empty($cohortData) && !empty($summary))
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-800 font-medium">
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">Average</td>
                                <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">-</td>
                                @foreach($summary['avg_' . $metricType] as $offset => $value)
                                    @if($offset <= 12)
                                        <td class="px-2 py-2 text-center">
                                            @if($value !== null)
                                                @if($metricType === 'retention')
                                                    <span class="text-primary-600 dark:text-primary-400 font-semibold">{{ $value }}%</span>
                                                @else
                                                    <span class="text-primary-600 dark:text-primary-400 font-semibold">${{ number_format($value, 0) }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </x-filament::section>

        {{-- Legend --}}
        <x-filament::section>
            <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-success-100 dark:bg-success-900"></span>
                    <span class="text-gray-600 dark:text-gray-400">Good (60%+)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-warning-100 dark:bg-warning-900"></span>
                    <span class="text-gray-600 dark:text-gray-400">Average (30-60%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-danger-100 dark:bg-danger-900"></span>
                    <span class="text-gray-600 dark:text-gray-400">Needs Improvement (&lt;30%)</span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
