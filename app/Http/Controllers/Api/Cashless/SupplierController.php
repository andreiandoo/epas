<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Http\Controllers\Controller;
use App\Models\Cashless\SupplierBrand;
use App\Models\Cashless\SupplierProduct;
use App\Models\MerchandiseSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request, int $editionId): JsonResponse
    {
        $suppliers = MerchandiseSupplier::active()
            ->whereHas('supplierProducts', fn ($q) => $q->where('festival_edition_id', $editionId))
            ->with(['brands' => fn ($q) => $q->active()])
            ->withCount(['supplierProducts' => fn ($q) => $q->where('festival_edition_id', $editionId)])
            ->get();

        return response()->json(['suppliers' => $suppliers]);
    }

    public function products(Request $request, int $editionId, int $supplierId): JsonResponse
    {
        $products = SupplierProduct::where('merchandise_supplier_id', $supplierId)
            ->where('festival_edition_id', $editionId)
            ->active()
            ->with('brand:id,name')
            ->orderBy('name')
            ->paginate($request->input('per_page', 50));

        return response()->json($products);
    }

    public function brands(int $supplierId): JsonResponse
    {
        $brands = SupplierBrand::where('merchandise_supplier_id', $supplierId)
            ->active()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json(['brands' => $brands]);
    }
}
