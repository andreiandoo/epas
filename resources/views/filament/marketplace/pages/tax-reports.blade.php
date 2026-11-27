<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            {{-- Search bar --}}
            <div class="mb-4">
                <input type="text"
                       wire:model.live.debounce.400ms="search"
                       placeholder="{{ __('Caută după nume organizator sau eveniment...') }}"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Organizator') }}</label>
                    <select wire:model.live="filterOrganizer" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                        <option value="">{{ __('Toți organizatorii') }}</option>
                        @foreach($organizerOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @if(count($eventOptions) > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Eveniment') }}</label>
                        <select wire:model.live="filterEvent" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                            <option value="">{{ __('Toate evenimentele') }}</option>
                            @foreach($eventOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Status') }}</label>
                    <select wire:model.live="filterStatus" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                        <option value="all">{{ __('Toate') }}</option>
                        <option value="upcoming">{{ __('Viitoare') }}</option>
                        <option value="past">{{ __('Trecute') }}</option>
                        <option value="cancelled">{{ __('Anulate') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Perioadă') }}</label>
                    <select wire:model.live="filterPeriod" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                        <option value="all">{{ __('Tot timpul') }}</option>
                        <option value="upcoming">{{ __('Viitoare') }}</option>
                        <option value="this_month">{{ __('Luna curentă') }}</option>
                        <option value="this_quarter">{{ __('Trimestrul curent') }}</option>
                        <option value="this_year">{{ __('Anul curent') }}</option>
                        <option value="past">{{ __('Evenimente trecute') }}</option>
                    </select>
                </div>
            </div>
        </div>

        @if(!$hasFilters)
            {{-- No filters applied - show prompt --}}
            <div class="text-center py-12">
                <x-heroicon-o-funnel class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ __('Selectează un filtru') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Alege un organizator, eveniment sau folosește căutarea pentru a afișa raportul fiscal.') }}
                </p>
            </div>
        @else
            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Revenue') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($filteredTotals['total_revenue'], 2) }} RON</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Taxes') }}</p>
                    <p class="text-2xl font-bold text-warning-600">{{ number_format($filteredTotals['total_tax'], 2) }} RON</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Evenimente') }}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $filteredTotals['event_count'] }}</p>
                </div>
            </div>

            {{-- Events List --}}
            @if(count($filteredEvents) > 0)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Events Tax Breakdown') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($filteredEvents as $event)
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <a href="{{ url('/marketplace/event-tax-report/' . $event['event']['id']) }}"
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
                                        <p class="text-gray-500">{{ __('Revenue') }}</p>
                                        <p class="font-medium">{{ number_format($event['estimated_revenue'], 2) }} RON</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">{{ __('Taxes') }}</p>
                                        <p class="font-medium text-warning-600">{{ number_format($event['total_tax'], 2) }} RON</p>
                                    </div>
                                    <div>
                                        <p class="text-gray-500">{{ __('Net') }}</p>
                                        <p class="font-medium text-green-600">{{ number_format($event['net_revenue'], 2) }} RON</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-document-magnifying-glass class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('Nu s-au găsit evenimente pentru filtrele selectate.') }}</p>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
