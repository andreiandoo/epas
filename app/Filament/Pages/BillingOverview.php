<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Tenant;
use Carbon\Carbon;
use Filament\Notifications\Notification;
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
        $unpaidInvoices = Invoice::whereIn('status', ['pending', 'overdue', 'outstanding', 'new'])
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
                // Get billing period using tenant helper methods
                $periodStart = $tenant->getCurrentPeriodStart();
                $periodEnd = $tenant->getCurrentPeriodEnd();
                $billingCycleDays = $tenant->billing_cycle_days ?? 30;

                // Calculate gross revenue from orders in current period (total_cents / 100)
                $grossRevenueQuery = Order::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['paid', 'confirmed']);

                if ($periodStart) {
                    $grossRevenueQuery->where('created_at', '>=', $periodStart);
                }
                if ($periodEnd) {
                    $grossRevenueQuery->where('created_at', '<=', $periodEnd->endOfDay());
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
                    ->whereIn('status', ['pending', 'overdue', 'outstanding', 'new'])
                    ->count();

                $unpaidInvoicesTotal = Invoice::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['pending', 'overdue', 'outstanding', 'new'])
                    ->sum('amount');

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'public_name' => $tenant->public_name,
                    'slug' => $tenant->slug,
                    'commission_rate' => $commissionRate,
                    'currency' => $tenant->currency ?? 'EUR',
                    'billing_cycle_days' => $billingCycleDays,
                    'billing_starts_at' => $tenant->billing_starts_at,
                    'last_billing_date' => $tenant->last_billing_date,
                    'next_billing_date' => $periodEnd,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'gross_revenue' => $grossRevenue,
                    'expected_amount' => $expectedAmount,
                    'days_until_billing' => $daysUntilBilling,
                    'is_overdue' => $daysUntilBilling !== null && $daysUntilBilling < 0,
                    'is_due_soon' => $daysUntilBilling !== null && $daysUntilBilling >= 0 && $daysUntilBilling <= 7,
                    'has_billing' => $periodEnd !== null,
                    'contract_number' => $tenant->contract_number,
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

    /**
     * Generate proforma invoice for a tenant
     */
    public function generateProformaInvoice(int $tenantId): void
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Tenant not found.')
                ->send();
            return;
        }

        // Get period dates
        $periodStart = $tenant->getCurrentPeriodStart();
        $periodEnd = $tenant->getCurrentPeriodEnd();

        if (!$periodStart || !$periodEnd) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Billing period not configured for this tenant.')
                ->send();
            return;
        }

        // Calculate gross revenue for the period
        $grossRevenue = Order::where('tenant_id', $tenant->id)
            ->whereIn('status', ['paid', 'confirmed'])
            ->where('created_at', '>=', $periodStart)
            ->where('created_at', '<=', $periodEnd->endOfDay())
            ->sum('total_cents') / 100;

        // Calculate commission
        $commissionRate = $tenant->commission_rate ?? 0;
        $subtotal = round($grossRevenue * ($commissionRate / 100), 2);

        if ($subtotal <= 0) {
            Notification::make()
                ->warning()
                ->title('No Commission')
                ->body('No commission to invoice for this period (gross revenue: ' . number_format($grossRevenue, 2) . ' ' . ($tenant->currency ?? 'EUR') . ')')
                ->send();
            return;
        }

        // Get settings for VAT and invoice number
        $settings = Setting::current();

        // Calculate VAT
        $vatRate = $settings->vat_enabled ? ($settings->vat_rate ?? 19) : 0;
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $totalAmount = $subtotal + $vatAmount;

        // Generate invoice number
        $invoiceNumber = $settings->getNextInvoiceNumber();

        // Build description
        $description = "Comision servicii digitale";
        if ($tenant->contract_number) {
            $description .= " conform contract nr. {$tenant->contract_number}";
        }
        $description .= " - Perioada {$periodStart->format('d.m.Y')} - {$periodEnd->format('d.m.Y')}";

        // Create proforma invoice
        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'number' => 'PF-' . $invoiceNumber, // Prefix with PF for Proforma
            'description' => $description,
            'issue_date' => now(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => now()->addDays($settings->default_payment_terms_days ?? 14),
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $totalAmount,
            'currency' => $tenant->currency ?? 'EUR',
            'status' => 'new',
            'meta' => [
                'type' => 'proforma',
                'gross_revenue' => $grossRevenue,
                'commission_rate' => $commissionRate,
            ],
        ]);

        // Advance billing cycle
        $tenant->advanceBillingCycle();

        // Reload data
        $this->loadStats();
        $this->loadTenantData();

        $tenantDisplayName = $tenant->public_name ?? $tenant->name;

        Notification::make()
            ->success()
            ->title('Proforma Generated')
            ->body("Invoice {$invoice->number} created for {$tenantDisplayName} - " . number_format($totalAmount, 2) . ' ' . $invoice->currency)
            ->send();
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
