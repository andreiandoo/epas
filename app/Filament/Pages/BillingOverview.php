<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
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
    public array $stats = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadTenantData();
    }

    protected function loadStats(): void
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();

        // Total unpaid invoices
        $unpaidInvoices = Invoice::whereIn('status', ['pending', 'overdue', 'outstanding'])
            ->selectRaw('currency, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('currency')
            ->get();

        // Total revenue this month (from paid/confirmed orders)
        $monthlyRevenue = Order::whereIn('status', ['paid', 'confirmed'])
            ->where('created_at', '>=', $startOfMonth)
            ->selectRaw('SUM(total_cents) as total')
            ->first();

        // Total invoiced this month
        $monthlyInvoiced = Invoice::where('issue_date', '>=', $startOfMonth)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        // Paid invoices this month
        $monthlyPaid = Invoice::where('status', 'paid')
            ->where('issue_date', '>=', $startOfMonth)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        $this->stats = [
            'unpaid_invoices' => $unpaidInvoices,
            'unpaid_count' => $unpaidInvoices->sum('count'),
            'unpaid_total' => $unpaidInvoices->mapWithKeys(fn($item) => [$item->currency => $item->total])->toArray(),
            'monthly_revenue' => ($monthlyRevenue->total ?? 0) / 100,
            'monthly_invoiced' => $monthlyInvoiced->mapWithKeys(fn($item) => [$item->currency => $item->total])->toArray(),
            'monthly_paid' => $monthlyPaid->mapWithKeys(fn($item) => [$item->currency => $item->total])->toArray(),
        ];
    }

    protected function loadTenantData(): void
    {
        $today = Carbon::today();

        $this->tenants = Tenant::query()
            ->where('status', 'active')
            ->with(['invoices' => function ($query) {
                $query->orderBy('issue_date', 'desc')->limit(1);
            }])
            ->orderByRaw('CASE WHEN next_billing_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_billing_date', 'asc')
            ->get()
            ->map(function (Tenant $tenant) use ($today) {
                // Calculate current billing period
                $periodEnd = $tenant->next_billing_date;
                $billingCycleDays = $tenant->billing_cycle_days ?? 30;
                $periodStart = $periodEnd ? $periodEnd->copy()->subDays($billingCycleDays) : null;

                // Calculate gross revenue from orders in current period (total_cents / 100)
                $grossRevenueQuery = Order::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['paid', 'confirmed']);

                if ($periodStart) {
                    $grossRevenueQuery->where('created_at', '>=', $periodStart);
                }

                $grossRevenue = $grossRevenueQuery->sum('total_cents') / 100;

                // Calculate expected invoice amount
                $commissionRate = $tenant->commission_rate ?? 0;
                $expectedAmount = round($grossRevenue * ($commissionRate / 100), 2);

                // Calculate days until next billing
                $daysUntilBilling = $periodEnd ? $today->diffInDays($periodEnd, false) : null;

                // Get last invoice info
                $lastInvoice = $tenant->invoices->first();

                // Count unpaid invoices for this tenant
                $unpaidInvoicesCount = Invoice::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['pending', 'overdue', 'outstanding'])
                    ->count();

                $unpaidInvoicesTotal = Invoice::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['pending', 'overdue', 'outstanding'])
                    ->sum('amount');

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'public_name' => $tenant->public_name,
                    'slug' => $tenant->slug,
                    'commission_rate' => $commissionRate,
                    'currency' => $tenant->currency ?? 'EUR',
                    'billing_cycle_days' => $billingCycleDays,
                    'next_billing_date' => $periodEnd,
                    'period_start' => $periodStart,
                    'gross_revenue' => $grossRevenue,
                    'expected_amount' => $expectedAmount,
                    'days_until_billing' => $daysUntilBilling,
                    'is_overdue' => $daysUntilBilling !== null && $daysUntilBilling < 0,
                    'is_due_soon' => $daysUntilBilling !== null && $daysUntilBilling >= 0 && $daysUntilBilling <= 7,
                    'has_billing' => $periodEnd !== null,
                    'last_invoice' => $lastInvoice ? [
                        'id' => $lastInvoice->id,
                        'number' => $lastInvoice->number,
                        'amount' => $lastInvoice->amount,
                        'status' => $lastInvoice->status,
                        'issue_date' => $lastInvoice->issue_date,
                    ] : null,
                    'unpaid_invoices_count' => $unpaidInvoicesCount,
                    'unpaid_invoices_total' => $unpaidInvoicesTotal,
                ];
            });
    }

    public function getTotalExpectedRevenue(): array
    {
        $byCurrency = $this->tenants
            ->where('has_billing', true)
            ->groupBy('currency')
            ->map(fn($group) => $group->sum('expected_amount'));

        return $byCurrency->toArray();
    }

    public function getTotalGrossRevenue(): array
    {
        $byCurrency = $this->tenants
            ->groupBy('currency')
            ->map(fn($group) => $group->sum('gross_revenue'));

        return $byCurrency->toArray();
    }

    public function getHeading(): string
    {
        return 'Billing Overview';
    }

    public function getSubheading(): ?string
    {
        $total = $this->tenants->count();
        $withBilling = $this->tenants->where('has_billing', true)->count();
        return "{$total} active tenant(s) · {$withBilling} with billing configured";
    }
}
