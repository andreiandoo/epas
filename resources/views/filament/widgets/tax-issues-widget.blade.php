<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Tax Configuration Status
        </x-slot>

        @php
            $issues = $this->getIssues();
            $expiring = $this->getExpiringTaxes();
        @endphp

        <div class="space-y-4">
            {{-- Issues Section --}}
            @if (count($issues) > 0)
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Issues Found</h4>
                    @foreach ($issues as $issue)
                        <div class="flex items-start gap-3 p-3 rounded-lg
                            @if ($issue['severity'] === 'warning') bg-yellow-50 dark:bg-yellow-900/20
                            @elseif ($issue['severity'] === 'danger') bg-red-50 dark:bg-red-900/20
                            @else bg-blue-50 dark:bg-blue-900/20 @endif">
                            @if ($issue['severity'] === 'warning')
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-500 flex-shrink-0" />
                            @elseif ($issue['severity'] === 'danger')
                                <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 flex-shrink-0" />
                            @else
                                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 flex-shrink-0" />
                            @endif
                            <span class="text-sm
                                @if ($issue['severity'] === 'warning') text-yellow-800 dark:text-yellow-200
                                @elseif ($issue['severity'] === 'danger') text-red-800 dark:text-red-200
                                @else text-blue-800 dark:text-blue-200 @endif">
                                {{ $issue['message'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center gap-3 p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 flex-shrink-0" />
                    <span class="text-sm text-green-800 dark:text-green-200">
                        No configuration issues detected.
                    </span>
                </div>
            @endif

            {{-- Expiring Taxes Section --}}
            @if ($expiring['total'] > 0)
                <div class="mt-4 space-y-2">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Expiring in Next 30 Days</h4>

                    @if ($expiring['general']->isNotEmpty())
                        <div class="space-y-1">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">General Taxes</p>
                            @foreach ($expiring['general']->take(3) as $tax)
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $tax->name }}</span>
                                    <span class="text-xs text-orange-600 dark:text-orange-400">
                                        Expires {{ $tax->valid_until->format('M j, Y') }}
                                    </span>
                                </div>
                            @endforeach
                            @if ($expiring['general']->count() > 3)
                                <p class="text-xs text-gray-500">
                                    + {{ $expiring['general']->count() - 3 }} more
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($expiring['local']->isNotEmpty())
                        <div class="space-y-1 mt-3">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Local Taxes</p>
                            @foreach ($expiring['local']->take(3) as $tax)
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $tax->getLocationString() }}</span>
                                    <span class="text-xs text-orange-600 dark:text-orange-400">
                                        Expires {{ $tax->valid_until->format('M j, Y') }}
                                    </span>
                                </div>
                            @endforeach
                            @if ($expiring['local']->count() > 3)
                                <p class="text-xs text-gray-500">
                                    + {{ $expiring['local']->count() - 3 }} more
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
