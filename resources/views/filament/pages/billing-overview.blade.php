<x-filament-panels::page>
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Tenants</div>
            <div class="text-3xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $tenants->count() }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue Billings</div>
            <div class="text-3xl font-bold text-danger-600 dark:text-danger-400 mt-2">{{ $tenants->where('is_overdue', true)->count() }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Due This Week</div>
            <div class="text-3xl font-bold text-warning-600 dark:text-warning-400 mt-2">{{ $tenants->where('is_due_soon', true)->count() }}</div>
        </div>
    </div>

    {{-- Expected Revenue Summary --}}
    @php
        $totals = $this->getTotalExpectedRevenue();
    @endphp
    @if(count($totals) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Expected Revenue (Current Period)</h3>
            <div class="flex flex-wrap gap-3">
                @foreach($totals as $currency => $amount)
                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-success-100 text-success-800 dark:bg-success-800 dark:text-success-100">
                        {{ number_format($amount, 2) }} {{ $currency }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tenants Billing Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tenants Billing Schedule</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tenant</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gross Revenue</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Commission</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Expected Invoice</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Billing Period</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Next Billing</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Countdown</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tenants as $tenant)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            {{-- Tenant Name --}}
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $tenant['public_name'] ?? $tenant['name'] }}
                                </div>
                                @if($tenant['public_name'] && $tenant['public_name'] !== $tenant['name'])
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant['name'] }}</div>
                                @endif
                            </td>

                            {{-- Gross Revenue --}}
                            <td class="px-6 py-4 text-right text-gray-900 dark:text-white">
                                {{ number_format($tenant['gross_revenue'], 2) }} {{ $tenant['currency'] }}
                            </td>

                            {{-- Commission Rate --}}
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                    {{ $tenant['commission_rate'] }}%
                                </span>
                            </td>

                            {{-- Expected Invoice Amount --}}
                            <td class="px-6 py-4 text-right">
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($tenant['expected_amount'], 2) }} {{ $tenant['currency'] }}
                                </span>
                            </td>

                            {{-- Billing Period --}}
                            <td class="px-6 py-4 text-center">
                                <div class="text-gray-900 dark:text-white">
                                    {{ $tenant['period_start']->format('M d') }} - {{ $tenant['next_billing_date']->format('M d, Y') }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">({{ $tenant['billing_cycle_days'] }} days)</div>
                            </td>

                            {{-- Next Billing Date --}}
                            <td class="px-6 py-4 text-center text-gray-900 dark:text-white">
                                {{ $tenant['next_billing_date']->format('M d, Y') }}
                            </td>

                            {{-- Countdown --}}
                            <td class="px-6 py-4 text-center">
                                @if($tenant['is_overdue'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-800 dark:text-danger-100">
                                        {{ abs($tenant['days_until_billing']) }} days OVERDUE
                                    </span>
                                @elseif($tenant['is_due_soon'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-800 dark:text-warning-100">
                                        {{ $tenant['days_until_billing'] }} days
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200">
                                        {{ $tenant['days_until_billing'] }} days
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No active tenants with billing dates configured.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Info Section --}}
    <div class="mt-6 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 text-sm text-gray-600 dark:text-gray-400">
        <p class="mb-2">
            <strong class="text-gray-900 dark:text-white">Note:</strong> Expected invoice amounts are calculated based on current period revenue (from last billing date to today).
        </p>
        <div class="flex flex-wrap gap-4 mt-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-800 dark:text-danger-100">OVERDUE</span>
                <span>Next billing date has passed</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-800 dark:text-warning-100">DUE SOON</span>
                <span>Next billing within 7 days</span>
            </div>
        </div>
        <p class="mt-3">
            Run <code class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs font-mono">php artisan invoices:generate-tenant</code> to generate invoices for all overdue tenants.
        </p>
    </div>
</x-filament-panels::page>
