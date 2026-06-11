<div class="space-y-3">
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">Seed Customers</div>
            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ number_format($estimate['seed_count']) }}
            </div>
            @if($estimate['quality_indicator'] === 'high')
                <x-filament::badge color="success" class="mt-1">Excellent</x-filament::badge>
            @elseif($estimate['quality_indicator'] === 'medium')
                <x-filament::badge color="warning" class="mt-1">Good</x-filament::badge>
            @else
                <x-filament::badge color="danger" class="mt-1">Low</x-filament::badge>
            @endif
        </div>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-gray-500 dark:text-gray-400">Estimated Reach</div>
            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ number_format($estimate['estimated_reach_min']) }} - {{ number_format($estimate['estimated_reach_max']) }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                {{ $estimate['percentage'] }}% in {{ $estimate['country'] }}
            </div>
        </div>
    </div>

    <div class="text-sm text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
        <x-heroicon-m-light-bulb class="w-4 h-4 inline-block mr-1 text-blue-500"/>
        {{ $estimate['recommendation'] }}
    </div>
</div>
