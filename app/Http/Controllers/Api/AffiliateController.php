<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Services\AffiliateTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AffiliateController extends Controller
{
    protected AffiliateTrackingService $affiliateService;

    public function __construct(AffiliateTrackingService $affiliateService)
    {
        $this->affiliateService = $affiliateService;
    }

    /**
     * Create a new affiliate (tenant admin only)
     * POST /api/affiliates
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'code' => 'required|string|unique:affiliates,code|max:255',
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'coupon_code' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $affiliate = Affiliate::create([
            'tenant_id' => $request->tenant_id,
            'code' => $request->code,
            'name' => $request->name,
            'contact_email' => $request->contact_email,
            'status' => 'active',
        ]);

        // Create coupon if provided
        if ($request->coupon_code) {
            $affiliate->coupons()->create([
                'coupon_code' => $request->coupon_code,
                'active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'affiliate' => $affiliate->load('coupons'),
        ], 201);
    }

    /**
     * Get affiliate dashboard (tenant admin)
     * GET /api/affiliates/{id}/dashboard
     */
    public function dashboard(Request $request, $id)
    {
        $affiliate = Affiliate::with(['conversions', 'clicks'])
            ->findOrFail($id);

        // Get date range from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get statistics
        $stats = $this->affiliateService->getAffiliateStats($affiliate->id, $startDate, $endDate);

        // Get conversions list
        $conversionsQuery = $affiliate->conversions()->with('tenant');

        if ($startDate) {
            $conversionsQuery->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $conversionsQuery->where('created_at', '<=', $endDate);
        }

        $conversions = $conversionsQuery->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'affiliate' => $affiliate,
            'stats' => $stats,
            'conversions' => $conversions,
        ]);
    }

    /**
     * Get current affiliate's own data (affiliate user)
     * GET /api/affiliate/me
     */
    public function me(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affiliate_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $affiliate = Affiliate::where('code', $request->affiliate_code)
            ->active()
            ->first();

        if (!$affiliate) {
            return response()->json(['error' => 'Affiliate not found'], 404);
        }

        // Get date range from request
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get statistics
        $stats = $this->affiliateService->getAffiliateStats($affiliate->id, $startDate, $endDate);

        // Get conversions
        $conversionsQuery = $affiliate->conversions();

        if ($startDate) {
            $conversionsQuery->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $conversionsQuery->where('created_at', '<=', $endDate);
        }

        $conversions = $conversionsQuery->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'affiliate' => [
                'code' => $affiliate->code,
                'name' => $affiliate->name,
            ],
            'stats' => $stats,
            'conversions' => $conversions,
        ]);
    }

    /**
     * Track affiliate click
     * POST /api/affiliates/track-click
     */
    public function trackClick(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'affiliate_code' => 'required|string',
            'url' => 'nullable|string',
            'utm' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->affiliateService->trackClick([
                'tenant_id' => $request->tenant_id,
                'affiliate_code' => $request->affiliate_code,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'url' => $request->url,
                'utm' => $request->utm,
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Attribute order to affiliate (preview)
     * POST /api/affiliates/attribute-order
     */
    public function attributeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'order_amount' => 'required|numeric|min:0',
            'coupon_code' => 'nullable|string',
            'cookie_value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attribution = $this->affiliateService->attributeOrder([
            'tenant_id' => $request->tenant_id,
            'coupon_code' => $request->coupon_code,
            'cookie_value' => $request->cookie_value,
            'order_amount' => $request->order_amount,
        ]);

        if (!$attribution) {
            return response()->json([
                'attributed' => false,
                'message' => 'No affiliate attribution found',
            ]);
        }

        return response()->json([
            'attributed' => true,
            'attribution' => $attribution,
        ]);
    }

    /**
     * Confirm order and create conversion
     * POST /api/affiliates/confirm-order
     */
    public function confirmOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'order_ref' => 'required|string',
            'order_amount' => 'required|numeric|min:0',
            'buyer_email' => 'nullable|email',
            'coupon_code' => 'nullable|string',
            'cookie_value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $conversion = $this->affiliateService->confirmOrder([
                'tenant_id' => $request->tenant_id,
                'order_ref' => $request->order_ref,
                'order_amount' => $request->order_amount,
                'buyer_email' => $request->buyer_email,
                'coupon_code' => $request->coupon_code,
                'cookie_value' => $request->cookie_value,
            ]);

            if (!$conversion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No conversion created (no attribution, self-purchase, or duplicate)',
                ]);
            }

            return response()->json([
                'success' => true,
                'conversion' => $conversion,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve conversion (on payment capture)
     * POST /api/affiliates/approve-order
     */
    public function approveOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'order_ref' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversion = $this->affiliateService->approveConversion(
            $request->order_ref,
            $request->tenant_id
        );

        if (!$conversion) {
            return response()->json([
                'success' => false,
                'message' => 'No pending conversion found for this order',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'conversion' => $conversion,
        ]);
    }

    /**
     * Reverse conversion (on refund/chargeback)
     * POST /api/affiliates/reverse-order
     */
    public function reverseOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'order_ref' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversion = $this->affiliateService->reverseConversion(
            $request->order_ref,
            $request->tenant_id
        );

        if (!$conversion) {
            return response()->json([
                'success' => false,
                'message' => 'No conversion found for this order',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'conversion' => $conversion,
        ]);
    }

    /**
     * Export conversions to CSV
     * GET /api/affiliates/{id}/export
     */
    public function export(Request $request, $id)
    {
        $affiliate = Affiliate::findOrFail($id);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = AffiliateConversion::where('affiliate_id', $affiliate->id);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $conversions = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $csv = "Order Ref,Amount,Commission Value,Commission Type,Status,Attributed By,Created At\n";

        foreach ($conversions as $conversion) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $conversion->order_ref,
                $conversion->amount,
                $conversion->commission_value,
                $conversion->commission_type,
                $conversion->status,
                $conversion->attributed_by,
                $conversion->created_at->format('Y-m-d H:i:s')
            );
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="affiliate-' . $affiliate->code . '-conversions.csv"');
    }
}
