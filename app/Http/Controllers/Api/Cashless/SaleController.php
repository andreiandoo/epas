<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Http\Controllers\Controller;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Services\Cashless\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $saleService,
    ) {}

    /**
     * Create a new sale (POS operation).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'account_id'    => 'required|integer|exists:cashless_accounts,id',
            'vendor_id'     => 'required|integer|exists:vendors,id',
            'edition_id'    => 'required|integer|exists:festival_editions,id',
            'items'         => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.variant_name' => 'nullable|string',
            'employee_id'   => 'nullable|integer',
            'pos_device_id' => 'nullable|integer',
            'shift_id'      => 'nullable|integer',
            'tip_cents'     => 'nullable|integer|min:0',
        ]);

        $account = CashlessAccount::findOrFail($request->account_id);

        try {
            $sale = $this->saleService->createSale(
                $account,
                $request->vendor_id,
                $request->edition_id,
                $request->items,
                $request->employee_id,
                $request->pos_device_id,
                $request->shift_id,
                $request->input('tip_cents', 0),
            );

            return response()->json([
                'sale' => $this->formatSale($sale),
                'balance_cents' => $account->balance_cents,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get sale details.
     */
    public function show(int $saleId): JsonResponse
    {
        $sale = CashlessSale::with(['items', 'vendor:id,name', 'employee:id,name,full_name', 'customer:id,first_name,last_name'])
            ->findOrFail($saleId);

        return response()->json(['sale' => $this->formatSale($sale)]);
    }

    /**
     * Void a sale (full refund).
     */
    public function void(Request $request, int $saleId): JsonResponse
    {
        $sale = CashlessSale::with('account')->findOrFail($saleId);

        try {
            $sale = $this->saleService->voidSale($sale, $request->input('operator'));

            return response()->json([
                'sale'          => $this->formatSale($sale),
                'balance_cents' => $sale->account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Partial refund on specific items.
     */
    public function partialRefund(Request $request, int $saleId): JsonResponse
    {
        $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer',
        ]);

        $sale = CashlessSale::with('account')->findOrFail($saleId);

        try {
            $sale = $this->saleService->partialRefund($sale, $request->item_ids, $request->input('operator'));

            return response()->json([
                'sale'          => $this->formatSale($sale),
                'balance_cents' => $sale->account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Sales grouped by category.
     */
    public function byCategory(Request $request, int $editionId): JsonResponse
    {
        $data = $this->saleService->salesByCategory(
            $editionId,
            $request->input('vendor_id'),
        );

        return response()->json(['data' => $data]);
    }

    /**
     * List sales for an edition (paginated).
     */
    public function index(Request $request, int $editionId): JsonResponse
    {
        $query = CashlessSale::where('festival_edition_id', $editionId)
            ->with(['vendor:id,name', 'employee:id,name,full_name'])
            ->orderByDesc('sold_at');

        if ($request->has('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('sold_at', $request->date);
        }

        if ($request->has('account_id')) {
            $query->where('cashless_account_id', $request->account_id);
        }

        return response()->json($query->paginate($request->input('per_page', 50)));
    }

    private function formatSale(CashlessSale $sale): array
    {
        return [
            'id'               => $sale->id,
            'sale_number'      => $sale->sale_number,
            'vendor'           => $sale->vendor?->only('id', 'name'),
            'employee'         => $sale->employee?->only('id', 'name', 'full_name'),
            'subtotal_cents'   => $sale->subtotal_cents,
            'tax_cents'        => $sale->tax_cents,
            'total_cents'      => $sale->total_cents,
            'commission_cents' => $sale->commission_cents,
            'tip_cents'        => $sale->tip_cents,
            'currency'         => $sale->currency,
            'items_count'      => $sale->items_count,
            'status'           => $sale->status->value,
            'sold_at'          => $sale->sold_at?->toIso8601String(),
            'items'            => $sale->relationLoaded('items') ? $sale->items->map(fn ($i) => [
                'id'                => $i->id,
                'product_name'      => $i->product_name,
                'category_name'     => $i->category_name,
                'variant_name'      => $i->variant_name,
                'quantity'          => $i->quantity,
                'unit_price_cents'  => $i->unit_price_cents,
                'total_cents'       => $i->total_cents,
                'tax_cents'         => $i->tax_cents,
                'sgr_cents'         => $i->sgr_cents,
                'product_type'      => $i->product_type,
            ]) : null,
            'customer'         => $sale->relationLoaded('customer') ? $sale->customer?->only('id', 'first_name', 'last_name') : null,
        ];
    }
}
