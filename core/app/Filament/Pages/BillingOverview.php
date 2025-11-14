<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Tenant;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class BillingOverview extends Page
{
    protected string $view = 'filament.pages.billing-overview';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Billing Overview';
    protected static ?string $title = 'Billing Overview';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 1;

    public Collection $tenants;

    public function mount(): void
    {
        $this->loadTenantData();
    }

    protected function loadTenantData(): void
    {
        $today = Carbon::today();

        $this->tenants = Tenant::query()
            ->where('status', 'active')
            ->whereNotNull('next_billing_date')
            ->orderBy('next_billing_date', 'asc')
            ->get()
            ->map(function (Tenant $tenant) use ($today) {
                // Calculate current billing period
                $periodEnd = $tenant->next_billing_date;
                $periodStart = $periodEnd->copy()->subDays($tenant->billing_cycle_days ?? 30);

                // Calculate gross revenue from orders in current period
                $grossRevenue = Order::where('tenant_id', $tenant->id)
                    ->whereBetween('created_at', [$periodStart, $today->endOfDay()])
                    ->where('status', '!=', 'cancelled')
                    ->sum('total');

                // Calculate expected invoice amount
                $commissionRate = $tenant->commission_rate ?? 0;
                $expectedAmount = round($grossRevenue * ($commissionRate / 100), 2);

                // Calculate days until next billing
                $daysUntilBilling = $today->diffInDays($periodEnd, false);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'public_name' => $tenant->public_name,
                    'commission_rate' => $commissionRate,
                    'currency' => $tenant->currency ?? 'RON',
                    'billing_cycle_days' => $tenant->billing_cycle_days ?? 30,
                    'next_billing_date' => $periodEnd,
                    'period_start' => $periodStart,
                    'gross_revenue' => $grossRevenue,
                    'expected_amount' => $expectedAmount,
                    'days_until_billing' => $daysUntilBilling,
                    'is_overdue' => $daysUntilBilling < 0,
                    'is_due_soon' => $daysUntilBilling >= 0 && $daysUntilBilling <= 7,
                ];
            });
    }

    public function getTotalExpectedRevenue(): array
    {
        $byCurrency = $this->tenants
            ->groupBy('currency')
            ->map(fn($group) => $group->sum('expected_amount'));

        return $byCurrency->toArray();
    }

    public function getHeading(): string
    {
        return 'Billing Overview';
    }

    public function getSubheading(): ?string
    {
        $count = $this->tenants->count();
        return "{$count} active tenant(s) · Next billing cycle summary";
    }
}
