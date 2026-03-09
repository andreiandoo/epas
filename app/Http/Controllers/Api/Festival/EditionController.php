<?php

namespace App\Http\Controllers\Api\Festival;

use App\Http\Controllers\Controller;
use App\Models\FestivalEdition;
use App\Models\Vendor;
use App\Models\VendorEdition;
use App\Models\VendorPosDevice;
use App\Models\VendorSaleItem;
use App\Models\WristbandTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EditionController extends Controller
{
    // ── Edition CRUD ──

    public function index(Request $request): JsonResponse
    {
        $editions = FestivalEdition::where('tenant_id', $request->tenant_id)
            ->orderByDesc('year')
            ->get();

        return response()->json(['editions' => $editions]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'      => 'required|integer|exists:tenants,id',
            'event_id'       => 'nullable|integer|exists:events,id',
            'name'           => 'required|string|max:200',
            'year'           => 'required|integer|min:2000|max:2099',
            'edition_number' => 'nullable|integer|min:1',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'currency'       => 'string|size:3',
            'settings'       => 'nullable|array',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $data['status'] = 'draft';

        $edition = FestivalEdition::create($data);

        return response()->json(['edition' => $edition], 201);
    }

    public function show(int $editionId): JsonResponse
    {
        $edition = FestivalEdition::with(['vendors.vendor:id,name,slug,company_name,logo_url'])->findOrFail($editionId);

        return response()->json(['edition' => $edition]);
    }

    public function update(Request $request, int $editionId): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $data = $request->validate([
            'name'           => 'string|max:200',
            'event_id'       => 'nullable|integer|exists:events,id',
            'year'           => 'integer|min:2000|max:2099',
            'edition_number' => 'nullable|integer|min:1',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date|after_or_equal:start_date',
            'status'         => 'in:draft,announced,active,completed,cancelled',
            'currency'       => 'string|size:3',
            'settings'       => 'nullable|array',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $edition->update($data);

        return response()->json(['edition' => $edition]);
    }

    // ── Vendor management (organizer perspective) ──

    public function storeVendor(Request $request, int $editionId): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'email'          => 'required|email',
            'password'       => 'required|string|min:8',
            'phone'          => 'nullable|string|max:20',
            'company_name'   => 'nullable|string|max:200',
            'cui'            => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:100',
            'logo_url'       => 'nullable|url|max:500',
            // Edition-specific
            'location'            => 'nullable|string|max:200',
            'location_coordinates' => 'nullable|string|max:50',
            'vendor_type'         => 'in:food,drink,merch,services,other',
            'commission_rate'     => 'numeric|min:0|max:100',
            'commission_mode'     => 'in:percentage,fixed_per_transaction',
            'fixed_commission_cents' => 'nullable|integer|min:0',
            'operating_hours'     => 'nullable|array',
        ]);

        // Create or find existing vendor for this tenant
        $vendor = Vendor::where('tenant_id', $edition->tenant_id)
            ->where('email', $data['email'])
            ->first();

        if (!$vendor) {
            $vendor = Vendor::create([
                'tenant_id'      => $edition->tenant_id,
                'name'           => $data['name'],
                'slug'           => Str::slug($data['name']),
                'email'          => $data['email'],
                'password'       => $data['password'],
                'phone'          => $data['phone'] ?? null,
                'company_name'   => $data['company_name'] ?? null,
                'cui'            => $data['cui'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'logo_url'       => $data['logo_url'] ?? null,
            ]);
        }

        // Link to this edition
        $vendorEdition = VendorEdition::create([
            'vendor_id'              => $vendor->id,
            'festival_edition_id'    => $editionId,
            'location'               => $data['location'] ?? null,
            'location_coordinates'   => $data['location_coordinates'] ?? null,
            'vendor_type'            => $data['vendor_type'] ?? 'food',
            'commission_rate'        => $data['commission_rate'] ?? 0,
            'commission_mode'        => $data['commission_mode'] ?? 'percentage',
            'fixed_commission_cents' => $data['fixed_commission_cents'] ?? null,
            'operating_hours'        => $data['operating_hours'] ?? null,
        ]);

        return response()->json([
            'vendor'         => $vendor,
            'vendor_edition' => $vendorEdition,
        ], 201);
    }

    public function updateVendorEdition(Request $request, int $editionId, int $vendorId): JsonResponse
    {
        $vendorEdition = VendorEdition::where('festival_edition_id', $editionId)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        $data = $request->validate([
            'location'               => 'nullable|string|max:200',
            'location_coordinates'   => 'nullable|string|max:50',
            'vendor_type'            => 'in:food,drink,merch,services,other',
            'commission_rate'        => 'numeric|min:0|max:100',
            'commission_mode'        => 'in:percentage,fixed_per_transaction',
            'fixed_commission_cents' => 'nullable|integer|min:0',
            'status'                 => 'in:pending,confirmed,cancelled',
            'operating_hours'        => 'nullable|array',
        ]);

        $vendorEdition->update($data);

        return response()->json(['vendor_edition' => $vendorEdition]);
    }

    public function listVendors(int $editionId): JsonResponse
    {
        $vendors = VendorEdition::where('festival_edition_id', $editionId)
            ->with('vendor:id,name,slug,email,company_name,contact_person,phone,logo_url,status')
            ->get()
            ->map(fn ($ve) => [
                'vendor'              => $ve->vendor,
                'location'            => $ve->location,
                'vendor_type'         => $ve->vendor_type,
                'commission_rate'     => $ve->commission_rate,
                'commission_mode'     => $ve->commission_mode,
                'status'              => $ve->status,
                'operating_hours'     => $ve->operating_hours,
            ]);

        return response()->json(['vendors' => $vendors]);
    }

    // ── POS device management ──

    public function storePosDevice(Request $request, int $editionId, int $vendorId): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $data = $request->validate([
            'device_uid' => 'required|string|max:100',
            'name'       => 'nullable|string|max:100',
        ]);

        $device = VendorPosDevice::create([
            'tenant_id'           => $edition->tenant_id,
            'vendor_id'           => $vendorId,
            'festival_edition_id' => $editionId,
            'device_uid'          => $data['device_uid'],
            'name'                => $data['name'] ?? null,
        ]);

        return response()->json(['device' => $device], 201);
    }

    public function listPosDevices(int $editionId, int $vendorId): JsonResponse
    {
        $devices = VendorPosDevice::where('festival_edition_id', $editionId)
            ->where('vendor_id', $vendorId)
            ->get();

        return response()->json(['devices' => $devices]);
    }

    // ── Organizer reports (cross-vendor) ──

    public function reportOverview(int $editionId): JsonResponse
    {
        $edition = FestivalEdition::findOrFail($editionId);

        $vendorSales = VendorSaleItem::where('festival_edition_id', $editionId)
            ->selectRaw('
                COUNT(DISTINCT vendor_id) as total_vendors,
                COUNT(*) as total_transactions,
                SUM(quantity) as total_items_sold,
                SUM(total_cents) as total_revenue_cents,
                SUM(commission_cents) as total_commission_cents
            ')
            ->first();

        $wristbandStats = DB::table('wristbands')
            ->where('festival_edition_id', $editionId)
            ->selectRaw('
                COUNT(*) as total_wristbands,
                SUM(CASE WHEN status = "activated" THEN 1 ELSE 0 END) as active_wristbands,
                SUM(balance_cents) as total_outstanding_balance_cents
            ')
            ->first();

        $topupStats = WristbandTransaction::where('festival_edition_id', $editionId)
            ->where('transaction_type', 'topup')
            ->selectRaw('
                COUNT(*) as total_topups,
                SUM(amount_cents) as total_topup_cents
            ')
            ->first();

        return response()->json([
            'edition' => $edition->only('id', 'name', 'year', 'status', 'currency'),
            'vendor_sales' => [
                'total_vendors'          => (int) $vendorSales->total_vendors,
                'total_transactions'     => (int) $vendorSales->total_transactions,
                'total_items_sold'       => (int) $vendorSales->total_items_sold,
                'total_revenue_cents'    => (int) $vendorSales->total_revenue_cents,
                'total_commission_cents' => (int) $vendorSales->total_commission_cents,
                'net_vendor_cents'       => (int) ($vendorSales->total_revenue_cents - $vendorSales->total_commission_cents),
            ],
            'wristbands' => [
                'total'                        => (int) $wristbandStats->total_wristbands,
                'active'                       => (int) $wristbandStats->active_wristbands,
                'outstanding_balance_cents'    => (int) $wristbandStats->total_outstanding_balance_cents,
            ],
            'topups' => [
                'count'       => (int) $topupStats->total_topups,
                'total_cents' => (int) $topupStats->total_topup_cents,
            ],
        ]);
    }

    public function reportVendorBreakdown(int $editionId): JsonResponse
    {
        $vendors = VendorSaleItem::where('vendor_sale_items.festival_edition_id', $editionId)
            ->join('vendors', 'vendor_sale_items.vendor_id', '=', 'vendors.id')
            ->selectRaw('
                vendors.id as vendor_id,
                vendors.name as vendor_name,
                vendors.company_name,
                COUNT(*) as total_transactions,
                SUM(vendor_sale_items.quantity) as total_items,
                SUM(vendor_sale_items.total_cents) as total_revenue_cents,
                SUM(vendor_sale_items.commission_cents) as total_commission_cents,
                SUM(vendor_sale_items.total_cents - vendor_sale_items.commission_cents) as net_vendor_cents
            ')
            ->groupBy('vendors.id', 'vendors.name', 'vendors.company_name')
            ->orderByDesc('total_revenue_cents')
            ->get();

        return response()->json(['vendors' => $vendors]);
    }

    /**
     * Compare two editions side by side.
     */
    public function compareEditions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'edition_a' => 'required|integer|exists:festival_editions,id',
            'edition_b' => 'required|integer|exists:festival_editions,id',
        ]);

        $stats = [];
        foreach (['edition_a', 'edition_b'] as $key) {
            $editionId = $data[$key];
            $edition   = FestivalEdition::findOrFail($editionId);

            $sales = VendorSaleItem::where('festival_edition_id', $editionId)
                ->selectRaw('
                    COUNT(DISTINCT vendor_id) as vendors,
                    COUNT(*) as transactions,
                    SUM(quantity) as items_sold,
                    SUM(total_cents) as revenue_cents,
                    SUM(commission_cents) as commission_cents
                ')
                ->first();

            $wristbands = DB::table('wristbands')
                ->where('festival_edition_id', $editionId)
                ->count();

            $topups = WristbandTransaction::where('festival_edition_id', $editionId)
                ->where('transaction_type', 'topup')
                ->sum('amount_cents');

            $stats[$key] = [
                'edition'          => $edition->only('id', 'name', 'year', 'edition_number'),
                'vendors'          => (int) $sales->vendors,
                'transactions'     => (int) $sales->transactions,
                'items_sold'       => (int) $sales->items_sold,
                'revenue_cents'    => (int) $sales->revenue_cents,
                'commission_cents' => (int) $sales->commission_cents,
                'wristbands'       => $wristbands,
                'topup_cents'      => (int) $topups,
            ];
        }

        // Calculate deltas
        $deltas = [];
        foreach (['vendors', 'transactions', 'items_sold', 'revenue_cents', 'commission_cents', 'wristbands', 'topup_cents'] as $metric) {
            $a = $stats['edition_a'][$metric];
            $b = $stats['edition_b'][$metric];
            $deltas[$metric] = [
                'absolute'   => $b - $a,
                'percentage' => $a > 0 ? round(($b - $a) / $a * 100, 2) : null,
            ];
        }

        return response()->json([
            'edition_a' => $stats['edition_a'],
            'edition_b' => $stats['edition_b'],
            'deltas'    => $deltas,
        ]);
    }

    /**
     * Per-vendor comparison across editions.
     */
    public function compareVendorAcrossEditions(Request $request, int $vendorId): JsonResponse
    {
        $data = $request->validate([
            'edition_ids'   => 'required|array|min:2',
            'edition_ids.*' => 'integer|exists:festival_editions,id',
        ]);

        $results = [];
        foreach ($data['edition_ids'] as $editionId) {
            $edition = FestivalEdition::findOrFail($editionId);

            $sales = VendorSaleItem::where('vendor_id', $vendorId)
                ->where('festival_edition_id', $editionId)
                ->selectRaw('
                    COUNT(*) as transactions,
                    SUM(quantity) as items_sold,
                    SUM(total_cents) as revenue_cents,
                    SUM(commission_cents) as commission_cents
                ')
                ->first();

            // Top products for this edition
            $topProducts = VendorSaleItem::where('vendor_id', $vendorId)
                ->where('festival_edition_id', $editionId)
                ->selectRaw('product_name, SUM(quantity) as qty, SUM(total_cents) as revenue_cents')
                ->groupBy('product_name')
                ->orderByDesc('revenue_cents')
                ->limit(10)
                ->get();

            $results[] = [
                'edition'      => $edition->only('id', 'name', 'year'),
                'transactions' => (int) $sales->transactions,
                'items_sold'   => (int) $sales->items_sold,
                'revenue_cents' => (int) $sales->revenue_cents,
                'commission_cents' => (int) $sales->commission_cents,
                'top_products' => $topProducts,
            ];
        }

        return response()->json(['editions' => $results]);
    }
}
