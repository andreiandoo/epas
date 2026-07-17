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
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Cronologie activitate</h2>
                </div>

                <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-700/70">
                    @foreach($logs as $log)
                        <li class="px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                            <div class="flex items-start gap-2.5">
                                {{-- Small action icon --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center
                                        @switch($log['event'])
                                            @case('created') bg-green-100 dark:bg-green-900/40 @break
                                            @case('deleted') bg-red-100 dark:bg-red-900/40 @break
                                            @default bg-blue-100 dark:bg-blue-900/40
                                        @endswitch
                                    ">
                                        @if($log['subject_kind'] === 'ticket_type')
                                            <x-heroicon-o-ticket class="w-3.5 h-3.5 text-blue-600 dark:text-blue-300" />
                                        @else
                                            @switch($log['event'])
                                                @case('created')
                                                    <x-heroicon-o-plus class="w-3.5 h-3.5 text-green-600 dark:text-green-300" />
                                                    @break
                                                @case('deleted')
                                                    <x-heroicon-o-trash class="w-3.5 h-3.5 text-red-600 dark:text-red-300" />
                                                    @break
                                                @default
                                                    <x-heroicon-o-pencil class="w-3.5 h-3.5 text-blue-600 dark:text-blue-300" />
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
                                              title="{{ $log['formatted_date'] }} {{ $log['formatted_time'] }}">
                                            {{ $log['formatted_date'] }}, {{ $log['formatted_time'] }}
                                        </span>
                                    </div>

                                    {{-- Line 2: who --}}
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                        {{ $log['causer_name'] }}
                                        <span class="text-gray-400 dark:text-gray-500">· {{ $log['causer_type_label'] }}</span>
                                        <span class="text-gray-300 dark:text-gray-600">· {{ $log['relative_time'] }}</span>
                                    </div>

                                    {{-- Line 3+: inline change diff (events + created ticket types) --}}
                                    @if(count($log['changes']) > 0 && !($log['subject_kind'] === 'ticket_type' && $log['event'] === 'updated'))
                                        <div class="mt-1 space-y-0.5">
                                            @foreach($log['changes'] as $change)
                                                <div class="text-xs leading-snug">
                                                    <span class="text-gray-500 dark:text-gray-400">{{ $change['field'] }}:</span>
                                                    @if($change['old'] !== '(empty)')
                                                        <span class="text-gray-400 dark:text-gray-500 line-through break-words">{{ $change['old'] }}</span>
                                                        <span class="text-gray-300 dark:text-gray-600">→</span>
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
