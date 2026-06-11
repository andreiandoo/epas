<x-filament-panels::page>
    {{-- Summary Stats Cards - Row 1 --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Active Tenants --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <x-heroicon-o-building-office class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $tenants->count() }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tenants</p>
                </div>
            </div>
        </div>

        {{-- Marketplace Clients --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <x-heroicon-o-building-storefront class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $marketplaceClients->count() }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Marketplace</p>
                </div>
            </div>
        </div>

        {{-- Overdue Billings --}}
        @php
            $totalOverdue = $tenants->where('is_overdue', true)->count() + $marketplaceClients->where('is_overdue', true)->count();
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="p-2.5 {{ $totalOverdue > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-gray-100 dark:bg-gray-700' }} rounded-lg">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 {{ $totalOverdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}" />
                </div>
                <div>
                    <p class="text-2xl font-bold {{ $totalOverdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ $totalOverdue }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Overdue</p>
                </div>
            </div>
        </div>

        {{-- Due This Week --}}
        @php
            $totalDueSoon = $tenants->where('is_due_soon', true)->count() + $marketplaceClients->where('is_due_soon', true)->count();
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center gap-3">
                <div class="p-2.5 {{ $totalDueSoon > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700' }} rounded-lg">
                    <x-heroicon-o-clock class="w-5 h-5 {{ $totalDueSoon > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}" />
                </div>
                <div>
                    <p class="text-2xl font-bold {{ $totalDueSoon > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $totalDueSoon }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Due This Week</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Financial Stats Cards - Row 2 --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Unpaid Invoices --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Unpaid Invoices</h3>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $stats['unpaid_count'] > 0 ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                    {{ $stats['unpaid_count'] }} {{ $stats['unpaid_count'] === 1 ? 'invoice' : 'invoices' }}
                </span>
            </div>
            @if(count($stats['unpaid_total']) > 0)
                <div class="space-y-1">
                    @foreach($stats['unpaid_total'] as $currency => $amount)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                            <span class="text-lg font-bold text-red-600 dark:text-red-400">{{ number_format($amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No unpaid invoices</p>
            @endif
        </div>

        {{-- Expected Revenue (Current Period) --}}
        @php
            $expectedTotals = $this->getTotalExpectedRevenue();
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Expected Commission</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">Current period</span>
            </div>
            @if(count($expectedTotals) > 0)
                <div class="space-y-1">
                    @foreach($expectedTotals as $currency => $amount)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                            <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No data</p>
            @endif
        </div>

        {{-- Total Gross Revenue (Current Period) --}}
        @php
            $grossTotals = $this->getTotalGrossRevenue();
        @endphp
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Gross Revenue</h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">Current period</span>
            </div>
            @if(count($grossTotals) > 0)
                <div class="space-y-1">
                    @foreach($grossTotals as $currency => $amount)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($amount, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No data</p>
            @endif
        </div>
    </div>

    {{-- This Month Stats --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">This Month ({{ now()->format('F Y') }})</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Platform Revenue --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Platform Revenue</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['monthly_revenue'], 2) }} <span class="text-sm font-normal text-gray-500">RON</span></p>
            </div>
            {{-- Invoiced --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Invoiced</p>
                @if(count($stats['monthly_invoiced']) > 0)
                    @foreach($stats['monthly_invoiced'] as $currency => $amount)
                        <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($amount, 2) }} <span class="text-sm font-normal text-gray-500">{{ $currency }}</span></p>
                    @endforeach
                @else
                    <p class="text-xl font-bold text-gray-400">0.00</p>
                @endif
            </div>
            {{-- Paid --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Paid</p>
                @if(count($stats['monthly_paid']) > 0)
                    @foreach($stats['monthly_paid'] as $currency => $amount)
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($amount, 2) }} <span class="text-sm font-normal text-gray-500">{{ $currency }}</span></p>
                    @endforeach
                @else
                    <p class="text-xl font-bold text-gray-400">0.00</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Tenants Billing Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-heroicon-o-building-office class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tenants</h3>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $tenants->count() }} tenants</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Tenant</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Gross Revenue</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Commission</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Expected</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Period</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Last Billing</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Next Billing</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Unpaid</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tenants as $tenant)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            {{-- Tenant Name --}}
                            <td class="px-4 py-3">
                                <a href="{{ \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $tenant['id']]) }}" class="group">
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                        {{ $tenant['public_name'] ?? $tenant['name'] }}
                                    </div>
                                    @if($tenant['public_name'] && $tenant['public_name'] !== $tenant['name'])
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant['name'] }}</div>
                                    @endif
                                </a>
                            </td>

                            {{-- Gross Revenue --}}
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                {{ number_format($tenant['gross_revenue'], 2) }} <span class="text-gray-500">{{ $tenant['currency'] }}</span>
                            </td>

                            {{-- Commission Rate --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200">
                                    {{ $tenant['commission_rate'] }}%
                                </span>
                            </td>

                            {{-- Expected Invoice Amount --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-semibold text-green-600 dark:text-green-400">
                                    {{ number_format($tenant['expected_amount'], 2) }} <span class="text-gray-500 font-normal">{{ $tenant['currency'] }}</span>
                                </span>
                            </td>

                            {{-- Billing Period --}}
                            <td class="px-4 py-3 text-center">
                                @if($tenant['has_billing'] && $tenant['period_start'])
                                    <div class="text-gray-900 dark:text-white text-xs">
                                        {{ $tenant['period_start']->format('M d') }} - {{ $tenant['next_billing_date']->format('M d') }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">({{ $tenant['billing_cycle_days'] }}d)</div>
                                @else
                                    <span class="text-gray-400 text-xs">Not configured</span>
                                @endif
                            </td>

                            {{-- Last Billing Date --}}
                            <td class="px-4 py-3 text-center">
                                @if($tenant['last_billing_date'])
                                    <span class="text-gray-900 dark:text-white">{{ $tenant['last_billing_date']->format('M d, Y') }}</span>
                                @else
                                    <span class="text-gray-400 text-xs">Never</span>
                                @endif
                            </td>

                            {{-- Next Billing Date --}}
                            <td class="px-4 py-3 text-center">
                                @if($tenant['has_billing'])
                                    <span class="text-gray-900 dark:text-white">{{ $tenant['next_billing_date']->format('M d, Y') }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            {{-- Status / Countdown --}}
                            <td class="px-4 py-3 text-center">
                                @if(!$tenant['has_billing'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        No billing
                                    </span>
                                @elseif($tenant['is_overdue'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">
                                        {{ abs($tenant['days_until_billing']) }}d OVERDUE
                                    </span>
                                @elseif($tenant['is_due_soon'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                                        {{ $tenant['days_until_billing'] }}d left
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300">
                                        {{ $tenant['days_until_billing'] }}d left
                                    </span>
                                @endif
                            </td>

                            {{-- Unpaid Invoices --}}
                            <td class="px-4 py-3 text-center">
                                @if($tenant['unpaid_invoices_count'] > 0)
                                    <a href="{{ \App\Filament\Resources\Billing\InvoiceResource::getUrl('index', ['tableFilters[tenant_id][value]' => $tenant['id'], 'tableFilters[status][value]' => 'outstanding']) }}"
                                       class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">
                                        {{ $tenant['unpaid_invoices_count'] }} ({{ number_format($tenant['unpaid_invoices_total'], 2) }} {{ $tenant['currency'] }})
                                    </a>
                                @else
                                    <span class="text-green-600 dark:text-green-400 text-xs">-</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if($tenant['has_billing'] && $tenant['is_overdue'])
                                        <button wire:click="generateProformaInvoice({{ $tenant['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="generateProformaInvoice({{ $tenant['id'] }})"
                                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-primary-600 rounded hover:bg-primary-700 disabled:opacity-50"
                                                title="Generate Proforma Invoice">
                                            <x-heroicon-o-document-plus class="w-3.5 h-3.5" />
                                            <span wire:loading.remove wire:target="generateProformaInvoice({{ $tenant['id'] }})">Invoice</span>
                                            <span wire:loading wire:target="generateProformaInvoice({{ $tenant['id'] }})">...</span>
                                        </button>
                                    @endif
                                    <a href="{{ \App\Filament\Pages\ClientEarnings::getUrl(['type' => 'tenant', 'id' => $tenant['id']]) }}"
                                       class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                       title="View Earnings">
                                        <x-heroicon-o-chart-bar class="w-4 h-4" />
                                    </a>
                                    <a href="{{ \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $tenant['id']]) }}"
                                       class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                       title="Edit Tenant">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <a href="{{ \App\Filament\Resources\Billing\InvoiceResource::getUrl('index', ['tableFilters[tenant_id][value]' => $tenant['id']]) }}"
                                       class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                       title="View Invoices">
                                        <x-heroicon-o-document-text class="w-4 h-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <p class="text-sm">No active tenants found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Marketplace Clients Billing Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-heroicon-o-building-storefront class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Marketplace Clients</h3>
            </div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $marketplaceClients->count() }} clients</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Client</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Gross Revenue</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Commission</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Expected</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Period</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Last Billing</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Next Billing</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Unpaid</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($marketplaceClients as $client)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            {{-- Client Name --}}
                            <td class="px-4 py-3">
                                <a href="{{ \App\Filament\Resources\MarketplaceClientResource::getUrl('edit', ['record' => $client['id']]) }}" class="group">
                                    <div class="font-medium text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                        {{ $client['name'] }}
                                    </div>
                                    @if($client['domain'])
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $client['domain'] }}</div>
                                    @endif
                                </a>
                            </td>

                            {{-- Gross Revenue --}}
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                {{ number_format($client['gross_revenue'], 2) }} <span class="text-gray-500">{{ $client['currency'] }}</span>
                            </td>

                            {{-- Commission Rate --}}
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-200">
                                    {{ $client['commission_rate'] }}%
                                </span>
                            </td>

                            {{-- Expected Invoice Amount --}}
                            <td class="px-4 py-3 text-right">
                                <span class="font-semibold text-green-600 dark:text-green-400">
                                    {{ number_format($client['expected_amount'], 2) }} <span class="text-gray-500 font-normal">{{ $client['currency'] }}</span>
                                </span>
                            </td>

                            {{-- Billing Period --}}
                            <td class="px-4 py-3 text-center">
                                @if($client['has_billing'] && $client['period_start'])
                                    <div class="text-gray-900 dark:text-white text-xs">
                                        {{ $client['period_start']->format('M d') }} - {{ $client['next_billing_date']->format('M d') }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">({{ $client['billing_cycle_days'] }}d)</div>
                                @else
                                    <span class="text-gray-400 text-xs">Not configured</span>
                                @endif
                            </td>

                            {{-- Last Billing Date --}}
                            <td class="px-4 py-3 text-center">
                                @if($client['last_billing_date'])
                                    <span class="text-gray-900 dark:text-white">{{ $client['last_billing_date']->format('M d, Y') }}</span>
                                @else
                                    <span class="text-gray-400 text-xs">Never</span>
                                @endif
                            </td>

                            {{-- Next Billing Date --}}
                            <td class="px-4 py-3 text-center">
                                @if($client['has_billing'])
                                    <span class="text-gray-900 dark:text-white">{{ $client['next_billing_date']->format('M d, Y') }}</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>

                            {{-- Status / Countdown --}}
                            <td class="px-4 py-3 text-center">
                                @if(!$client['has_billing'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        No billing
                                    </span>
                                @elseif($client['is_overdue'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">
                                        {{ abs($client['days_until_billing']) }}d OVERDUE
                                    </span>
                                @elseif($client['is_due_soon'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">
                                        {{ $client['days_until_billing'] }}d left
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-300">
                                        {{ $client['days_until_billing'] }}d left
                                    </span>
                                @endif
                            </td>

                            {{-- Unpaid Invoices --}}
                            <td class="px-4 py-3 text-center">
                                @if($client['unpaid_invoices_count'] > 0)
                                    <a href="{{ \App\Filament\Resources\Billing\InvoiceResource::getUrl('index', ['tableFilters[marketplace_client_id][value]' => $client['id'], 'tableFilters[status][value]' => 'outstanding']) }}"
                                       class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">
                                        {{ $client['unpaid_invoices_count'] }} ({{ number_format($client['unpaid_invoices_total'], 2) }} {{ $client['currency'] }})
                                    </a>
                                @else
                                    <span class="text-green-600 dark:text-green-400 text-xs">-</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if($client['has_billing'] && $client['is_overdue'])
                                        <button wire:click="generateMarketplaceProformaInvoice({{ $client['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="generateMarketplaceProformaInvoice({{ $client['id'] }})"
                                                class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-white bg-primary-600 rounded hover:bg-primary-700 disabled:opacity-50"
                                                title="Generate Proforma Invoice">
                                            <x-heroicon-o-document-plus class="w-3.5 h-3.5" />
                                            <span wire:loading.remove wire:target="generateMarketplaceProformaInvoice({{ $client['id'] }})">Invoice</span>
                                            <span wire:loading wire:target="generateMarketplaceProformaInvoice({{ $client['id'] }})">...</span>
                                        </button>
                                    @endif
                                    <a href="{{ \App\Filament\Pages\ClientEarnings::getUrl(['type' => 'marketplace', 'id' => $client['id']]) }}"
                                       class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                       title="View Earnings">
                                        <x-heroicon-o-chart-bar class="w-4 h-4" />
                                    </a>
                                    <a href="{{ \App\Filament\Resources\MarketplaceClientResource::getUrl('edit', ['record' => $client['id']]) }}"
                                       class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                       title="Edit Client">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </a>
                                    <a href="{{ \App\Filament\Resources\Billing\InvoiceResource::getUrl('index', ['tableFilters[marketplace_client_id][value]' => $client['id']]) }}"
                                       class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                       title="View Invoices">
                                        <x-heroicon-o-document-text class="w-4 h-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <p class="text-sm">No active marketplace clients found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Legend / Help Section --}}
    <div class="mt-6 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 text-sm text-gray-600 dark:text-gray-400">
        <p class="mb-3">
            <strong class="text-gray-900 dark:text-white">Note:</strong> Expected commission is calculated as Gross Revenue x Commission Rate for tenants, or from pre-calculated commission amounts for marketplace clients.
        </p>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">OVERDUE</span>
                <span>Billing date has passed</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">DUE SOON</span>
                <span>Within 7 days</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">No billing</span>
                <span>Billing not configured</span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
