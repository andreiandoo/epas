<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Financial Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Available Balance --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-wallet class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Available Balance</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">
                            {{ number_format($organizer->available_balance, 2) }} RON
                        </p>
                    </div>
                </div>
            </div>

            {{-- Pending Payouts --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 dark:bg-yellow-900/50 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-clock class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Pending Payouts</p>
                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">
                            {{ number_format($organizer->pending_balance, 2) }} RON
                        </p>
                    </div>
                </div>
            </div>

            {{-- Total Paid Out --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Paid Out</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">
                            {{ number_format($organizer->total_paid_out, 2) }} RON
                        </p>
                    </div>
                </div>
            </div>

            {{-- Total Revenue --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($organizer->total_revenue, 2) }} RON
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bank Info --}}
        @if($organizer->bank_name || $organizer->iban)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-building-library class="w-5 h-5 text-gray-400" />
                    <div class="flex gap-6 text-sm">
                        @if($organizer->bank_name)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Bank:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $organizer->bank_name }}</span>
                            </div>
                        @endif
                        @if($organizer->iban)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">IBAN:</span>
                                <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $organizer->iban }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Revenue per Event --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-calendar class="w-5 h-5 text-gray-400" />
                    Revenue per Event
                </h3>
            </div>
            @if($revenuePerEvent->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-chart-bar class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No completed orders yet.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Event</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Orders</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Gross Revenue</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Commission</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Net Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($revenuePerEvent as $row)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ $row->marketplaceEvent?->title ?? 'Event #' . $row->marketplace_event_id }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-gray-600 dark:text-gray-300">
                                    {{ $row->orders_count }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($row->gross_revenue, 2) }} RON
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">
                                    -{{ number_format($row->total_commission, 2) }} RON
                                </td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-green-600 dark:text-green-400">
                                    {{ number_format($row->net_revenue, 2) }} RON
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <td class="px-6 py-3 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ $revenuePerEvent->sum('orders_count') }}</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ number_format($revenuePerEvent->sum('gross_revenue'), 2) }} RON</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-red-600 dark:text-red-400">-{{ number_format($revenuePerEvent->sum('total_commission'), 2) }} RON</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ number_format($revenuePerEvent->sum('net_revenue'), 2) }} RON</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>

        {{-- Payout History --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-gray-400" />
                    Payout History
                </h3>
            </div>
            @if($payouts->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-banknotes class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No payouts yet.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payment Ref</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($payouts as $payout)
                            <tr>
                                <td class="px-6 py-3 text-sm font-mono">
                                    <a href="{{ url('/marketplace/payouts/' . $payout->id) }}" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">
                                        {{ $payout->reference }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                    {{ number_format($payout->amount, 2) }} {{ $payout->currency ?? 'RON' }}
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                                            'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                                            'processing' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300',
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                            'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        ];
                                        $color = $statusColors[$payout->status] ?? $statusColors['cancelled'];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $color }}">
                                        {{ ucfirst($payout->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->payment_reference ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->created_at?->format('d M Y') }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->completed_at?->format('d M Y') ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-filament-panels::page>
