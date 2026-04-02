<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Http\Controllers\Controller;
use App\Models\Cashless\VendorFinanceSummary;
use App\Services\Cashless\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function vendorSummaries(Request $request, int $editionId): JsonResponse
    {
        $query = VendorFinanceSummary::where('festival_edition_id', $editionId)
            ->with('vendor:id,name');

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('date')) {
            $query->where('period_date', $request->date);
        }

        return response()->json($query->orderByDesc('period_date')->paginate($request->input('per_page', 50)));
    }

    public function vendorTotal(int $editionId, int $vendorId): JsonResponse
    {
        $totals = VendorFinanceSummary::where('festival_edition_id', $editionId)
            ->where('vendor_id', $vendorId)
            ->selectRaw('
                SUM(gross_sales_cents) as gross_sales_cents,
                SUM(net_sales_cents) as net_sales_cents,
                SUM(commission_cents) as commission_cents,
                SUM(fees_cents) as fees_cents,
                SUM(tax_collected_cents) as tax_collected_cents,
                SUM(sgr_collected_cents) as sgr_collected_cents,
                SUM(tips_cents) as tips_cents,
                SUM(vendor_payout_cents) as vendor_payout_cents,
                SUM(transactions_count) as transactions_count
            ')
            ->first();

        return response()->json(['totals' => $totals]);
    }

    public function enforcePricing(int $editionId): JsonResponse
    {
        $result = app(PricingService::class)->enforceAllForEdition($editionId);

        return response()->json([
            'message'  => "Pricing enforced: {$result['enforced']} updated, {$result['skipped']} skipped",
            'enforced' => $result['enforced'],
            'skipped'  => $result['skipped'],
        ]);
    }
}
