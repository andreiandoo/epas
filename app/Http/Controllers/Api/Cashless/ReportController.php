<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Http\Controllers\Controller;
use App\Services\Cashless\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    public function liveKpis(int $editionId): JsonResponse
    {
        return response()->json($this->reportService->liveKpis($editionId));
    }

    public function salesOverview(Request $request, int $editionId): JsonResponse
    {
        return response()->json([
            'totals' => $this->reportService->totalSales($editionId, $request->date),
            'hourly' => $this->reportService->hourlySales($editionId, $request->vendor_id, $request->date),
        ]);
    }

    public function vendorReport(Request $request, int $editionId): JsonResponse
    {
        return response()->json([
            'vendors'  => $this->reportService->salesPerVendor($editionId, $request->date),
            'balances' => $this->reportService->vendorBalances($editionId),
        ]);
    }

    public function productReport(Request $request, int $editionId): JsonResponse
    {
        return response()->json([
            'products' => $this->reportService->salesPerProduct(
                $editionId,
                $request->vendor_id,
                $request->date,
                $request->input('limit', 50),
            ),
        ]);
    }

    public function financeReport(int $editionId): JsonResponse
    {
        return response()->json([
            'revenue'  => $this->reportService->festivalRevenue($editionId),
            'balances' => $this->reportService->vendorBalances($editionId),
            'topups'   => $this->reportService->topupsByChannel($editionId),
        ]);
    }

    public function customerReport(int $editionId): JsonResponse
    {
        return response()->json([
            'active'       => $this->reportService->activeCustomers($editionId),
            'spending'     => $this->reportService->averageSpending($editionId),
            'distribution' => $this->reportService->balanceDistribution($editionId),
            'basket'       => $this->reportService->averageBasket($editionId),
        ]);
    }

    public function stockReport(Request $request, int $editionId): JsonResponse
    {
        return response()->json([
            'stock' => $this->reportService->stockSummary($editionId, $request->vendor_id),
        ]);
    }
}
