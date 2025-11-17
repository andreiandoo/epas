<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromoCodes\PromoCodeService;
use App\Services\PromoCodes\PromoCodeValidator;
use App\Services\PromoCodes\PromoCodeCalculator;
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
}
