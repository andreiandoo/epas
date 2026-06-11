<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        @if(!$isPaused)
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                        @endif
                        <span class="relative inline-flex rounded-full h-3 w-3 {{ $isPaused ? 'bg-gray-400' : 'bg-success-500' }}"></span>
                    </span>
                    <span>Live Event Stream</span>
                    @if($newEventsCount > 0)
                        <span class="inline-flex items-center rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                            +{{ $newEventsCount }} new
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::button
                        size="sm"
                        color="gray"
                        wire:click="togglePause"
                    >
                        @if($isPaused)
                            <x-heroicon-m-play class="w-4 h-4 mr-1" />
                            Resume
                        @else
                            <x-heroicon-m-pause class="w-4 h-4 mr-1" />
                            Pause
                        @endif
                    </x-filament::button>
                    @if($newEventsCount > 0)
                        <x-filament::button
                            size="sm"
                            color="gray"
                            wire:click="clearNewCount"
                        >
                            Clear
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-slot>

        <div wire:poll.3s="pollForNewEvents" class="space-y-2 max-h-[500px] overflow-y-auto">
            @forelse($events as $event)
                <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors {{ $loop->first && $newEventsCount > 0 ? 'ring-2 ring-primary-500 ring-opacity-50' : '' }}">
                    {{-- Event Type Icon --}}
                    <div class="flex-shrink-0">
                        <span @class([
                            'inline-flex items-center justify-center w-10 h-10 rounded-full',
                            'bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300' => $event['type_color'] === 'success',
                            'bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300' => $event['type_color'] === 'warning',
                            'bg-info-100 text-info-700 dark:bg-info-900 dark:text-info-300' => $event['type_color'] === 'info',
                            'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' => $event['type_color'] === 'primary',
                            'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' => $event['type_color'] === 'gray',
                        ])>
                            <x-dynamic-component :component="$event['type_icon']" class="w-5 h-5" />
                        </span>
                    </div>

                    {{-- Event Details --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span @class([
                                'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20' => $event['type_color'] === 'success',
                                'bg-warning-50 text-warning-700 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20' => $event['type_color'] === 'warning',
                                'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/20' => $event['type_color'] === 'info',
                                'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20' => $event['type_color'] === 'primary',
                                'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20' => $event['type_color'] === 'gray',
                            ])>
                                {{ $event['type_label'] }}
                            </span>

                            @if($event['value'] && $event['value'] > 0)
                                <span class="text-sm font-semibold text-success-600 dark:text-success-400">
                                    ${{ number_format($event['value'], 2) }}
                                </span>
                            @endif

                            @if($event['has_click_id'])
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $event['click_id_type'] }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-1 flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                            <span class="truncate max-w-[200px]" title="{{ $event['page_url'] }}">
                                {{ $event['page_title'] ?? Str::limit($event['page_url'], 40) ?? 'Unknown page' }}
                            </span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span class="flex items-center gap-1">
                                <x-dynamic-component :component="$event['device_icon']" class="w-4 h-4" />
                                {{ ucfirst($event['device']) }}
                            </span>
                            <span class="text-gray-300 dark:text-gray-600">|</span>
                            <span>{{ $event['location'] }}</span>
                        </div>

                        @if($event['source'] && $event['source'] !== 'Direct')
                            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Source: {{ $event['source'] }}
                            </div>
                        @endif
                    </div>

                    {{-- Timestamp --}}
                    <div class="flex-shrink-0 text-right">
                        <div class="text-sm font-mono text-gray-600 dark:text-gray-400">
                            {{ $event['timestamp'] }}
                        </div>
                        <div class="text-xs text-gray-400 dark:text-gray-500">
                            {{ $event['time_ago'] }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-signal-slash class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>No events yet. Events will appear here as they happen.</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
