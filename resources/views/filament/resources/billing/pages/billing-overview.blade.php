<x-filament::page>
    {{-- Summary Cards --}}
    <div class="grid gap-4 mb-6 md:grid-cols-3">
        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Tenants</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $tenants->count() }}</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue Billings</div>
                <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">
                    {{ $tenants->where('is_overdue', true)->count() }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="p-4 text-center">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Due This Week</div>
                <div class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                    {{ $tenants->where('is_due_soon', true)->count() }}
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Expected Revenue Summary --}}
    @php
        $totals = $this->getTotalExpectedRevenue();
    @endphp
    @if(count($totals) > 0)
        <x-filament::section heading="Expected Revenue (Current Period)" class="mb-6">
            <div class="flex flex-wrap gap-4">
                @foreach($totals as $currency => $amount)
                    <div class="px-4 py-2 bg-gray-100 rounded-lg dark:bg-gray-800">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($amount, 2) }} {{ $currency }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Tenants Billing Table --}}
    <x-filament::section heading="Tenants Billing Schedule">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3">Tenant</th>
                        <th class="px-4 py-3 text-right">Gross Revenue</th>
                        <th class="px-4 py-3 text-center">Commission</th>
                        <th class="px-4 py-3 text-right">Expected Invoice</th>
                        <th class="px-4 py-3 text-center">Billing Period</th>
                        <th class="px-4 py-3 text-center">Next Billing</th>
                        <th class="px-4 py-3 text-center">Countdown</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                            {{-- Tenant Name --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $tenant['public_name'] ?? $tenant['name'] }}
                                </div>
                                @if($tenant['public_name'] && $tenant['public_name'] !== $tenant['name'])
                                    <div class="text-xs text-gray-500">{{ $tenant['name'] }}</div>
                                @endif
                            </td>

                            {{-- Gross Revenue --}}
                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                {{ number_format($tenant['gross_revenue'], 2) }} {{ $tenant['currency'] }}
                            </td>

                            {{-- Commission Rate --}}
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    {{ $tenant['commission_rate'] }}%
                                </span>
                            </td>

                            {{-- Expected Invoice Amount --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-bold text-gray-900 dark:text-white">
                                    {{ number_format($tenant['expected_amount'], 2) }} {{ $tenant['currency'] }}
                                </span>
                            </td>

                            {{-- Billing Period --}}
                            <td class="px-4 py-3 text-center text-xs text-gray-600 dark:text-gray-400">
                                {{ $tenant['period_start']->format('M d') }} - {{ $tenant['next_billing_date']->format('M d, Y') }}
                                <div class="text-gray-500">({{ $tenant['billing_cycle_days'] }} days)</div>
                            </td>

                            {{-- Next Billing Date --}}
                            <td class="px-4 py-3 text-center text-gray-900 dark:text-white">
                                {{ $tenant['next_billing_date']->format('M d, Y') }}
                            </td>

                            {{-- Countdown --}}
                            <td class="px-4 py-3 text-center">
                                @if($tenant['is_overdue'])
                                    <span class="px-3 py-1 text-sm font-bold text-white bg-red-600 rounded-full">
                                        {{ abs($tenant['days_until_billing']) }} days OVERDUE
                                    </span>
                                @elseif($tenant['is_due_soon'])
                                    <span class="px-3 py-1 text-sm font-bold text-white bg-yellow-500 rounded-full">
                                        {{ $tenant['days_until_billing'] }} days
                                    </span>
                                @else
                                    <span class="px-3 py-1 text-sm font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                        {{ $tenant['days_until_billing'] }} days
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No active tenants with billing dates configured.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Info Section --}}
    <x-filament::section class="mt-6">
        <div class="p-4 text-sm text-gray-600 dark:text-gray-400">
            <p class="mb-2">
                <strong>Note:</strong> Expected invoice amounts are calculated based on current period revenue (from last billing date to today).
            </p>
            <p class="mb-2">
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 mr-2">OVERDUE</span>
                Next billing date has passed
            </p>
            <p class="mb-2">
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 mr-2">DUE SOON</span>
                Next billing within 7 days
            </p>
            <p>
                Run <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-800 rounded">php artisan invoices:generate-tenant</code> to generate invoices for all overdue tenants.
            </p>
        </div>
    </x-filament::section>
</x-filament::page>
