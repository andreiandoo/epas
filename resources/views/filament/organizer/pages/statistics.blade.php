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
        $commission = $data['commission'];
        $events = $data['events'];
        $payouts = $data['payouts'];
        $ticketTypes = $data['ticketTypes'];
    @endphp

    {{-- Overview Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ number_format($overview['total_orders'] ?? 0) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Orders</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ number_format($overview['organizer_revenue'] ?? 0, 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Your Revenue</div>
                @if(($overview['revenue_change'] ?? 0) != 0)
                    <div class="text-xs mt-1 {{ $overview['revenue_change'] > 0 ? 'text-green-500' : 'text-red-500' }}">
                        {{ $overview['revenue_change'] > 0 ? '+' : '' }}{{ number_format($overview['revenue_change'], 1) }}% vs previous period
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ number_format($overview['total_tickets'] ?? 0) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tickets Sold</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ number_format($overview['avg_order_value'] ?? 0, 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Avg Order Value</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Commission Breakdown --}}
    <x-filament::section>
        <x-slot name="heading">
            Revenue Breakdown
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Gross Revenue</div>
                <div class="text-2xl font-bold">{{ number_format($commission['gross_revenue'] ?? 0, 2) }} RON</div>
                <div class="text-xs text-gray-500">100%</div>
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Tixello Platform Fee</div>
                <div class="text-2xl font-bold text-red-600">-{{ number_format($commission['tixello_fee'] ?? 0, 2) }} RON</div>
                <div class="text-xs text-gray-500">{{ number_format($commission['tixello_percent'] ?? 0, 1) }}%</div>
            </div>

            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Marketplace Commission</div>
                <div class="text-2xl font-bold text-orange-600">-{{ number_format($commission['marketplace_fee'] ?? 0, 2) }} RON</div>
                <div class="text-xs text-gray-500">{{ number_format($commission['marketplace_percent'] ?? 0, 1) }}%</div>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">Your Net Revenue</div>
                <div class="text-2xl font-bold text-green-600">{{ number_format($commission['net_revenue'] ?? 0, 2) }} RON</div>
                <div class="text-xs text-gray-500">{{ number_format($commission['net_percent'] ?? 0, 1) }}%</div>
            </div>
        </div>

        {{-- Visual breakdown --}}
        @php
            $total = ($commission['gross_revenue'] ?? 0) > 0 ? $commission['gross_revenue'] : 1;
            $tixelloPercent = (($commission['tixello_fee'] ?? 0) / $total) * 100;
            $marketplacePercent = (($commission['marketplace_fee'] ?? 0) / $total) * 100;
            $netPercent = (($commission['net_revenue'] ?? 0) / $total) * 100;
        @endphp
        <div class="mt-6">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">Revenue Distribution</div>
            <div class="flex h-8 rounded-full overflow-hidden">
                <div class="bg-red-500 flex items-center justify-center text-white text-xs" style="width: {{ $tixelloPercent }}%">
                    @if($tixelloPercent > 5) {{ number_format($tixelloPercent, 0) }}% @endif
                </div>
                <div class="bg-orange-500 flex items-center justify-center text-white text-xs" style="width: {{ $marketplacePercent }}%">
                    @if($marketplacePercent > 5) {{ number_format($marketplacePercent, 0) }}% @endif
                </div>
                <div class="bg-green-500 flex items-center justify-center text-white text-xs" style="width: {{ $netPercent }}%">
                    @if($netPercent > 10) {{ number_format($netPercent, 0) }}% @endif
                </div>
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-500">
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-red-500 rounded-full"></span> Platform Fee</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-orange-500 rounded-full"></span> Marketplace</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 bg-green-500 rounded-full"></span> Your Revenue</span>
            </div>
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Event Performance --}}
        <x-filament::section>
            <x-slot name="heading">
                Event Performance
            </x-slot>

            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white dark:bg-gray-800">
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Event</th>
                            <th class="text-right py-2">Tickets</th>
                            <th class="text-right py-2">Orders</th>
                            <th class="text-right py-2">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">
                                    <div>{{ Str::limit($event['name'], 25) }}</div>
                                    <div class="text-xs text-gray-500">{{ $event['date'] }}</div>
                                </td>
                                <td class="text-right py-2">{{ number_format($event['tickets_sold']) }}</td>
                                <td class="text-right py-2">{{ number_format($event['orders']) }}</td>
                                <td class="text-right py-2 text-green-600">{{ number_format($event['net_revenue'], 2) }}</td>
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

        {{-- Ticket Type Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">
                Ticket Types Sold
            </x-slot>

            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-white dark:bg-gray-800">
                        <tr class="border-b dark:border-gray-700">
                            <th class="text-left py-2">Ticket Type</th>
                            <th class="text-right py-2">Price</th>
                            <th class="text-right py-2">Sold</th>
                            <th class="text-right py-2">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ticketTypes as $type)
                            <tr class="border-b dark:border-gray-700">
                                <td class="py-2">
                                    <div>{{ Str::limit($type->name ?? 'N/A', 20) }}</div>
                                    <div class="text-xs text-gray-500">{{ Str::limit($type->event_name ?? '', 20) }}</div>
                                </td>
                                <td class="text-right py-2">{{ number_format($type->price ?? 0, 2) }}</td>
                                <td class="text-right py-2">{{ number_format($type->sold ?? 0) }}</td>
                                <td class="text-right py-2 text-green-600">{{ number_format($type->revenue ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">No ticket sales found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{-- Payout History --}}
    <x-filament::section>
        <x-slot name="heading">
            Recent Payouts
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b dark:border-gray-700">
                        <th class="text-left py-2">Reference</th>
                        <th class="text-left py-2">Period</th>
                        <th class="text-right py-2">Amount</th>
                        <th class="text-center py-2">Status</th>
                        <th class="text-right py-2">Processed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $payout)
                        <tr class="border-b dark:border-gray-700">
                            <td class="py-2 font-mono text-sm">{{ $payout['reference'] }}</td>
                            <td class="py-2 text-gray-600 dark:text-gray-400">{{ $payout['period'] }}</td>
                            <td class="text-right py-2 font-semibold text-green-600">{{ number_format($payout['amount'], 2) }} RON</td>
                            <td class="text-center py-2">
                                @php
                                    $statusColor = match($payout['status']) {
                                        'completed' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                        'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                                        'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                        'failed' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                                    };
                                @endphp
                                <span class="px-2 py-1 text-xs rounded-full {{ $statusColor }}">
                                    {{ ucfirst($payout['status']) }}
                                </span>
                            </td>
                            <td class="text-right py-2 text-gray-500">{{ $payout['processed_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-500">No payouts yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(count($payouts) > 0)
            <div class="mt-4">
                <a href="{{ route('filament.organizer.resources.payouts.index') }}" class="text-sm text-primary-600 hover:underline">
                    View all payouts &rarr;
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
