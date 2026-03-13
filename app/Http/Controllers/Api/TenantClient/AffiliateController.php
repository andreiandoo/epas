<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateSettings;
use App\Models\AffiliateWithdrawal;
use App\Models\Customer;
use App\Models\CustomerToken;
use App\Services\AffiliateTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateController extends Controller
{
    protected AffiliateTrackingService $trackingService;

    public function __construct(AffiliateTrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    // ==========================================
    // PUBLIC ENDPOINTS
    // ==========================================

    /**
     * Get affiliate program information
     */
    public function programInfo(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Check if affiliate microservice is enabled
        $hasAffiliates = $tenant->microservices()
            ->where('slug', 'affiliates')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasAffiliates) {
            return response()->json([
                'success' => false,
                'enabled' => false,
                'message' => 'Affiliate program not enabled',
            ]);
        }

        $settings = AffiliateSettings::getOrCreate($tenant->id);

        if (!$settings->is_active || !$settings->allow_self_registration) {
            return response()->json([
                'success' => false,
                'enabled' => false,
                'message' => 'Affiliate registration not available',
            ]);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'data' => [
                'program_name' => $settings->program_name ?? 'Affiliate Program',
                'program_description' => $settings->program_description,
                'benefits' => $settings->program_benefits ?? [],
                'commission' => [
                    'type' => $settings->default_commission_type,
                    'value' => $settings->default_commission_value,
                    'formatted' => $settings->getFormattedCommission(),
                ],
                'cookie_duration_days' => $settings->cookie_duration_days,
                'min_withdrawal_amount' => $settings->min_withdrawal_amount,
                'currency' => $settings->currency,
                'require_approval' => $settings->require_approval,
                'payment_methods' => $settings->getPaymentMethodOptions(),
                'registration_terms' => $settings->registration_terms,
            ],
        ]);
    }

    /**
     * Register as an affiliate (requires authenticated customer)
     */
    public function register(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Check if affiliate microservice is enabled
        $hasAffiliates = $tenant->microservices()
            ->where('slug', 'affiliates')
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasAffiliates) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate program not enabled',
            ], 400);
        }

        $settings = AffiliateSettings::getOrCreate($tenant->id);

        if (!$settings->is_active || !$settings->allow_self_registration) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate registration not available',
            ], 400);
        }

        // Check if customer already has an affiliate account
        $existingAffiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if ($existingAffiliate) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an affiliate account',
                'data' => [
                    'status' => $existingAffiliate->status,
                    'code' => $existingAffiliate->code,
                ],
            ], 409);
        }

        $validated = $request->validate([
            'payment_method' => 'nullable|string|in:' . implode(',', array_keys($settings->getPaymentMethodOptions())),
            'payment_details' => 'nullable|array',
            'payment_details.bank_name' => 'nullable|string|max:100',
            'payment_details.iban' => 'nullable|string|max:50',
            'payment_details.account_holder' => 'nullable|string|max:100',
            'payment_details.paypal_email' => 'nullable|email|max:255',
            'payment_details.revolut_tag' => 'nullable|string|max:50',
            'payment_details.wise_email' => 'nullable|email|max:255',
            'accept_terms' => 'required|accepted',
        ]);

        // Create affiliate account
        $affiliate = Affiliate::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'name' => $customer->full_name,
            'contact_email' => $customer->email,
            'status' => $settings->require_approval ? Affiliate::STATUS_PENDING : Affiliate::STATUS_ACTIVE,
            'commission_type' => $settings->default_commission_type,
            'commission_rate' => $settings->default_commission_value,
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_details' => $validated['payment_details'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $settings->require_approval
                ? 'Affiliate application submitted. Awaiting approval.'
                : 'Affiliate account created successfully.',
            'data' => [
                'affiliate_id' => $affiliate->id,
                'code' => $affiliate->code,
                'status' => $affiliate->status,
                'tracking_url' => $affiliate->isActive() ? $affiliate->getTrackingUrl() : null,
            ],
        ]);
    }

    // ==========================================
    // AUTHENTICATED AFFILIATE ENDPOINTS
    // ==========================================

    /**
     * Get affiliate dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
                'has_affiliate' => false,
            ], 404);
        }

        $settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();

        // Get stats
        $stats = $this->trackingService->getAffiliateStats($affiliate->id);

        // Recalculate balances
        $affiliate->recalculateBalances();
        $affiliate->refresh();

        return response()->json([
            'success' => true,
            'has_affiliate' => true,
            'data' => [
                'affiliate' => [
                    'id' => $affiliate->id,
                    'code' => $affiliate->code,
                    'name' => $affiliate->name,
                    'status' => $affiliate->status,
                    'status_label' => $affiliate->getStatusLabel(),
                    'status_color' => $affiliate->getStatusColor(),
                    'is_active' => $affiliate->isActive(),
                    'is_pending' => $affiliate->isPending(),
                    'tracking_url' => $affiliate->isActive() ? $affiliate->getTrackingUrl() : null,
                    'commission' => [
                        'type' => $affiliate->commission_type,
                        'rate' => $affiliate->commission_rate,
                        'formatted' => $affiliate->getFormattedCommission(),
                    ],
                    'created_at' => $affiliate->created_at->toIso8601String(),
                ],
                'balance' => [
                    'pending' => (float) $affiliate->pending_balance,
                    'available' => (float) $affiliate->available_balance,
                    'total_withdrawn' => (float) $affiliate->total_withdrawn,
                    'total_earned' => $stats['total_commission'] ?? 0,
                    'currency' => $settings?->currency ?? 'RON',
                ],
                'stats' => [
                    'total_clicks' => $affiliate->clicks()->count(),
                    'total_conversions' => $stats['total_conversions'] ?? 0,
                    'pending_conversions' => $stats['pending_conversions'] ?? 0,
                    'approved_conversions' => $stats['approved_conversions'] ?? 0,
                    'reversed_conversions' => $stats['reversed_conversions'] ?? 0,
                    'total_sales' => $stats['total_sales'] ?? 0,
                    'pending_commission' => $stats['pending_commission'] ?? 0,
                ],
                'withdrawal' => [
                    'min_amount' => $settings?->min_withdrawal_amount ?? 50,
                    'can_withdraw' => $affiliate->available_balance >= ($settings?->min_withdrawal_amount ?? 50),
                    'payment_methods' => $settings?->getPaymentMethodOptions() ?? [],
                    'processing_days' => $settings?->withdrawal_processing_days ?? 14,
                ],
            ],
        ]);
    }

    /**
     * Get conversion history
     */
    public function conversions(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
            ], 404);
        }

        $limit = min($request->input('limit', 20), 100);
        $offset = $request->input('offset', 0);
        $status = $request->input('status'); // pending, approved, reversed

        $query = $affiliate->conversions()
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $conversions = $query->skip($offset)->take($limit)->get();

        $settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'conversions' => $conversions->map(fn ($c) => [
                    'id' => $c->id,
                    'order_ref' => $c->order_ref,
                    'amount' => (float) $c->amount,
                    'commission_value' => (float) $c->commission_value,
                    'commission_type' => $c->commission_type,
                    'status' => $c->status,
                    'attributed_by' => $c->attributed_by,
                    'created_at' => $c->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total,
                    'offset' => $offset,
                    'limit' => $limit,
                ],
                'currency' => $settings?->currency ?? 'RON',
            ],
        ]);
    }

    /**
     * Get click statistics
     */
    public function clicks(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
            ], 404);
        }

        // Get click stats by date (last 30 days)
        $clicksByDate = $affiliate->clicks()
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as clicks')
            ->where('clicked_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($c) => [
                'date' => $c->date,
                'clicks' => $c->clicks,
            ]);

        $totalClicks = $affiliate->clicks()->count();
        $clicksThisMonth = $affiliate->clicks()
            ->whereMonth('clicked_at', now()->month)
            ->whereYear('clicked_at', now()->year)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_clicks' => $totalClicks,
                'clicks_this_month' => $clicksThisMonth,
                'clicks_by_date' => $clicksByDate,
            ],
        ]);
    }

    /**
     * Update payment details
     */
    public function updatePaymentDetails(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
            ], 404);
        }

        $settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();

        $validated = $request->validate([
            'payment_method' => 'required|string|in:' . implode(',', array_keys($settings?->getPaymentMethodOptions() ?? ['bank_transfer' => 'Bank Transfer'])),
            'payment_details' => 'required|array',
            'payment_details.bank_name' => 'required_if:payment_method,bank_transfer|nullable|string|max:100',
            'payment_details.iban' => 'required_if:payment_method,bank_transfer|nullable|string|max:50',
            'payment_details.account_holder' => 'required_if:payment_method,bank_transfer|nullable|string|max:100',
            'payment_details.paypal_email' => 'required_if:payment_method,paypal|nullable|email|max:255',
            'payment_details.revolut_tag' => 'required_if:payment_method,revolut|nullable|string|max:50',
            'payment_details.wise_email' => 'required_if:payment_method,wise|nullable|email|max:255',
        ]);

        $affiliate->update([
            'payment_method' => $validated['payment_method'],
            'payment_details' => $validated['payment_details'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment details updated successfully',
        ]);
    }

    // ==========================================
    // WITHDRAWAL ENDPOINTS
    // ==========================================

    /**
     * Get withdrawal history
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
            ], 404);
        }

        $limit = min($request->input('limit', 20), 100);
        $offset = $request->input('offset', 0);

        $query = $affiliate->withdrawals()
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $withdrawals = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals->map(fn ($w) => [
                    'id' => $w->id,
                    'reference' => $w->reference,
                    'amount' => (float) $w->amount,
                    'currency' => $w->currency,
                    'status' => $w->status,
                    'status_label' => $w->getStatusLabel(),
                    'status_color' => $w->getStatusColor(),
                    'payment_method' => $w->payment_method,
                    'payment_method_label' => $w->getPaymentMethodLabel(),
                    'rejection_reason' => $w->rejection_reason,
                    'transaction_id' => $w->transaction_id,
                    'created_at' => $w->created_at->toIso8601String(),
                    'processed_at' => $w->processed_at?->toIso8601String(),
                ]),
                'pagination' => [
                    'total' => $total,
                    'has_more' => ($offset + $limit) < $total,
                    'offset' => $offset,
                    'limit' => $limit,
                ],
            ],
        ]);
    }

    /**
     * Request a withdrawal
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
            ], 404);
        }

        if (!$affiliate->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your affiliate account is not active',
            ], 400);
        }

        $settings = AffiliateSettings::where('tenant_id', $tenant->id)->first();
        $minAmount = $settings?->min_withdrawal_amount ?? 50;

        $validated = $request->validate([
            'amount' => "required|numeric|min:{$minAmount}",
            'payment_method' => 'nullable|string|in:' . implode(',', array_keys($settings?->getPaymentMethodOptions() ?? ['bank_transfer' => 'Bank Transfer'])),
            'payment_details' => 'nullable|array',
        ]);

        $amount = (float) $validated['amount'];

        // Recalculate balances first
        $affiliate->recalculateBalances();
        $affiliate->refresh();

        if ($amount > $affiliate->available_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient available balance',
                'available_balance' => $affiliate->available_balance,
            ], 400);
        }

        // Use provided payment details or fall back to saved ones
        $paymentMethod = $validated['payment_method'] ?? $affiliate->payment_method;
        $paymentDetails = $validated['payment_details'] ?? $affiliate->payment_details;

        if (!$paymentMethod || !$paymentDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Payment details are required. Please update your payment information.',
            ], 400);
        }

        // Create withdrawal request
        $withdrawal = $affiliate->requestWithdrawal(
            $amount,
            $paymentMethod,
            $paymentDetails,
            $request->ip()
        );

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create withdrawal request',
            ], 500);
        }

        Log::info('Affiliate withdrawal requested', [
            'tenant_id' => $tenant->id,
            'affiliate_id' => $affiliate->id,
            'withdrawal_id' => $withdrawal->id,
            'amount' => $amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'data' => [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $withdrawal->reference,
                'amount' => (float) $withdrawal->amount,
                'status' => $withdrawal->status,
                'estimated_processing_days' => $settings?->withdrawal_processing_days ?? 14,
                'new_available_balance' => $affiliate->fresh()->available_balance,
            ],
        ]);
    }

    /**
     * Cancel a pending withdrawal
     */
    public function cancelWithdrawal(Request $request, int $withdrawalId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $customer = $this->getAuthenticatedCustomer($request);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $affiliate = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'Affiliate account not found',
            ], 404);
        }

        $withdrawal = AffiliateWithdrawal::where('id', $withdrawalId)
            ->where('affiliate_id', $affiliate->id)
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal not found',
            ], 404);
        }

        if (!$withdrawal->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'This withdrawal cannot be cancelled',
            ], 400);
        }

        $withdrawal->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal cancelled successfully',
            'data' => [
                'new_available_balance' => $affiliate->fresh()->available_balance,
            ],
        ]);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Get authenticated customer from request
     */
    private function getAuthenticatedCustomer(Request $request): ?Customer
    {
        $token = $request->bearerToken();

        if (!$token) {
            return null;
        }

        $customerToken = CustomerToken::where('token', hash('sha256', $token))
            ->with('customer')
            ->first();

        if (!$customerToken || $customerToken->isExpired()) {
            return null;
        }

        $customerToken->markAsUsed();

        return $customerToken->customer;
    }
}
