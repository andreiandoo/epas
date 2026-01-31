<div class="p-4 space-y-4">
    <div class="grid grid-cols-3 gap-4 text-center">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalCount) }}</div>
            <div class="text-sm text-gray-500">Total Matches</div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-2xl font-bold text-green-600">{{ number_format($audience->matched_count ?? 0) }}</div>
            <div class="text-sm text-gray-500">Platform Matched</div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-2xl font-bold text-blue-600">{{ $audience->getMatchRate() }}%</div>
            <div class="text-sm text-gray-500">Match Rate</div>
        </div>
    </div>

    <div class="border-t dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Sample Customers (first 10)</h4>

        @if($sampleCustomers->isEmpty())
            <p class="text-gray-500 text-center py-4">No customers match this audience criteria.</p>
        @else
            <div class="space-y-2">
                @foreach($sampleCustomers as $customer)
                    <div class="flex justify-between items-center py-2 px-3 bg-gray-50 dark:bg-gray-800 rounded">
                        <div>
                            <span class="font-medium text-gray-900 dark:text-white">
                                {{ $customer->full_name ?? 'Anonymous' }}
                            </span>
                            @if($customer->email_hash)
                                <span class="text-xs text-green-600 ml-2">Has Email</span>
                            @endif
                        </div>
                        <div class="text-right text-sm text-gray-600 dark:text-gray-400">
                            <span>{{ $customer->total_orders }} orders</span>
                            <span class="mx-2">|</span>
                            <span>${{ number_format($customer->total_spent ?? 0, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="border-t dark:border-gray-700 pt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Audience Details</h4>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <div>
                <dt class="text-gray-500">Type</dt>
                <dd class="text-gray-900 dark:text-white">{{ $audience->getTypeLabel() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Platform</dt>
                <dd class="text-gray-900 dark:text-white">{{ $audience->getPlatformName() }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Status</dt>
                <dd class="text-gray-900 dark:text-white capitalize">{{ $audience->status }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Auto-Sync</dt>
                <dd class="text-gray-900 dark:text-white">{{ $audience->is_auto_sync ? 'Yes (' . $audience->sync_frequency . ')' : 'No' }}</dd>
            </div>
        </dl>
    </div>
</div>
