<x-filament-panels::page>
    <div class="space-y-6">
        @if($activities->isEmpty())
            <div class="text-center py-12">
                <x-heroicon-o-clock class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No activity yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Activity will appear here as you use the platform.</p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($activities as $activity)
                        <li class="px-6 py-4">
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <x-heroicon-o-user class="w-5 h-5 text-gray-500" />
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $activity->description }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        @if($activity->causer)
                                            {{ $activity->causer->name ?? $activity->causer->email ?? 'System' }}
                                        @else
                                            System
                                        @endif
                                        &middot;
                                        {{ $activity->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex-shrink-0 text-xs text-gray-400">
                                    {{ $activity->created_at->format('d M Y H:i') }}
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
