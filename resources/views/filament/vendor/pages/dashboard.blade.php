<x-filament-panels::page>
    @if(!$hasEdition)
        <div class="text-center py-12">
            <x-heroicon-o-calendar-days class="w-16 h-16 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Active Edition</h3>
            <p class="text-gray-500 dark:text-gray-400 mt-1">There is no active festival edition for your vendor.</p>
        </div>
    @else
        <div class="space-y-6">
            {{-- Welcome --}}
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Welcome, <span class="font-medium text-gray-900 dark:text-white">{{ $employee->full_name ?? $employee->name }}</span>
                &middot; {{ $edition->name }} {{ $edition->year }}
            </div>

            {{-- Today's Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-filament::section>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($todayStats->sales_count) }}</div>
                        <div class="text-sm text-gray-500">Sales Today</div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($todayStats->revenue_cents / 100, 2) }}</div>
                        <div class="text-sm text-gray-500">Revenue Today (RON)</div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($todayStats->items_sold) }}</div>
                        <div class="text-sm text-gray-500">Items Sold</div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($todayStats->tips_cents / 100, 2) }}</div>
                        <div class="text-sm text-gray-500">Tips Today (RON)</div>
                    </div>
                </x-filament::section>
            </div>

            {{-- Edition Stats (Manager/Supervisor only) --}}
            @if($editionStats && !$isMember)
                <x-filament::section heading="Edition Totals">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-xl font-semibold">{{ number_format($editionStats->sales_count) }}</div>
                            <div class="text-sm text-gray-500">Total Sales</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-semibold">{{ number_format($editionStats->revenue_cents / 100, 2) }}</div>
                            <div class="text-sm text-gray-500">Total Revenue (RON)</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-semibold">{{ number_format($editionStats->commission_cents / 100, 2) }}</div>
                            <div class="text-sm text-gray-500">Commission (RON)</div>
                        </div>
                        <div class="text-center">
                            <div class="text-xl font-semibold">{{ number_format(($editionStats->revenue_cents - $editionStats->commission_cents) / 100, 2) }}</div>
                            <div class="text-sm text-gray-500">Net Revenue (RON)</div>
                        </div>
                    </div>
                </x-filament::section>
            @endif

            {{-- Top Products --}}
            @if($topProducts->isNotEmpty())
                <x-filament::section heading="Top Products Today">
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($topProducts as $product)
                            <div class="flex justify-between items-center py-2">
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $product->product_name }}</span>
                                    <span class="text-sm text-gray-500 ml-2">x{{ $product->total_qty }}</span>
                                </div>
                                <span class="font-mono text-sm">{{ number_format($product->total_cents / 100, 2) }} RON</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
