<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Filter Statistics
        </x-slot>

        {{ $this->form }}
    </x-filament::section>

    @php
        $data = $this->getViewData();
        $overview = $data['overview'];
        $organizers = $data['organizers'];
        $payouts = $data['payouts'];
        $topEvents = $data['topEvents'];
    @endphp

    {{-- Overview Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ number_format($overview['total_orders']) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Orders</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ number_format($overview['total_gross_revenue'], 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Gross Revenue</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ number_format($overview['total_tickets']) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tickets Sold</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ number_format($overview['avg_order_value'], 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg Order Value</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Commission Breakdown --}}
    <x-filament::section>
        <x-slot name="heading">
            Commission Breakdown
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Tixello Platform Fee (1%)</div>
                        <div class="text-2xl font-bold text-red-600">{{ number_format($overview['tixello_commission'], 2) }} RON</div>
                    </div>
                    <div class="text-red-500">
                        <x-heroicon-o-building-office class="w-8 h-8" />
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Your Marketplace Commission</div>
                        <div class="text-2xl font-bold text-blue-600">{{ number_format($overview['marketplace_commission'], 2) }} RON</div>
                    </div>
                    <div class="text-blue-500">
                        <x-heroicon-o-banknotes class="w-8 h-8" />
                    </div>
                </div>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Organizer Revenue</div>
                        <div class="text-2xl font-bold text-green-600">{{ number_format($overview['organizer_revenue'], 2) }} RON</div>
                    </div>
                    <div class="text-green-500">
                        <x-heroicon-o-user-group class="w-8 h-8" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue Split Visualization --}}
        @php
            $total = $overview['total_gross_revenue'] > 0 ? $overview['total_gross_revenue'] : 1;
            $tixelloPercent = ($overview['tixello_commission'] / $total) * 100;
            $marketplacePercent = ($overview['marketplace_commission'] / $total) * 100;
            $organizerPercent = ($overview['organizer_revenue'] / $total) * 100;
        @endphp
        <div class="mt-4">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">Revenue Split</div>
            <div class="flex h-6 rounded-full overflow-hidden">
                <div class="bg-red-500" style="width: {{ $tixelloPercent }}%"></div>
                <div class="bg-blue-500" style="width: {{ $marketplacePercent }}%"></div>
                <div class="bg-green-500" style="width: {{ $organizerPercent }}%"></div>
            </div>
            <div class="flex justify-between mt-1 text-xs text-gray-500">
                <span>Tixello {{ number_format($tixelloPercent, 1) }}%</span>
                <span>Marketplace {{ number_format($marketplacePercent, 1) }}%</span>
                <span>Organizers {{ number_format($organizerPercent, 1) }}%</span>
            </div>
        </div>
    </x-filament::section>

    {{-- Payout Status --}}
    <x-filament::section>
        <x-slot name="heading">
            Payout Status
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Pending Payouts</div>
                <div class="text-xl font-bold text-yellow-600">{{ number_format($payouts['pending_amount'], 2) }} RON</div>
                <div class="text-xs text-gray-500">{{ $payouts['pending_count'] }} payouts</div>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Processing</div>
                <div class="text-xl font-bold text-blue-600">{{ number_format($payouts['processing_amount'], 2) }} RON</div>
                <div class="text-xs text-gray-500">{{ $payouts['processing_count'] }} payouts</div>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
                <div class="text-xl font-bold text-green-600">{{ number_format($payouts['completed_amount'], 2) }} RON</div>
                <div class="text-xs text-gray-500">{{ $payouts['completed_count'] }} payouts</div>
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Failed</div>
                <div class="text-xl font-bold text-red-600">{{ $payouts['failed_count'] }}</div>
                <div class="text-xs text-gray-500">payouts</div>
            </div>
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Organizers --}}
        <x-filament::section>
            <x-slot name="heading">
                Organizer Performance
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Organizer</th>
                            <th class="text-right py-2">Orders</th>
                            <th class="text-right py-2">Gross Revenue</th>
                            <th class="text-right py-2">Your Commission</th>
                            <th class="text-right py-2">Pending Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($organizers as $organizer)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $organizer['name'] }}</span>
                                        @if($organizer['is_verified'])
                                            <x-heroicon-s-check-badge class="w-4 h-4 text-blue-500" />
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right py-2">{{ number_format($organizer['orders_count']) }}</td>
                                <td class="text-right py-2">{{ number_format($organizer['gross_revenue'], 2) }}</td>
                                <td class="text-right py-2 text-blue-600">{{ number_format($organizer['marketplace_commission'], 2) }}</td>
                                <td class="text-right py-2 text-yellow-600">{{ number_format($organizer['pending_payout'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500">No organizers found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Top Events --}}
        <x-filament::section>
            <x-slot name="heading">
                Top Events
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Event</th>
                            <th class="text-left py-2">Organizer</th>
                            <th class="text-right py-2">Orders</th>
                            <th class="text-right py-2">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topEvents as $event)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">
                                    <div>{{ Str::limit($event['name'], 30) }}</div>
                                    <div class="text-xs text-gray-500">{{ $event['date'] }}</div>
                                </td>
                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $event['organizer'] }}</td>
                                <td class="text-right py-2">{{ number_format($event['orders']) }}</td>
                                <td class="text-right py-2 text-green-600">{{ number_format($event['revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">No events found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
