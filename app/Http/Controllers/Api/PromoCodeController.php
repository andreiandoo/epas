<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromoCodes\PromoCodeService;
use App\Services\PromoCodes\PromoCodeValidator;
use App\Services\PromoCodes\PromoCodeCalculator;
use App\Services\PromoCodes\PromoCodeExportService;
use App\Services\PromoCodes\PromoCodeImportService;
use App\Services\PromoCodes\PromoCodeUsageAnalyzer;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Promo Code Controller
 *
 * Manages promo/voucher codes for tenants
 */
class PromoCodeController extends Controller
{
    public function __construct(
        protected PromoCodeService $promoCodeService,
        protected PromoCodeValidator $validator,
        protected PromoCodeCalculator $calculator,
        protected PromoCodeExportService $exportService,
        protected PromoCodeImportService $importService,
        protected PromoCodeUsageAnalyzer $usageAnalyzer,
        protected AuditService $auditService
    ) {}

    /**
     * List all promo codes for a tenant
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function index(Request $request, string $tenantId): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'applies_to' => $request->query('applies_to'),
            'event_id' => $request->query('event_id'),
            'search' => $request->query('search'),
            'order_by' => $request->query('order_by', 'created_at'),
            'order_dir' => $request->query('order_dir', 'desc'),
            'limit' => min($request->query('limit', 50), 100),
            'offset' => $request->query('offset', 0),
        ];

        $promoCodes = $this->promoCodeService->list($tenantId, array_filter($filters));

        return response()->json([
            'success' => true,
            'data' => $promoCodes,
        ]);
    }

    /**
     * Get a single promo code
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $promoCode = $this->promoCodeService->getById($id);

            return response()->json([
                'success' => true,
                'data' => $promoCode,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create a new promo code
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function store(Request $request, string $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50|alpha_num',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:fixed,percentage',
            'value' => 'required|numeric|min:0',
            'applies_to' => 'required|in:cart,event,ticket_type',
            'event_id' => 'nullable|uuid',
            'ticket_type_id' => 'nullable|uuid',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_tickets' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_public' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Additional validation
        if ($request->type === 'percentage' && $request->value > 100) {
            return response()->json([
                'success' => false,
                'error' => 'Percentage value cannot exceed 100',
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['created_by'] = auth()->id();

            $promoCode = $this->promoCodeService->create($tenantId, $data);

            // Audit log
            $this->auditService->log([
                'tenant_id' => $tenantId,
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'actor_name' => auth()->user()->name ?? 'Unknown',
                'action' => 'promo_code.created',
                'resource_type' => 'promo_code',
                'resource_id' => $promoCode['id'],
                'metadata' => [
                    'code' => $promoCode['code'],
                    'type' => $promoCode['type'],
                    'value' => $promoCode['value'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'severity' => 'low',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Promo code created successfully',
                'data' => $promoCode,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update a promo code
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_tickets' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'status' => 'nullable|in:active,inactive',
            'is_public' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $promoCode = $this->promoCodeService->update($id, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Promo code updated successfully',
                'data' => $promoCode,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Deactivate a promo code
     *
     * @param string $id
     * @return JsonResponse
     */
    public function deactivate(string $id): JsonResponse
    {
        try {
            $this->promoCodeService->deactivate($id);

            return response()->json([
                'success' => true,
                'message' => 'Promo code deactivated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Delete a promo code
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->promoCodeService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Promo code deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get usage statistics for a promo code
     *
     * @param string $id
     * @return JsonResponse
     */
    public function stats(string $id): JsonResponse
    {
        try {
            $stats = $this->promoCodeService->getUsageStats($id);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Validate a promo code for a cart (public endpoint)
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function validate(Request $request, string $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'cart' => 'required|array',
            'cart.total' => 'required|numeric|min:0',
            'cart.items' => 'nullable|array',
            'customer_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = strtoupper($request->code);
        $promoCode = $this->promoCodeService->getByCode($tenantId, $code);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'reason' => 'Invalid promo code',
            ], 404);
        }

        $validation = $this->validator->validate(
            $promoCode,
            $request->cart,
            $request->customer_id
        );

        if ($validation['valid']) {
            $calculation = $this->calculator->calculate($promoCode, $request->cart);

            return response()->json([
                'success' => true,
                'valid' => true,
                'promo_code' => [
                    'id' => $promoCode['id'],
                    'code' => $promoCode['code'],
                    'description' => $promoCode['description'],
                    'type' => $promoCode['type'],
                    'value' => $promoCode['value'],
                ],
                'discount' => [
                    'amount' => $calculation['discount_amount'],
                    'formatted' => $this->calculator->formatDiscount($promoCode),
                    'applied_to' => $calculation['applied_to'],
                    'final_amount' => $calculation['final_amount'],
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'valid' => false,
            'reason' => $validation['reason'],
        ], 400);
    }

    /**
     * Bulk create promo codes
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function bulkCreate(Request $request, string $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'count' => 'required|integer|min:1|max:1000',
            'template' => 'required|array',
            'template.type' => 'required|in:fixed,percentage',
            'template.value' => 'required|numeric|min:0',
            'template.applies_to' => 'required|in:cart,event,ticket_type',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $codes = $this->promoCodeService->bulkCreate(
                $tenantId,
                $request->count,
                $request->template
            );

            return response()->json([
                'success' => true,
                'message' => count($codes) . ' promo codes created successfully',
                'data' => $codes,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk activate promo codes
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'promo_code_ids' => 'required|array',
            'promo_code_ids.*' => 'uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $count = $this->promoCodeService->bulkActivate($request->promo_code_ids);

            return response()->json([
                'success' => true,
                'message' => "{$count} promo code(s) activated successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk deactivate promo codes
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'promo_code_ids' => 'required|array',
            'promo_code_ids.*' => 'uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $count = $this->promoCodeService->bulkDeactivate(
                $request->promo_code_ids,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} promo code(s) deactivated successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk delete promo codes
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'promo_code_ids' => 'required|array',
            'promo_code_ids.*' => 'uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $count = $this->promoCodeService->bulkDelete($request->promo_code_ids);

            return response()->json([
                'success' => true,
                'message' => "{$count} promo code(s) deleted successfully",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Clone a promo code
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50|alpha_num',
            'name' => 'nullable|string|max:255',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $newCode = $this->promoCodeService->clone($id, $validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Promo code duplicated successfully',
                'data' => $newCode,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Export promo codes to CSV
     *
     * @param Request $request
     * @param string $tenantId
     * @return Response
     */
    public function export(Request $request, string $tenantId)
    {
        $filters = [
            'status' => $request->query('status'),
            'type' => $request->query('type'),
        ];

        $csv = $this->exportService->exportToCSV($tenantId, array_filter($filters));

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="promo-codes-' . date('Y-m-d') . '.csv"');
    }

    /**
     * Import promo codes from CSV
     *
     * @param Request $request
     * @param string $tenantId
     * @return JsonResponse
     */
    public function import(Request $request, string $tenantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $csvContent = file_get_contents($request->file('file')->getRealPath());
            $results = $this->importService->importFromCSV($tenantId, $csvContent);

            return response()->json([
                'success' => true,
                'message' => "Import completed: {$results['success']} successful, {$results['failed']} failed",
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get usage history for a promo code
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function usageHistory(Request $request, string $id): JsonResponse
    {
        try {
            $filters = [
                'customer_id' => $request->query('customer_id'),
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'min_discount' => $request->query('min_discount'),
                'order_by' => $request->query('order_by', 'used_at'),
                'order_dir' => $request->query('order_dir', 'desc'),
                'limit' => min($request->query('limit', 100), 500),
                'offset' => $request->query('offset', 0),
            ];

            $usage = $this->usageAnalyzer->getUsageHistory($id, array_filter($filters));

            return response()->json([
                'success' => true,
                'data' => $usage,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Detect fraud patterns
     *
     * @param string $id
     * @return JsonResponse
     */
    public function fraudDetection(string $id): JsonResponse
    {
        try {
            $fraudAnalysis = $this->usageAnalyzer->detectFraud($id);

            return response()->json([
                'success' => true,
                'data' => $fraudAnalysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get usage timeline analytics
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function usageTimeline(Request $request, string $id): JsonResponse
    {
        try {
            $groupBy = $request->query('group_by', 'day');

            if (!in_array($groupBy, ['day', 'week', 'month'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid group_by parameter. Must be: day, week, or month',
                ], 422);
            }

            $timeline = $this->usageAnalyzer->getUsageTimeline($id, $groupBy);

            return response()->json([
                'success' => true,
                'data' => $timeline,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Export usage history to CSV
     *
     * @param string $id
     * @return Response
     */
    public function exportUsage(string $id)
    {
        try {
            $csv = $this->exportService->exportUsageToCSV($id);

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="promo-code-usage-' . $id . '.csv"');
        } catch (\Exception $e) {
            return response($e->getMessage(), 404);
        }
    }
}
