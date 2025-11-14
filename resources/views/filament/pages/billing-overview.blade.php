<x-filament::page>
    {{-- Summary Cards --}}
    <div class="billing-overview-grid">
        <div class="billing-stat-card">
            <div class="billing-stat-label">Active Tenants</div>
            <div class="billing-stat-value text-primary">{{ $tenants->count() }}</div>
        </div>

        <div class="billing-stat-card">
            <div class="billing-stat-label">Overdue Billings</div>
            <div class="billing-stat-value text-danger">{{ $tenants->where('is_overdue', true)->count() }}</div>
        </div>

        <div class="billing-stat-card">
            <div class="billing-stat-label">Due This Week</div>
            <div class="billing-stat-value text-warning">{{ $tenants->where('is_due_soon', true)->count() }}</div>
        </div>
    </div>

    {{-- Expected Revenue Summary --}}
    @php
        $totals = $this->getTotalExpectedRevenue();
    @endphp
    @if(count($totals) > 0)
        <div class="billing-revenue-section">
            <div class="billing-revenue-title">Expected Revenue (Current Period)</div>
            <div class="billing-revenue-grid">
                @foreach($totals as $currency => $amount)
                    <div class="billing-revenue-badge">
                        {{ number_format($amount, 2) }} {{ $currency }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tenants Billing Table --}}
    <div class="billing-table-container">
        <div class="billing-table-header">
            <div class="billing-table-title">Tenants Billing Schedule</div>
        </div>
        <div class="overflow-x-auto">
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th class="text-right">Gross Revenue</th>
                        <th class="text-center">Commission</th>
                        <th class="text-right">Expected Invoice</th>
                        <th class="text-center">Billing Period</th>
                        <th class="text-center">Next Billing</th>
                        <th class="text-center">Countdown</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenants as $tenant)
                        <tr>
                            {{-- Tenant Name --}}
                            <td>
                                <div class="billing-tenant-name">
                                    {{ $tenant['public_name'] ?? $tenant['name'] }}
                                </div>
                                @if($tenant['public_name'] && $tenant['public_name'] !== $tenant['name'])
                                    <div class="billing-tenant-legal">{{ $tenant['name'] }}</div>
                                @endif
                            </td>

                            {{-- Gross Revenue --}}
                            <td class="text-right">
                                {{ number_format($tenant['gross_revenue'], 2) }} {{ $tenant['currency'] }}
                            </td>

                            {{-- Commission Rate --}}
                            <td class="text-center">
                                <span class="billing-commission-badge">
                                    {{ $tenant['commission_rate'] }}%
                                </span>
                            </td>

                            {{-- Expected Invoice Amount --}}
                            <td class="text-right">
                                <span class="billing-amount">
                                    {{ number_format($tenant['expected_amount'], 2) }} {{ $tenant['currency'] }}
                                </span>
                            </td>

                            {{-- Billing Period --}}
                            <td class="text-center">
                                <span class="billing-period">
                                    {{ $tenant['period_start']->format('M d') }} - {{ $tenant['next_billing_date']->format('M d, Y') }}
                                </span>
                                <span class="billing-period-days">({{ $tenant['billing_cycle_days'] }} days)</span>
                            </td>

                            {{-- Next Billing Date --}}
                            <td class="text-center">
                                {{ $tenant['next_billing_date']->format('M d, Y') }}
                            </td>

                            {{-- Countdown --}}
                            <td class="text-center">
                                @if($tenant['is_overdue'])
                                    <span class="billing-countdown overdue">
                                        {{ abs($tenant['days_until_billing']) }} days OVERDUE
                                    </span>
                                @elseif($tenant['is_due_soon'])
                                    <span class="billing-countdown due-soon">
                                        {{ $tenant['days_until_billing'] }} days
                                    </span>
                                @else
                                    <span class="billing-countdown normal">
                                        {{ $tenant['days_until_billing'] }} days
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: #6b7280;">
                                No active tenants with billing dates configured.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Info Section --}}
    <div class="billing-info-section">
        <p class="billing-info-text">
            <strong>Note:</strong> Expected invoice amounts are calculated based on current period revenue (from last billing date to today).
        </p>
        <p class="billing-info-text">
            <span class="billing-info-badge overdue">OVERDUE</span>
            Next billing date has passed
        </p>
        <p class="billing-info-text">
            <span class="billing-info-badge due-soon">DUE SOON</span>
            Next billing within 7 days
        </p>
        <p class="billing-info-text">
            Run <code class="billing-info-code">php artisan invoices:generate-tenant</code> to generate invoices for all overdue tenants.
        </p>
    </div>
</x-filament::page>
