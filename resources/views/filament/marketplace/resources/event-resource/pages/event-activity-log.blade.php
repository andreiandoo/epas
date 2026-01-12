<x-filament-panels::page>
    @php
        $logs = $this->getActivityLogs();
    @endphp

    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-primary-600">{{ $logs->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Changes</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600">{{ $logs->where('event', 'created')->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Created</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-blue-600">{{ $logs->where('event', 'updated')->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Updated</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-purple-600">{{ $logs->unique('causer_email')->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Contributors</div>
            </div>
        </div>

        {{-- Activity Timeline --}}
        @if($logs->isEmpty())
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <x-heroicon-o-clock class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No activity recorded</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Activity logs will appear here as changes are made to this event.</p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Timeline</h2>
                </div>

                <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($logs as $log)
                        <li class="px-6 py-5 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex gap-4">
                                {{-- Avatar/Icon --}}
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center
                                        @switch($log['causer_type'])
                                            @case('admin')
                                                bg-red-100 dark:bg-red-900/30
                                                @break
                                            @case('organizer')
                                                bg-blue-100 dark:bg-blue-900/30
                                                @break
                                            @case('customer')
                                                bg-green-100 dark:bg-green-900/30
                                                @break
                                            @default
                                                bg-gray-100 dark:bg-gray-700
                                        @endswitch
                                    ">
                                        @switch($log['causer_type'])
                                            @case('admin')
                                                <x-heroicon-o-shield-check class="w-6 h-6 text-red-600 dark:text-red-400" />
                                                @break
                                            @case('organizer')
                                                <x-heroicon-o-user-circle class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                                @break
                                            @case('customer')
                                                <x-heroicon-o-user class="w-6 h-6 text-green-600 dark:text-green-400" />
                                                @break
                                            @default
                                                <x-heroicon-o-cog-6-tooth class="w-6 h-6 text-gray-500" />
                                        @endswitch
                                    </div>
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    {{-- User Info & Action --}}
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="font-semibold text-gray-900 dark:text-white">
                                            {{ $log['causer_name'] }}
                                        </span>

                                        @if($log['causer_email'])
                                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                                ({{ $log['causer_email'] }})
                                            </span>
                                        @endif

                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @switch($log['causer_type'])
                                                @case('admin')
                                                    bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                                    @break
                                                @case('organizer')
                                                    bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                                    @break
                                                @case('customer')
                                                    bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                                    @break
                                                @default
                                                    bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endswitch
                                        ">
                                            {{ ucfirst($log['causer_type']) }}
                                        </span>
                                    </div>

                                    {{-- Action Description --}}
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium
                                            @switch($log['event'])
                                                @case('created')
                                                    bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                                    @break
                                                @case('updated')
                                                    bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                                    @break
                                                @case('deleted')
                                                    bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                                    @break
                                                @default
                                                    bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endswitch
                                        ">
                                            @switch($log['event'])
                                                @case('created')
                                                    <x-heroicon-o-plus-circle class="w-4 h-4 mr-1" />
                                                    Created Event
                                                    @break
                                                @case('updated')
                                                    <x-heroicon-o-pencil-square class="w-4 h-4 mr-1" />
                                                    Updated Event
                                                    @break
                                                @case('deleted')
                                                    <x-heroicon-o-trash class="w-4 h-4 mr-1" />
                                                    Deleted Event
                                                    @break
                                                @default
                                                    {{ ucfirst($log['event']) }}
                                            @endswitch
                                        </span>
                                    </div>

                                    {{-- Changes Details --}}
                                    @if(count($log['changes']) > 0)
                                        <div class="mt-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                                Changes Made
                                            </h4>
                                            <div class="space-y-3">
                                                @foreach($log['changes'] as $change)
                                                    <div class="text-sm">
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                                            {{ $change['field'] }}:
                                                        </span>
                                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                                            @if($change['old'] !== '(empty)')
                                                                <span class="inline-flex items-center max-w-full">
                                                                    <span class="flex-shrink-0 w-4 h-4 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center mr-1.5">
                                                                        <x-heroicon-o-minus class="w-2.5 h-2.5 text-red-600 dark:text-red-400" />
                                                                    </span>
                                                                    <span class="text-gray-500 dark:text-gray-400 line-through break-all">
                                                                        {{ Str::limit($change['old'], 80) }}
                                                                    </span>
                                                                </span>
                                                            @endif
                                                            @if($change['new'] !== '(empty)')
                                                                <span class="inline-flex items-center max-w-full">
                                                                    <span class="flex-shrink-0 w-4 h-4 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center mr-1.5">
                                                                        <x-heroicon-o-plus class="w-2.5 h-2.5 text-green-600 dark:text-green-400" />
                                                                    </span>
                                                                    <span class="text-gray-900 dark:text-white font-medium break-all">
                                                                        {{ Str::limit($change['new'], 80) }}
                                                                    </span>
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Timestamp --}}
                                <div class="flex-shrink-0 text-right">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $log['formatted_date'] }}
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log['formatted_time'] }}
                                    </div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        {{ $log['relative_time'] }}
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
