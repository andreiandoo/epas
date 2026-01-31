<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500 dark:text-gray-400">API Calls</p>
            <p class="text-2xl font-bold">{{ number_format($microservice->getUsageStat('api_calls', 0)) }}</p>
        </div>
        <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500 dark:text-gray-400">Events Processed</p>
            <p class="text-2xl font-bold">{{ number_format($microservice->getUsageStat('events_processed', 0)) }}</p>
        </div>
        <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500 dark:text-gray-400">Errors</p>
            <p class="text-2xl font-bold text-red-500">{{ number_format($microservice->getUsageStat('errors', 0)) }}</p>
        </div>
        <div class="p-4 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500 dark:text-gray-400">Avg Response Time</p>
            <p class="text-2xl font-bold">{{ $microservice->getUsageStat('avg_response_ms', 0) }}ms</p>
        </div>
    </div>

    <div class="border-t pt-4 dark:border-gray-700">
        <h4 class="font-medium mb-2">Activity Timeline</h4>
        <div class="space-y-2 text-sm">
            <p><strong>Activated:</strong> {{ $microservice->activated_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
            @if($microservice->deactivated_at)
                <p><strong>Deactivated:</strong> {{ $microservice->deactivated_at->format('Y-m-d H:i:s') }}</p>
            @endif
            <p><strong>Last Used:</strong>
                @if($lastUsed = $microservice->getUsageStat('last_used'))
                    {{ \Carbon\Carbon::parse($lastUsed)->format('Y-m-d H:i:s') }}
                    ({{ \Carbon\Carbon::parse($lastUsed)->diffForHumans() }})
                @else
                    Never
                @endif
            </p>
        </div>
    </div>

    @if($microservice->settings && count($microservice->settings) > 0)
        <div class="border-t pt-4 dark:border-gray-700">
            <h4 class="font-medium mb-2">Current Settings</h4>
            <pre class="p-3 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-x-auto">{{ json_encode($microservice->settings, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</div>
