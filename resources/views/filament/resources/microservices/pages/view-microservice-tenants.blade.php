<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Microservice Details</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Name:</span>
                    <p class="font-medium">{{ $record->name }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Price:</span>
                    <p class="font-medium">{{ number_format($record->price, 2) }} RON</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Pricing Model:</span>
                    <p class="font-medium">{{ ucfirst($record->pricing_model) }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Total Active Tenants:</span>
                    <p class="font-medium">{{ $record->tenants()->wherePivot('is_active', true)->count() }}</p>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
