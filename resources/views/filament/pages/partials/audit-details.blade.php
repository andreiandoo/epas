<div class="space-y-6">
    {{-- Event Info --}}
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Event</p>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                @if($record->event === 'created') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                @elseif($record->event === 'updated') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                @elseif($record->event === 'deleted') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                @else bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                @endif">
                {{ $record->getEventLabel() }}
            </span>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Date & Time</p>
            <p class="text-sm text-gray-900 dark:text-white">{{ $record->created_at->format('M j, Y H:i:s') }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Changed By</p>
            <p class="text-sm text-gray-900 dark:text-white">{{ $record->user_name ?? 'System' }}</p>
        </div>
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Type</p>
            <p class="text-sm text-gray-900 dark:text-white">{{ $record->getTaxTypeLabel() }}</p>
        </div>
        @if($record->ip_address)
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</p>
            <p class="text-sm text-gray-900 dark:text-white">{{ $record->ip_address }}</p>
        </div>
        @endif
    </div>

    @if($record->reason)
    <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Reason</p>
        <p class="text-sm text-gray-900 dark:text-white">{{ $record->reason }}</p>
    </div>
    @endif

    {{-- Changes --}}
    @php
        $changes = $record->getChangedFields();
    @endphp

    @if($record->event === 'created')
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">New Values</p>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left font-medium text-gray-600 dark:text-gray-300 pr-4">Field</th>
                            <th class="text-left font-medium text-gray-600 dark:text-gray-300">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($record->new_values ?? [] as $field => $value)
                            @if(!in_array($field, ['created_at', 'updated_at', 'deleted_at']))
                            <tr>
                                <td class="py-1 pr-4 text-gray-500 dark:text-gray-400">{{ Str::title(str_replace('_', ' ', $field)) }}</td>
                                <td class="py-1 text-green-700 dark:text-green-300">
                                    @if(is_array($value))
                                        {{ json_encode($value) }}
                                    @elseif(is_bool($value))
                                        {{ $value ? 'Yes' : 'No' }}
                                    @else
                                        {{ $value ?? 'null' }}
                                    @endif
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($record->event === 'deleted')
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Deleted Values</p>
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left font-medium text-gray-600 dark:text-gray-300 pr-4">Field</th>
                            <th class="text-left font-medium text-gray-600 dark:text-gray-300">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($record->old_values ?? [] as $field => $value)
                            @if(!in_array($field, ['created_at', 'updated_at', 'deleted_at']))
                            <tr>
                                <td class="py-1 pr-4 text-gray-500 dark:text-gray-400">{{ Str::title(str_replace('_', ' ', $field)) }}</td>
                                <td class="py-1 text-red-700 dark:text-red-300">
                                    @if(is_array($value))
                                        {{ json_encode($value) }}
                                    @elseif(is_bool($value))
                                        {{ $value ? 'Yes' : 'No' }}
                                    @else
                                        {{ $value ?? 'null' }}
                                    @endif
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(!empty($changes))
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Changed Fields</p>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Field</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Old Value</th>
                            <th class="px-4 py-2 text-left font-medium text-gray-600 dark:text-gray-300">New Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                        @foreach($changes as $field => $values)
                            <tr>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                    {{ Str::title(str_replace('_', ' ', $field)) }}
                                </td>
                                <td class="px-4 py-2 text-red-600 dark:text-red-400">
                                    @if(is_array($values['old']))
                                        {{ json_encode($values['old']) }}
                                    @elseif(is_bool($values['old']))
                                        {{ $values['old'] ? 'Yes' : 'No' }}
                                    @else
                                        {{ $values['old'] ?? 'null' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-green-600 dark:text-green-400">
                                    @if(is_array($values['new']))
                                        {{ json_encode($values['new']) }}
                                    @elseif(is_bool($values['new']))
                                        {{ $values['new'] ? 'Yes' : 'No' }}
                                    @else
                                        {{ $values['new'] ?? 'null' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="text-center py-4 text-gray-500 dark:text-gray-400">
            No detailed changes available.
        </div>
    @endif

    @if($record->user_agent)
    <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">User Agent</p>
        <p class="text-xs text-gray-600 dark:text-gray-400 break-all">{{ $record->user_agent }}</p>
    </div>
    @endif
</div>
