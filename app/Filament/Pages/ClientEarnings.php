<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\StripeService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class ClientEarnings extends Page
{
    protected string $view = 'filament.pages.client-earnings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Client Earnings';
    protected static ?string $title = 'Client Earnings';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 2;
    protected static bool $shouldRegisterNavigation = false; // Only accessible via links

    protected static ?string $slug = 'client-earnings/{type?}/{id?}';

    public string $type = 'tenant'; // 'tenant' or 'marketplace'
    public int $clientId = 0;
    public string $period = 'month'; // day, week, month, custom
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public array $client = [];
    public float $totalRevenue = 0;
    public float $totalCommission = 0;
    public int $orderCount = 0;
    public Collection $orders;
    public Collection $dailyBreakdown;

    public function mount(?string $type = null, ?string $id = null): void
    {
        $this->type = $type ?? request()->query('type', 'tenant');
        $this->clientId = (int) ($id ?? request()->query('id', 0));

        // Default period: this month
        $this->dateFrom = Carbon::today()->startOfMonth()->toDateString();
        $this->dateTo = Carbon::today()->toDateString();

        $this->loadClientInfo();
        $this->loadEarningsData();
    }

    protected function loadClientInfo(): void
    {
        if ($this->type === 'marketplace') {
            $client = MarketplaceClient::find($this->clientId);
            if ($client) {
                $this->client = [
                    'name' => $client->name,
                    'domain' => $client->domain,
                    'commission_rate' => $client->commission_rate ?? 0,
                    'commission_mode' => $client->commission_mode,
                    'currency' => $client->currency ?? 'RON',
                    'billing_starts_at' => $client->billing_starts_at,
                    'next_billing_date' => $client->getCurrentPeriodEnd(),
                    'period_start' => $client->getCurrentPeriodStart(),
                    'has_billing' => $client->getCurrentPeriodEnd() !== null,
                ];
            }
        } else {
            $tenant = Tenant::find($this->clientId);
            if ($tenant) {
                $this->client = [
                    'name' => $tenant->public_name ?? $tenant->name,
                    'domain' => $tenant->domain,
                    'commission_rate' => $tenant->commission_rate ?? 0,
                    'commission_mode' => $tenant->commission_mode ?? 'percentage',
                    'currency' => $tenant->currency ?? 'EUR',
                    'billing_starts_at' => $tenant->billing_starts_at,
                    'next_billing_date' => $tenant->getCurrentPeriodEnd(),
                    'period_start' => $tenant->getCurrentPeriodStart(),
                    'has_billing' => $tenant->getCurrentPeriodEnd() !== null,
                ];
            }
        }
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        $today = Carbon::today();

        switch ($period) {
            case 'day':
                $this->dateFrom = $today->toDateString();
                $this->dateTo = $today->toDateString();
                break;
            case 'week':
                $this->dateFrom = $today->copy()->startOfWeek()->toDateString();
                $this->dateTo = $today->toDateString();
                break;
            case 'month':
                $this->dateFrom = $today->copy()->startOfMonth()->toDateString();
                $this->dateTo = $today->toDateString();
                break;
            case 'last_month':
                $this->dateFrom = $today->copy()->subMonth()->startOfMonth()->toDateString();
                $this->dateTo = $today->copy()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'billing_period':
                if (!empty($this->client['period_start'])) {
                    $this->dateFrom = $this->client['period_start']->toDateString();
                }
                if (!empty($this->client['next_billing_date'])) {
                    $this->dateTo = $this->client['next_billing_date']->toDateString();
                }
                break;
        }

        $this->loadEarningsData();
    }

    public function applyCustomDates(): void
    {
        $this->period = 'custom';
        $this->loadEarningsData();
    }

    protected function loadEarningsData(): void
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $query = Order::whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($this->type === 'marketplace') {
            $query->where('marketplace_client_id', $this->clientId);
        } else {
            $query->where('tenant_id', $this->clientId);
        }

        // Get orders
        $ordersRaw = $query->orderBy('created_at', 'desc')->get();

        // Calculate totals
        if ($this->type === 'marketplace') {
            $this->totalRevenue = (float) $ordersRaw->sum('total');
        } else {
            $this->totalRevenue = $ordersRaw->sum('total_cents') / 100;
        }
        // Platform commission = gross revenue × client's commission rate
        $this->totalCommission = round($this->totalRevenue * (($this->client['commission_rate'] ?? 0) / 100), 2);

        $this->orderCount = $ordersRaw->count();

        // Map orders for display
        $this->orders = $ordersRaw->map(function ($order) {
            $revenue = $this->type === 'marketplace'
                ? (float) $order->total
                : ($order->total_cents / 100);

            // Platform commission = order revenue × client's commission rate
            $commission = round($revenue * (($this->client['commission_rate'] ?? 0) / 100), 2);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number ?? "#{$order->id}",
                'date' => $order->created_at,
                'customer' => $order->customer_name ?? $order->customer_email,
                'revenue' => $revenue,
                'commission' => $commission,
                'currency' => $order->currency ?? ($this->client['currency'] ?? 'RON'),
                'status' => $order->status,
            ];
        });

        // Daily breakdown
        $this->dailyBreakdown = $ordersRaw->groupBy(fn ($order) => $order->created_at->format('Y-m-d'))
            ->map(function ($dayOrders, $date) {
                if ($this->type === 'marketplace') {
                    $revenue = (float) $dayOrders->sum('total');
                } else {
                    $revenue = $dayOrders->sum('total_cents') / 100;
                }
                // Platform commission = day revenue × client's commission rate
                $commission = round($revenue * (($this->client['commission_rate'] ?? 0) / 100), 2);

                return [
                    'date' => $date,
                    'orders' => $dayOrders->count(),
                    'revenue' => $revenue,
                    'commission' => $commission,
                ];
            })
            ->sortByDesc('date')
            ->values();
    }

    /**
     * Generate invoice for the currently filtered period
     */
    public function generateInvoiceForPeriod(): void
    {
        if ($this->totalCommission <= 0) {
            Notification::make()
                ->warning()
                ->title('No Commission')
                ->body('No commission to invoice for this period.')
                ->send();
            return;
        }

        $settings = Setting::current();
        $vatRate = $settings->vat_enabled ? ($settings->vat_rate ?? 19) : 0;
        $subtotal = $this->totalCommission;
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $totalAmount = $subtotal + $vatAmount;
        $invoiceNumber = $settings->getNextInvoiceNumber();

        $periodStart = Carbon::parse($this->dateFrom);
        $periodEnd = Carbon::parse($this->dateTo);

        $invoiceData = [
            'number' => 'PF-' . $invoiceNumber,
            'type' => 'proforma',
            'issue_date' => now(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => now()->addDays($settings->default_payment_terms_days ?? 14),
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $totalAmount,
            'currency' => $this->client['currency'] ?? 'RON',
            'status' => 'new',
            'meta' => [
                'gross_revenue' => $this->totalRevenue,
                'commission_rate' => $this->client['commission_rate'] ?? 0,
                'order_count' => $this->orderCount,
            ],
        ];

        if ($this->type === 'marketplace') {
            $client = MarketplaceClient::find($this->clientId);
            $invoiceData['marketplace_client_id'] = $this->clientId;
            $invoiceData['description'] = Invoice::generateMarketplaceDescription($client, $periodStart, $periodEnd);
            $invoiceData['meta']['client_type'] = 'marketplace';
        } else {
            $tenant = Tenant::find($this->clientId);
            $invoiceData['tenant_id'] = $this->clientId;
            $invoiceData['description'] = Invoice::generateDescription($tenant, $periodStart, $periodEnd);
        }

        $invoice = Invoice::create($invoiceData);

        // Create Stripe payment link
        try {
            $stripeService = app(StripeService::class);
            if ($stripeService->isConfigured()) {
                $stripeService->createInvoicePaymentLink($invoice);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to create Stripe payment link for invoice ' . $invoice->number . ': ' . $e->getMessage());
        }

        Notification::make()
            ->success()
            ->title('Invoice Generated')
            ->body("Invoice {$invoice->number} created - " . number_format($totalAmount, 2) . ' ' . $invoice->currency)
            ->send();
    }

    public function getHeading(): string
    {
        return ($this->client['name'] ?? 'Client') . ' — Earnings';
    }

    public function getSubheading(): ?string
    {
        $typeLabel = $this->type === 'marketplace' ? 'Marketplace Client' : 'Tenant';
        return "{$typeLabel} · Commission: {$this->client['commission_rate']}%";
    }
}
