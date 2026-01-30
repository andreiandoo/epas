<x-filament-panels::page>
    <div class="space-y-6">
        @if(!$report)
            <div class="text-center py-12">
                <x-heroicon-o-calculator class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No data available</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tax reports will appear here once you have events.</p>
            </div>
        @else
            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event</label>
                        <select wire:model.live="filterEvent" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="all">All Events</option>
                            @foreach($eventOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select wire:model.live="filterStatus" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="all">All Statuses</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="past">Past</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Period</label>
                        <select wire:model.live="filterPeriod" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="all">All Time</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="this_month">This Month</option>
                            <option value="this_quarter">This Quarter</option>
                            <option value="this_year">This Year</option>
                            <option value="past">Past Events</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($filteredTotals['total_revenue'], 2) }} RON</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Taxes</p>
                    <p class="text-2xl font-bold text-warning-600">{{ number_format($filteredTotals['total_tax'], 2) }} RON</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Events</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $filteredTotals['event_count'] }}</p>
                </div>
            </div>

            {{-- Events List --}}
            @if(count($filteredEvents) > 0)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Events Tax Breakdown</h3>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($filteredEvents as $event)
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <a href="{{ route('filament.marketplace.pages.event-tax-report', ['event' => $event['event']['id']]) }}"
                                           class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">
                                            {{ $event['event']['title'] }}
                                        </a>
                                        <p class="text-sm text-gray-500">{{ $event['event']['date'] }} &middot; {{ $event['event']['venue'] }}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full {{ $event['event']['status'] === 'upcoming' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($event['event']['status']) }}
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-gray-500">Revenue</p>
                                        <p class="font-medium">{{ number_format($event['estimated_revenue'], 2) }} RON</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Taxes</p>
                                        <p class="font-medium text-warning-600">{{ number_format($event['total_tax'], 2) }} RON</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">Net</p>
                                        <p class="font-medium text-green-600">{{ number_format($event['net_revenue'], 2) }} RON</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
