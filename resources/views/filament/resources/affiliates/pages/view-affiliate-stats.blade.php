<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Affiliate Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Affiliate Details</h2>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Code:</span>
                        <p class="font-medium">{{ $record->code }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Name:</span>
                        <p class="font-medium">{{ $record->name }}</p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Status:</span>
                        <p class="font-medium">
                            <span class="px-2 py-1 rounded-full text-xs
                                {{ $record->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $record->status === 'inactive' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $record->status === 'suspended' ? 'bg-red-100 text-red-800' : '' }}
                            ">
                                {{ ucfirst($record->status) }}
                            </span>
                        </p>
                    </div>
                    @if($record->contact_email)
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Email:</span>
                        <p class="font-medium">{{ $record->contact_email }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Tracking Links & Coupons</h2>
                <div class="space-y-3">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Coupons:</span>
                        @if($record->coupons->count() > 0)
                            <div class="mt-2 space-y-1">
                                @foreach($record->coupons as $coupon)
                                    <p class="font-medium text-sm">
                                        <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $coupon->coupon_code }}</code>
                                        @if($coupon->active)
                                            <span class="text-green-600 text-xs">(Active)</span>
                                        @else
                                            <span class="text-gray-400 text-xs">(Inactive)</span>
                                        @endif
                                    </p>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400">No coupons</p>
                        @endif
                    </div>
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Links:</span>
                        <p class="font-medium">{{ $record->links->count() }} link(s)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Conversions</h3>
                <p class="text-3xl font-bold mt-2">{{ $stats['total_conversions'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Approved Conversions</h3>
                <p class="text-3xl font-bold mt-2 text-green-600">{{ $stats['approved_conversions'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Commission</h3>
                <p class="text-3xl font-bold mt-2 text-green-600">{{ number_format($stats['total_commission'], 2) }} RON</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Commission</h3>
                <p class="text-3xl font-bold mt-2 text-yellow-600">{{ number_format($stats['pending_commission'], 2) }} RON</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</h3>
                <p class="text-2xl font-bold mt-2 text-yellow-600">{{ $stats['pending_conversions'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Reversed</h3>
                <p class="text-2xl font-bold mt-2 text-red-600">{{ $stats['reversed_conversions'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sales</h3>
                <p class="text-2xl font-bold mt-2">{{ number_format($stats['total_sales'], 2) }} RON</p>
            </div>
        </div>

        <!-- Conversions Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Conversion History</h2>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
