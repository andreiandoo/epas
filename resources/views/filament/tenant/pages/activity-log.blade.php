<x-filament-panels::page>
    @if($activities->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-12 text-center">
            <x-heroicon-o-clock class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No activity yet</h3>
            <p class="text-gray-600">Your activity history will appear here.</p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border overflow-hidden">
            <div class="divide-y">
                @foreach($activities as $activity)
                    <div class="p-4 hover:bg-gray-50">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 p-2 bg-gray-100 rounded-lg">
                                @if(str_contains($activity->description, 'created'))
                                    <x-heroicon-o-plus-circle class="w-5 h-5 text-green-600" />
                                @elseif(str_contains($activity->description, 'updated'))
                                    <x-heroicon-o-pencil-square class="w-5 h-5 text-blue-600" />
                                @elseif(str_contains($activity->description, 'deleted'))
                                    <x-heroicon-o-trash class="w-5 h-5 text-red-600" />
                                @else
                                    <x-heroicon-o-document-text class="w-5 h-5 text-gray-600" />
                                @endif
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm text-gray-900">
                                    {{ ucfirst($activity->description) }}
                                    @if($activity->subject_type)
                                        <span class="text-gray-500">
                                            on {{ class_basename($activity->subject_type) }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $activity->created_at->diffForHumans() }}
                                    &bull;
                                    {{ $activity->created_at->format('M d, Y H:i') }}
                                </p>
                                @if($activity->properties && $activity->properties->count() > 0)
                                    <details class="mt-2">
                                        <summary class="text-xs text-indigo-600 cursor-pointer hover:text-indigo-800">
                                            View details
                                        </summary>
                                        <pre class="mt-2 text-xs bg-gray-50 p-2 rounded overflow-auto max-h-32">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                                    </details>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
