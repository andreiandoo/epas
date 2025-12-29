<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TaxReportController extends BaseController
{
    /**
     * Get tax settings and requirements for organizer
     */
    public function settings(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        // Get marketplace tax settings
        $marketplaceTaxSettings = $marketplace->settings['tax'] ?? [];

        return $this->success([
            'organizer' => [
                'company_name' => $organizer->company_name,
                'company_tax_id' => $organizer->company_tax_id,
                'company_registration' => $organizer->company_registration,
                'company_address' => $organizer->company_address,
                'is_tax_registered' => !empty($organizer->company_tax_id),
            ],
            'tax_settings' => $organizer->tax_settings ?? [],
            'marketplace_requirements' => [
                'vat_rate' => $marketplaceTaxSettings['vat_rate'] ?? 19,
                'invoice_required' => $marketplaceTaxSettings['invoice_required'] ?? true,
                'tax_withholding' => $marketplaceTaxSettings['tax_withholding'] ?? false,
                'tax_withholding_rate' => $marketplaceTaxSettings['tax_withholding_rate'] ?? 0,
            ],
            'tax_deadlines' => $this->getTaxDeadlines($organizer),
        ]);
    }

    /**
     * Update organizer tax settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'tax_settings' => 'array',
            'tax_settings.vat_registered' => 'boolean',
            'tax_settings.vat_number' => 'nullable|string|max:50',
            'tax_settings.fiscal_year_start' => 'nullable|date_format:m-d',
            'tax_settings.accounting_method' => 'nullable|in:cash,accrual',
            'tax_settings.preferred_currency' => 'nullable|string|size:3',
        ]);

        $organizer->update([
            'tax_settings' => array_merge(
                $organizer->tax_settings ?? [],
                $validated['tax_settings'] ?? []
            ),
        ]);

        return $this->success([
            'tax_settings' => $organizer->fresh()->tax_settings,
        ], 'Tax settings updated');
    }

    /**
     * Get annual tax summary
     */
    public function annualSummary(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, 1, 1)->startOfYear();
        $endDate = Carbon::create($year, 12, 31)->endOfYear();

        // Get all completed orders for the year
        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $grossRevenue = (float) $orders->sum('total');
        $commissionRate = $organizer->getEffectiveCommissionRate();
        $commissionPaid = round($grossRevenue * $commissionRate / 100, 2);
        $netRevenue = $grossRevenue - $commissionPaid;

        // VAT calculations (assuming standard 19% rate for Romania)
        $vatRate = $organizer->tax_settings['vat_rate'] ?? 19;
        $isVatRegistered = $organizer->tax_settings['vat_registered'] ?? !empty($organizer->company_tax_id);

        $vatCollected = $isVatRegistered ? round($grossRevenue * $vatRate / (100 + $vatRate), 2) : 0;
        $vatOnCommission = $isVatRegistered ? round($commissionPaid * $vatRate / (100 + $vatRate), 2) : 0;

        // Get refunds
        $refunds = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('status', 'refunded')
            ->whereBetween('refunded_at', [$startDate, $endDate])
            ->sum('total');

        // Get payouts
        $payouts = $organizer->payouts()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->sum('amount');

        return $this->success([
            'year' => $year,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'revenue' => [
                'gross_sales' => $grossRevenue,
                'refunds' => (float) $refunds,
                'net_sales' => $grossRevenue - $refunds,
                'total_orders' => $orders->count(),
                'average_order_value' => $orders->count() > 0
                    ? round($grossRevenue / $orders->count(), 2)
                    : 0,
            ],
            'deductions' => [
                'commission_paid' => $commissionPaid,
                'commission_rate' => $commissionRate,
            ],
            'vat' => [
                'is_vat_registered' => $isVatRegistered,
                'vat_rate' => $vatRate,
                'vat_collected' => $vatCollected,
                'vat_on_commission' => $vatOnCommission,
                'net_vat_liability' => $vatCollected - $vatOnCommission,
            ],
            'net_income' => [
                'before_tax' => $netRevenue,
                'payouts_received' => (float) $payouts,
            ],
            'tax_obligations' => $this->calculateTaxObligations($organizer, $netRevenue, $year),
        ]);
    }

    /**
     * Get quarterly tax report
     */
    public function quarterlyReport(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $year = $request->input('year', now()->year);
        $quarter = $request->input('quarter', ceil(now()->month / 3));

        $startMonth = ($quarter - 1) * 3 + 1;
        $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
        $endDate = Carbon::create($year, $startMonth + 2, 1)->endOfMonth();

        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $grossRevenue = (float) $orders->sum('total');
        $commissionRate = $organizer->getEffectiveCommissionRate();
        $commissionPaid = round($grossRevenue * $commissionRate / 100, 2);

        // Monthly breakdown
        $monthlyBreakdown = [];
        for ($i = 0; $i < 3; $i++) {
            $monthStart = Carbon::create($year, $startMonth + $i, 1)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $monthOrders = $orders->filter(function ($order) use ($monthStart, $monthEnd) {
                return $order->paid_at >= $monthStart && $order->paid_at <= $monthEnd;
            });

            $monthRevenue = (float) $monthOrders->sum('total');
            $monthlyBreakdown[] = [
                'month' => $monthStart->format('Y-m'),
                'month_name' => $monthStart->format('F Y'),
                'orders' => $monthOrders->count(),
                'revenue' => $monthRevenue,
                'commission' => round($monthRevenue * $commissionRate / 100, 2),
            ];
        }

        return $this->success([
            'year' => $year,
            'quarter' => $quarter,
            'quarter_name' => "Q{$quarter} {$year}",
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_orders' => $orders->count(),
                'gross_revenue' => $grossRevenue,
                'commission_paid' => $commissionPaid,
                'net_revenue' => $grossRevenue - $commissionPaid,
            ],
            'monthly_breakdown' => $monthlyBreakdown,
            'tax_deadlines' => $this->getQuarterlyTaxDeadlines($year, $quarter),
        ]);
    }

    /**
     * Get tax document (invoice/statement) for a period
     */
    public function taxDocument(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'type' => 'required|in:invoice,statement,vat_report',
            'period' => 'required|in:monthly,quarterly,annual',
            'year' => 'required|integer|min:2020|max:' . (now()->year + 1),
            'month' => 'nullable|integer|min:1|max:12',
            'quarter' => 'nullable|integer|min:1|max:4',
        ]);

        // Determine date range
        $year = $validated['year'];
        if ($validated['period'] === 'monthly') {
            $month = $validated['month'] ?? now()->month;
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $periodName = $startDate->format('F Y');
        } elseif ($validated['period'] === 'quarterly') {
            $quarter = $validated['quarter'] ?? ceil(now()->month / 3);
            $startMonth = ($quarter - 1) * 3 + 1;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $endDate = Carbon::create($year, $startMonth + 2, 1)->endOfMonth();
            $periodName = "Q{$quarter} {$year}";
        } else {
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate = Carbon::create($year, 12, 31)->endOfYear();
            $periodName = "Year {$year}";
        }

        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $grossRevenue = (float) $orders->sum('total');
        $commissionRate = $organizer->getEffectiveCommissionRate();
        $commissionPaid = round($grossRevenue * $commissionRate / 100, 2);

        return $this->success([
            'document' => [
                'type' => $validated['type'],
                'period' => $validated['period'],
                'period_name' => $periodName,
                'generated_at' => now()->toIso8601String(),
            ],
            'organizer' => [
                'name' => $organizer->name,
                'company_name' => $organizer->company_name,
                'company_tax_id' => $organizer->company_tax_id,
                'company_registration' => $organizer->company_registration,
                'company_address' => $organizer->company_address,
            ],
            'marketplace' => [
                'name' => $organizer->marketplaceClient->name,
                'company_name' => $organizer->marketplaceClient->company_name,
            ],
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'total_orders' => $orders->count(),
                'gross_revenue' => $grossRevenue,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionPaid,
                'net_revenue' => $grossRevenue - $commissionPaid,
            ],
            'download_url' => route('api.marketplace-client.organizer.tax.download', [
                'type' => $validated['type'],
                'period' => $validated['period'],
                'year' => $year,
                'month' => $validated['month'] ?? null,
                'quarter' => $validated['quarter'] ?? null,
            ]),
        ]);
    }

    /**
     * Get tax deadlines for the organizer
     */
    protected function getTaxDeadlines(MarketplaceOrganizer $organizer): array
    {
        $deadlines = [];
        $now = now();
        $currentYear = $now->year;
        $currentMonth = $now->month;
        $currentQuarter = ceil($currentMonth / 3);

        // VAT deadlines (monthly, due 25th of next month)
        if ($organizer->tax_settings['vat_registered'] ?? !empty($organizer->company_tax_id)) {
            $vatDeadline = Carbon::create($currentYear, $currentMonth, 25);
            if ($vatDeadline->isPast()) {
                $vatDeadline = $vatDeadline->addMonth();
            }
            $deadlines[] = [
                'type' => 'vat_declaration',
                'description' => 'Monthly VAT declaration',
                'due_date' => $vatDeadline->toDateString(),
                'days_until' => $now->diffInDays($vatDeadline, false),
                'period' => $vatDeadline->copy()->subMonth()->format('F Y'),
            ];
        }

        // Quarterly profit tax (for companies)
        if (!empty($organizer->company_tax_id)) {
            $quarterlyDeadline = Carbon::create(
                $currentYear,
                $currentQuarter * 3 + 1,
                25
            );
            if ($quarterlyDeadline->isPast()) {
                $quarterlyDeadline = $quarterlyDeadline->addMonths(3);
            }
            $deadlines[] = [
                'type' => 'quarterly_tax',
                'description' => 'Quarterly income tax payment',
                'due_date' => $quarterlyDeadline->toDateString(),
                'days_until' => $now->diffInDays($quarterlyDeadline, false),
                'period' => "Q" . ($currentQuarter) . " {$currentYear}",
            ];
        }

        // Annual declaration
        $annualDeadline = Carbon::create($currentYear, 3, 25); // March 25
        if ($annualDeadline->isPast()) {
            $annualDeadline = $annualDeadline->addYear();
        }
        $deadlines[] = [
            'type' => 'annual_declaration',
            'description' => 'Annual income declaration',
            'due_date' => $annualDeadline->toDateString(),
            'days_until' => $now->diffInDays($annualDeadline, false),
            'period' => "Year " . ($annualDeadline->year - 1),
        ];

        return $deadlines;
    }

    /**
     * Get quarterly tax deadlines
     */
    protected function getQuarterlyTaxDeadlines(int $year, int $quarter): array
    {
        $deadlineMonth = $quarter * 3 + 1;
        $deadlineYear = $deadlineMonth > 12 ? $year + 1 : $year;
        $deadlineMonth = $deadlineMonth > 12 ? $deadlineMonth - 12 : $deadlineMonth;

        return [
            'vat_declaration' => Carbon::create($deadlineYear, $deadlineMonth, 25)->toDateString(),
            'income_tax_payment' => Carbon::create($deadlineYear, $deadlineMonth, 25)->toDateString(),
        ];
    }

    /**
     * Calculate tax obligations
     */
    protected function calculateTaxObligations(MarketplaceOrganizer $organizer, float $netRevenue, int $year): array
    {
        $obligations = [];

        // For companies (with tax ID)
        if (!empty($organizer->company_tax_id)) {
            // Corporate income tax (Romania: 16% for most companies, 1% for micro-enterprises)
            $isMicroEnterprise = $netRevenue < 500000; // Simplified check
            $taxRate = $isMicroEnterprise ? 1 : 16;

            $obligations[] = [
                'type' => $isMicroEnterprise ? 'micro_enterprise_tax' : 'corporate_income_tax',
                'description' => $isMicroEnterprise
                    ? 'Micro-enterprise revenue tax (1% of revenue)'
                    : 'Corporate income tax (16% of profit)',
                'rate' => $taxRate,
                'estimated_amount' => round($netRevenue * $taxRate / 100, 2),
                'note' => $isMicroEnterprise
                    ? 'Based on gross revenue for micro-enterprises'
                    : 'Based on net profit after deductible expenses',
            ];
        } else {
            // For individuals (PFA or freelancers)
            $taxRate = 10; // Romania: 10% income tax for individuals
            $obligations[] = [
                'type' => 'individual_income_tax',
                'description' => 'Individual income tax (10%)',
                'rate' => $taxRate,
                'estimated_amount' => round($netRevenue * $taxRate / 100, 2),
                'note' => 'Based on net income after deductible expenses',
            ];

            // Social contributions for individuals
            if ($netRevenue > 24960) { // 12 minimum wages threshold
                $obligations[] = [
                    'type' => 'social_contributions',
                    'description' => 'CAS + CASS (25% + 10%)',
                    'rate' => 35,
                    'estimated_amount' => round(min($netRevenue, 24960 * 2) * 0.35, 2),
                    'note' => 'Social and health insurance contributions',
                ];
            }
        }

        return $obligations;
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}
