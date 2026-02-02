<x-filament-panels::page>
    @if(!$tenant || $venues->isEmpty())
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
            <x-heroicon-o-building-office-2 class="w-12 h-12 mx-auto text-yellow-500 dark:text-yellow-400 mb-3" />
            <p class="text-yellow-800 dark:text-yellow-200 font-medium">No venues found.</p>
            <p class="text-yellow-600 dark:text-yellow-300 text-sm mt-1">You need to own at least one venue to see venue usage.</p>
        </div>
    @else
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <!-- Total Events -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <x-heroicon-o-calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_events']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Events</p>
                    </div>
                </div>
            </div>

            <!-- Your Events -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <x-heroicon-o-calendar-days class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['own_events']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Your Events</p>
                    </div>
                </div>
            </div>

            <!-- Hosted Events -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <x-heroicon-o-users class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['hosted_events']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Hosted Events</p>
                    </div>
                </div>
            </div>

            <!-- Upcoming -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                        <x-heroicon-o-clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['upcoming_events']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Upcoming</p>
                    </div>
                </div>
            </div>

            <!-- Hosted Tickets Sold -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-pink-100 dark:bg-pink-900/30 rounded-lg">
                        <x-heroicon-o-ticket class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['hosted_tickets_sold']) }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Hosted Tickets</p>
                    </div>
                </div>
            </div>

            <!-- Hosted Revenue -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-teal-100 dark:bg-teal-900/30 rounded-lg">
                        <x-heroicon-o-banknotes class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['hosted_revenue'], 2) }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $tenant->currency ?? 'EUR' }}</span></p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Hosted Revenue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Venue</label>
                    <select
                        wire:model.live="venueFilter"
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="all">All Venues</option>
                        @foreach($venues as $venue)
                            <option value="{{ $venue->id }}">{{ $venue->getTranslation('name', app()->getLocale()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                    <select
                        wire:model.live="statusFilter"
                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-primary-500 focus:border-primary-500"
                    >
                        <option value="upcoming">Upcoming</option>
                        <option value="past">Past</option>
                        <option value="all">All</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Events List -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Events at Your Venues</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $events->count() }} event{{ $events->count() !== 1 ? 's' : '' }} found</p>
            </div>

            @if($events->isEmpty())
                <div class="p-8 text-center">
                    <x-heroicon-o-calendar class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-3" />
                    <p class="text-gray-500 dark:text-gray-400">No events found matching your filters.</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($events as $event)
                        @php
                            $eventStats = $this->getEventStats($event);
                            $isOwn = $this->isOwnEvent($event);
                        @endphp
                        <div class="p-5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <!-- Event Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if($isOwn)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                Your Event
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                Hosted
                                            </span>
                                        @endif
                                        @if($event->is_cancelled)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                Cancelled
                                            </span>
                                        @elseif($event->is_sold_out)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                                Sold Out
                                            </span>
                                        @endif
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $event->getTranslation('title', app()->getLocale()) }}
                                    </h4>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="flex items-center gap-1">
                                            <x-heroicon-o-calendar class="w-4 h-4" />
                                            {{ $event->start_date?->format('M d, Y') ?? 'TBD' }}
                                            @if($event->start_time)
                                                at {{ $event->start_time }}
                                            @endif
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <x-heroicon-o-building-office-2 class="w-4 h-4" />
                                            {{ $event->venue?->getTranslation('name', app()->getLocale()) ?? 'No venue' }}
                                        </span>
                                        @if(!$isOwn && $event->tenant)
                                            <span class="flex items-center gap-1">
                                                <x-heroicon-o-user-circle class="w-4 h-4" />
                                                Organized by: <strong>{{ $event->tenant->public_name ?? $event->tenant->name }}</strong>
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Event Stats -->
                                <div class="flex items-center gap-6">
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($eventStats['tickets_sold']) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tickets</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($eventStats['revenue'], 2) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant->currency ?? 'EUR' }}</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-xl font-bold {{ $eventStats['occupancy'] >= 80 ? 'text-green-600 dark:text-green-400' : ($eventStats['occupancy'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white') }}">{{ $eventStats['occupancy'] }}%</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Occupancy</p>
                                    </div>

                                    <!-- Action Button -->
                                    <div>
                                        @if($isOwn)
                                            <a href="{{ route('filament.tenant.resources.events.edit', ['record' => $event->id]) }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                                                <x-heroicon-o-pencil class="w-4 h-4" />
                                                Edit
                                            </a>
                                        @else
                                            <a href="{{ route('filament.tenant.resources.events.view-guest', ['record' => $event->id]) }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                                <x-heroicon-o-eye class="w-4 h-4" />
                                                View Details
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
