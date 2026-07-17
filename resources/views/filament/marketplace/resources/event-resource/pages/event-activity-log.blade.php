<x-filament-panels::page>
    @php
        $logs = $this->getActivityLogs();
    @endphp

    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-primary-600">{{ $logs->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total modificări</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600">{{ $logs->where('event', 'created')->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Create</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-blue-600">{{ $logs->where('event', 'updated')->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Actualizări</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-purple-600">{{ $logs->pluck('causer_email')->filter()->unique()->count() }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Contribuitori</div>
            </div>
        </div>

        {{-- Activity Timeline --}}
        @if($logs->isEmpty())
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <x-heroicon-o-clock class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">Nicio activitate înregistrată</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Istoricul modificărilor va apărea aici pe măsură ce evenimentul este editat.</p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Cronologie activitate</h2>
                </div>

                <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-700/70">
                    @foreach($logs as $log)
                        <li class="px-4 py-2.5">
                            <div class="flex items-start gap-2.5">
                                {{-- Small action icon (solid, high-contrast) --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center
                                        @switch($log['event'])
                                            @case('created') bg-green-600 @break
                                            @case('deleted') bg-red-600 @break
                                            @default bg-blue-600
                                        @endswitch
                                    ">
                                        @if($log['subject_kind'] === 'ticket_type')
                                            <x-heroicon-s-ticket class="w-3.5 h-3.5 text-white" />
                                        @else
                                            @switch($log['event'])
                                                @case('created')
                                                    <x-heroicon-s-plus class="w-4 h-4 text-white" />
                                                    @break
                                                @case('deleted')
                                                    <x-heroicon-s-trash class="w-3.5 h-3.5 text-white" />
                                                    @break
                                                @default
                                                    <x-heroicon-s-pencil class="w-3 h-3 text-white" />
                                            @endswitch
                                        @endif
                                    </div>
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    {{-- Line 1: summary + timestamp --}}
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                            {{ $log['summary'] }}
                                        </span>
                                        <span class="flex-shrink-0 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap"
                                              title="{{ $log['relative_time'] }}">
                                            {{ $log['formatted_date'] }}, {{ $log['formatted_time'] }}
                                        </span>
                                    </div>

                                    {{-- Line 2: who + role badge --}}
                                    <div class="flex items-center flex-wrap gap-1.5 mt-0.5">
                                        @if($log['causer_url'])
                                            <a href="{{ $log['causer_url'] }}"
                                               class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline truncate">
                                                {{ $log['causer_name'] }}
                                            </a>
                                        @else
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300 truncate">
                                                {{ $log['causer_name'] }}
                                            </span>
                                        @endif

                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium leading-none
                                            @switch($log['causer_type'])
                                                @case('admin') bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300 @break
                                                @case('organizer') bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 @break
                                                @case('customer') bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300 @break
                                                @case('staff') bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300 @break
                                                @default bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                                            @endswitch
                                        ">
                                            {{ $log['causer_type_label'] }}
                                        </span>
                                    </div>

                                    {{-- Line 3+: inline change diff (events + created ticket types) --}}
                                    @if(count($log['changes']) > 0 && !($log['subject_kind'] === 'ticket_type' && $log['event'] === 'updated'))
                                        <div class="mt-1 space-y-0.5">
                                            @foreach($log['changes'] as $change)
                                                <div class="text-xs leading-snug">
                                                    <span class="text-gray-500 dark:text-gray-400">{{ $change['field'] }}:</span>
                                                    @if($change['old'] !== '(empty)')
                                                        <span class="text-gray-400 dark:text-gray-500 line-through break-words">{{ $change['old'] }}</span>
                                                        <span class="text-gray-400 dark:text-gray-500">→</span>
                                                    @endif
                                                    @if($change['new'] !== '(empty)')
                                                        <span class="text-gray-700 dark:text-gray-200 font-medium break-words">{{ $change['new'] }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
