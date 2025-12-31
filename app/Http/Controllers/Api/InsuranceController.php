<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InsuranceConfig;
use App\Models\InsurancePolicy;
use App\Services\Insurance\InsuranceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class InsuranceController extends Controller
{
    public function __construct(protected InsuranceService $insuranceService)
    {}

    /**
     * GET /api/ti/quote
     * Get insurance quote
     */
    public function quote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'ticket_price' => 'required|numeric|min:0',
            'ticket_type' => 'nullable|string',
            'event_ref' => 'nullable|string',
            'country' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $quote = $this->insuranceService->quote($request->tenant, $request->all());

            return response()->json([
                'success' => true,
                'quote' => $quote,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/ti/issue
     * Issue insurance policy
     */
    public function issue(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'order_ref' => 'required|string',
            'ticket_ref' => 'nullable|string',
            'premium' => 'required|numeric|min:0',
            'ticket_price' => 'required|numeric|min:0',
            'user' => 'nullable|array',
            'event' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $policy = $this->insuranceService->issue(
                $request->tenant,
                $request->order_ref,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'policy' => $policy,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/ti/sync
     * Sync policy status with provider
     */
    public function sync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'policy_id' => 'required|uuid|exists:ti_policies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $policy = InsurancePolicy::findOrFail($request->policy_id);
            $result = $this->insuranceService->sync($policy);

            return response()->json([
                'success' => true,
                'policy' => $policy->fresh(),
                'sync_result' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/ti/policies
     * List policies with filters
     */
    public function listPolicies(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'status' => 'nullable|in:pending,issued,voided,refunded,error',
            'order_ref' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'export' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = InsurancePolicy::where('tenant_id', $request->tenant);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('order_ref')) {
            $query->where('order_ref', $request->order_ref);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $policies = $query->orderBy('created_at', 'desc')->paginate(50);

        // Export CSV if requested
        if ($request->export) {
            return $this->exportPoliciesCSV($policies->items());
        }

        return response()->json([
            'success' => true,
            'policies' => $policies,
        ]);
    }

    /**
     * POST /api/ti/{id}/void
     * Void a policy
     */
    public function void(string $id, Request $request): JsonResponse
    {
        try {
            $policy = InsurancePolicy::findOrFail($id);
            $reason = $request->input('reason');

            $this->insuranceService->void($policy, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Policy voided successfully',
                'policy' => $policy->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/ti/{id}/refund
     * Refund a policy
     */
    public function refund(string $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $policy = InsurancePolicy::findOrFail($id);
            $amount = $request->input('amount');

            $this->insuranceService->refund($policy, $amount);

            return response()->json([
                'success' => true,
                'message' => 'Policy refunded successfully',
                'policy' => $policy->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/ti/stats
     * Get insurance statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|uuid',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $stats = $this->insuranceService->getStats(
                $request->tenant,
                $request->from_date,
                $request->to_date
            );

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function exportPoliciesCSV($policies)
    {
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'Policy ID', 'Order Ref', 'Ticket Ref', 'Insurer', 'Premium',
            'Currency', 'Tax', 'Status', 'Policy Number', 'Created At',
            'Refund Amount', 'Error Message'
        ]);

        foreach ($policies as $policy) {
            fputcsv($output, [
                $policy->id,
                $policy->order_ref,
                $policy->ticket_ref,
                $policy->insurer,
                $policy->premium_amount,
                $policy->currency,
                $policy->tax_amount,
                $policy->status,
                $policy->policy_number,
                $policy->created_at->toDateTimeString(),
                $policy->refund_amount,
                $policy->error_message,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="insurance-policies-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
