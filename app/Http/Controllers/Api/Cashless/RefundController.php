<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Http\Controllers\Controller;
use App\Models\Cashless\CashlessRefund;
use App\Models\Cashless\CashlessSale;
use App\Services\Cashless\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        private RefundService $refundService,
    ) {}

    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'cashless_sale_id' => 'required|integer|exists:cashless_sales,id',
            'refund_type'      => 'required|in:full,partial',
            'reason'           => 'required|string|max:1000',
            'item_ids'         => 'required_if:refund_type,partial|array',
            'item_ids.*'       => 'integer',
            'employee_id'      => 'nullable|integer',
        ]);

        $sale = CashlessSale::findOrFail($request->cashless_sale_id);

        try {
            $refund = $this->refundService->requestRefund(
                $sale,
                $request->refund_type,
                $request->reason,
                $request->item_ids,
                $request->employee_id,
            );

            return response()->json(['refund' => $refund], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, int $refundId): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|integer',
        ]);

        $refund = CashlessRefund::findOrFail($refundId);

        try {
            $refund = $this->refundService->approveAndProcess($refund, $request->employee_id);

            return response()->json([
                'refund'        => $refund,
                'balance_cents' => $refund->account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, int $refundId): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'employee_id'      => 'required|integer',
        ]);

        $refund = CashlessRefund::findOrFail($refundId);

        try {
            $refund = $this->refundService->reject($refund, $request->rejection_reason, $request->employee_id);

            return response()->json(['refund' => $refund]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function index(Request $request, int $editionId): JsonResponse
    {
        $query = CashlessRefund::where('festival_edition_id', $editionId)
            ->with(['sale:id,sale_number,total_cents', 'requestedBy:id,name', 'approvedBy:id,name'])
            ->orderByDesc('requested_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        return response()->json($query->paginate($request->input('per_page', 30)));
    }
}
