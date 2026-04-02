<?php

namespace App\Jobs;

use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\VendorFinanceSummary;
use App\Models\VendorEdition;
use App\Models\VendorSaleItem;
use App\Services\Cashless\FinanceFeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CalculateVendorFinanceSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $editionId,
        private string $date,
        private ?int $vendorId = null,
    ) {}

    public function handle(FinanceFeeService $feeService): void
    {
        $vendorEditions = VendorEdition::where('festival_edition_id', $this->editionId)
            ->when($this->vendorId, fn ($q) => $q->where('vendor_id', $this->vendorId))
            ->get();

        foreach ($vendorEditions as $ve) {
            $this->calculateForVendor($ve, $feeService);
        }
    }

    private function calculateForVendor(VendorEdition $ve, FinanceFeeService $feeService): void
    {
        $vendorId = $ve->vendor_id;

        // Completed sales for this vendor on this date
        $sales = CashlessSale::where('vendor_id', $vendorId)
            ->where('festival_edition_id', $this->editionId)
            ->whereDate('sold_at', $this->date)
            ->where('status', SaleStatus::Completed)
            ->get();

        $grossSales = $sales->sum('total_cents');
        $commissions = $sales->sum('commission_cents');
        $tips = $sales->sum('tip_cents');
        $txCount = $sales->count();

        // Calculate fees from FinanceFeeRules
        $saleFees = 0;
        foreach ($sales as $sale) {
            $result = $feeService->calculateFeesForSale($sale);
            $saleFees += $result['total_fees_cents'];
        }

        // Add daily fixed fees
        $dailyFees = $feeService->calculateDailyFees($this->editionId, $vendorId, $this->date);
        $totalFees = $saleFees + $dailyFees;

        // Tax and SGR from sale items
        $itemAggs = VendorSaleItem::where('vendor_id', $vendorId)
            ->where('festival_edition_id', $this->editionId)
            ->whereDate('created_at', $this->date)
            ->whereHas('cashlessSale', fn ($q) => $q->where('status', SaleStatus::Completed))
            ->selectRaw('COALESCE(SUM(tax_cents), 0) as tax, COALESCE(SUM(sgr_cents), 0) as sgr')
            ->first();

        // Refunded amounts
        $refundedCents = CashlessSale::where('vendor_id', $vendorId)
            ->where('festival_edition_id', $this->editionId)
            ->whereDate('sold_at', $this->date)
            ->whereIn('status', [SaleStatus::Refunded, SaleStatus::Voided])
            ->sum('total_cents');

        $netSales = $grossSales - $refundedCents;
        $vendorPayout = $netSales - $commissions - $totalFees;

        VendorFinanceSummary::updateOrCreate(
            [
                'festival_edition_id' => $this->editionId,
                'vendor_id'           => $vendorId,
                'period_date'         => $this->date,
            ],
            [
                'tenant_id'            => $ve->vendor->tenant_id ?? $ve->edition->tenant_id,
                'gross_sales_cents'    => $grossSales,
                'net_sales_cents'      => $netSales,
                'commission_cents'     => $commissions,
                'fees_cents'           => $totalFees,
                'tax_collected_cents'  => $itemAggs->tax ?? 0,
                'sgr_collected_cents'  => $itemAggs->sgr ?? 0,
                'tips_cents'           => $tips,
                'vendor_payout_cents'  => $vendorPayout,
                'transactions_count'   => $txCount,
            ]
        );
    }
}
