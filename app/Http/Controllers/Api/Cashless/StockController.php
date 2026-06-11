<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Http\Controllers\Controller;
use App\Models\Cashless\InventoryMovement;
use App\Services\Cashless\SupplierStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private SupplierStockService $stockService,
    ) {}

    public function summary(Request $request, int $editionId): JsonResponse
    {
        $stock = $this->stockService->getStockSummary($editionId, $request->input('vendor_id'));

        return response()->json(['stock' => $stock]);
    }

    public function delivery(Request $request, int $editionId): JsonResponse
    {
        $request->validate([
            'supplier_product_id' => 'required|integer|exists:supplier_products,id',
            'quantity'            => 'required|numeric|min:0.001',
            'reference'           => 'nullable|string',
            'notes'               => 'nullable|string',
            'performed_by'        => 'nullable|string',
        ]);

        try {
            $movement = $this->stockService->recordDelivery(
                $request->input('tenant_id', auth()->user()->tenant_id ?? 0),
                $editionId,
                $request->supplier_product_id,
                $request->quantity,
                $request->reference,
                $request->notes,
                $request->performed_by,
            );

            return response()->json(['movement' => $movement], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function allocate(Request $request, int $editionId): JsonResponse
    {
        $request->validate([
            'supplier_product_id' => 'required|integer',
            'vendor_id'           => 'required|integer|exists:vendors,id',
            'quantity'            => 'required|numeric|min:0.001',
            'reference'           => 'nullable|string',
            'performed_by'        => 'nullable|string',
        ]);

        try {
            $movement = $this->stockService->allocateToVendor(
                $request->input('tenant_id', auth()->user()->tenant_id ?? 0),
                $editionId,
                $request->supplier_product_id,
                $request->vendor_id,
                $request->quantity,
                $request->reference,
                $request->performed_by,
            );

            return response()->json(['movement' => $movement], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function returnStock(Request $request, int $editionId): JsonResponse
    {
        $request->validate([
            'supplier_product_id' => 'required|integer',
            'vendor_id'           => 'nullable|integer',
            'return_to'           => 'required|in:festival,supplier',
            'quantity'            => 'required|numeric|min:0.001',
            'notes'               => 'nullable|string',
            'performed_by'        => 'nullable|string',
        ]);

        try {
            $tenantId = $request->input('tenant_id', auth()->user()->tenant_id ?? 0);

            $movement = $request->return_to === 'festival'
                ? $this->stockService->returnToFestival($tenantId, $editionId, $request->supplier_product_id, $request->vendor_id, $request->quantity, $request->notes, $request->performed_by)
                : $this->stockService->returnToSupplier($tenantId, $editionId, $request->supplier_product_id, $request->quantity, $request->reference ?? null, $request->notes, $request->performed_by);

            return response()->json(['movement' => $movement], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function waste(Request $request, int $editionId): JsonResponse
    {
        $request->validate([
            'supplier_product_id' => 'required|integer',
            'vendor_id'           => 'nullable|integer',
            'quantity'            => 'required|numeric|min:0.001',
            'notes'               => 'nullable|string',
            'performed_by'        => 'nullable|string',
        ]);

        try {
            $movement = $this->stockService->recordWaste(
                $request->input('tenant_id', auth()->user()->tenant_id ?? 0),
                $editionId,
                $request->supplier_product_id,
                $request->vendor_id,
                $request->quantity,
                $request->notes,
                $request->performed_by,
            );

            return response()->json(['movement' => $movement], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function movements(Request $request, int $editionId): JsonResponse
    {
        $query = InventoryMovement::where('festival_edition_id', $editionId)
            ->with('supplierProduct:id,name,sku')
            ->orderByDesc('performed_at');

        if ($request->has('supplier_product_id')) {
            $query->where('supplier_product_id', $request->supplier_product_id);
        }

        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        return response()->json($query->paginate($request->input('per_page', 50)));
    }
}
