<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <x-heroicon-o-credit-card class="w-8 h-8 text-primary-500" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Payment Processor Configuration</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">{{ $message }}</p>
                </div>
            </div>
        </div>

        @if(!empty($paymentSettings))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h4 class="font-medium text-gray-900 dark:text-white mb-4">Current Settings</h4>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach($paymentSettings as $key => $value)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
</x-filament-panels::page>
