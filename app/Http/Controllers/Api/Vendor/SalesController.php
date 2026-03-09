<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorSaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /**
     * Real-time sales dashboard for the vendor.
     */
    public function dashboard(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $totals = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->selectRaw('
                COUNT(*) as total_items,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents,
                SUM(commission_cents) as total_commission_cents
            ')
            ->first();

        // Sales per hour (last 24h)
        $hourly = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->where('created_at', '>=', now()->subHours(24))
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00") as hour,
                SUM(total_cents) as revenue_cents,
                SUM(quantity) as quantity
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Top products
        $topProducts = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->selectRaw('
                product_name,
                category_name,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents
            ')
            ->groupBy('product_name', 'category_name')
            ->orderByDesc('total_revenue_cents')
            ->limit(20)
            ->get();

        return response()->json([
            'totals'       => [
                'items'            => (int) $totals->total_items,
                'quantity'         => (int) $totals->total_quantity,
                'revenue_cents'    => (int) $totals->total_revenue_cents,
                'commission_cents' => (int) $totals->total_commission_cents,
                'net_cents'        => (int) ($totals->total_revenue_cents - $totals->total_commission_cents),
            ],
            'hourly'       => $hourly,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * Detailed sales list (paginated).
     */
    public function index(Request $request, int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $query = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->orderByDesc('created_at');

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->has('category')) {
            $query->where('category_name', $request->category);
        }

        if ($request->has('product_name')) {
            $query->where('product_name', 'like', "%{$request->product_name}%");
        }

        $sales = $query->paginate($request->input('per_page', 50));

        return response()->json($sales);
    }

    /**
     * Report: breakdown by category.
     */
    public function reportByCategory(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $report = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->selectRaw('
                COALESCE(category_name, "Uncategorized") as category,
                COUNT(*) as total_transactions,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents,
                SUM(commission_cents) as total_commission_cents,
                AVG(unit_price_cents) as avg_price_cents
            ')
            ->groupBy('category_name')
            ->orderByDesc('total_revenue_cents')
            ->get();

        return response()->json(['report' => $report]);
    }

    /**
     * Report: breakdown by product.
     */
    public function reportByProduct(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $report = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->selectRaw('
                product_name,
                COALESCE(category_name, "Uncategorized") as category,
                variant_name,
                COUNT(*) as total_transactions,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents,
                SUM(commission_cents) as total_commission_cents,
                MIN(unit_price_cents) as min_price_cents,
                MAX(unit_price_cents) as max_price_cents,
                AVG(unit_price_cents) as avg_price_cents
            ')
            ->groupBy('product_name', 'category_name', 'variant_name')
            ->orderByDesc('total_revenue_cents')
            ->get();

        return response()->json(['report' => $report]);
    }

    /**
     * Report: daily breakdown.
     */
    public function reportByDay(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $report = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_transactions,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents,
                SUM(commission_cents) as total_commission_cents
            ')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['report' => $report]);
    }

    /**
     * Report: hourly heatmap for a specific day.
     */
    public function reportByHour(Request $request, int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $query = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId);

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $report = $query->selectRaw('
                DATE(created_at) as date,
                HOUR(created_at) as hour,
                SUM(quantity) as total_quantity,
                SUM(total_cents) as total_revenue_cents
            ')
            ->groupBy(DB::raw('DATE(created_at)'), DB::raw('HOUR(created_at)'))
            ->orderBy('date')
            ->orderBy('hour')
            ->get();

        return response()->json(['report' => $report]);
    }

    /**
     * Report: POS device breakdown.
     */
    public function reportByPosDevice(int $editionId): JsonResponse
    {
        $vendor = Auth::guard('vendor')->user();

        $report = VendorSaleItem::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $editionId)
            ->leftJoin('vendor_pos_devices', 'vendor_sale_items.vendor_pos_device_id', '=', 'vendor_pos_devices.id')
            ->selectRaw('
                vendor_pos_devices.device_uid,
                vendor_pos_devices.name as device_name,
                COUNT(*) as total_transactions,
                SUM(vendor_sale_items.quantity) as total_quantity,
                SUM(vendor_sale_items.total_cents) as total_revenue_cents
            ')
            ->groupBy('vendor_pos_devices.device_uid', 'vendor_pos_devices.name')
            ->orderByDesc('total_revenue_cents')
            ->get();

        return response()->json(['report' => $report]);
    }
}
