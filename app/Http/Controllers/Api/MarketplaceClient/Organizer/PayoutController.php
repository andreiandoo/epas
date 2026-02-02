<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayoutController extends BaseController
{
    /**
     * Get balance overview
     */
    public function balance(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $minimumPayout = $organizer->getMinimumPayoutAmount();
        $canRequestPayout = $organizer->hasMinimumPayoutBalance()
            && !$organizer->hasPendingPayout()
            && !empty($organizer->payout_details);

        return $this->success([
            'balance' => [
                'available' => (float) $organizer->available_balance,
                'pending' => (float) $organizer->pending_balance,
                'total' => (float) $organizer->total_balance,
                'total_paid_out' => (float) $organizer->total_paid_out,
            ],
            'payout_settings' => [
                'minimum_amount' => $minimumPayout,
                'currency' => 'RON',
                'has_payout_details' => !empty($organizer->payout_details),
            ],
            'can_request_payout' => $canRequestPayout,
            'pending_payout' => $organizer->hasPendingPayout()
                ? $this->formatPayout($organizer->getPendingPayout())
                : null,
        ]);
    }

    /**
     * Get combined finance overview (balance + recent transactions + payouts)
     */
    public function finance(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Get recent transactions
        $transactions = MarketplaceTransaction::where('marketplace_organizer_id', $organizer->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'type_label' => $tx->getTypeLabel(),
                    'amount' => (float) $tx->amount,
                    'balance_after' => (float) $tx->balance_after,
                    'currency' => $tx->currency,
                    'description' => $tx->description,
                    'order_id' => $tx->order_id,
                    'payout_id' => $tx->marketplace_payout_id,
                    'date' => $tx->created_at->toIso8601String(),
                    'created_at' => $tx->created_at->toIso8601String(),
                ];
            });

        // Get recent payouts
        $payouts = MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($payout) {
                return $this->formatPayout($payout);
            });

        return $this->success([
            'available_balance' => (float) $organizer->available_balance,
            'pending_balance' => (float) $organizer->pending_balance,
            'total_earned' => (float) $organizer->total_revenue,
            'total_paid_out' => (float) $organizer->total_paid_out,
            'commission_rate' => $organizer->getEffectiveCommissionRate(),
            'commission_mode' => $organizer->getEffectiveCommissionMode(),
            'transactions' => $transactions,
            'payouts' => $payouts,
        ]);
    }

    /**
     * Get transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = MarketplaceTransaction::where('marketplace_organizer_id', $organizer->id);

        // Filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $transactions = $query->paginate($perPage);

        return $this->paginated($transactions, function ($tx) {
            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'type_label' => $tx->getTypeLabel(),
                'amount' => (float) $tx->amount,
                'balance_after' => (float) $tx->balance_after,
                'currency' => $tx->currency,
                'description' => $tx->description,
                'order_id' => $tx->order_id,
                'payout_id' => $tx->marketplace_payout_id,
                'created_at' => $tx->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get payout history
     */
    public function payouts(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = MarketplacePayout::where('marketplace_organizer_id', $organizer->id);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 50);
        $payouts = $query->paginate($perPage);

        return $this->paginated($payouts, function ($payout) {
            return $this->formatPayout($payout);
        });
    }

    /**
     * Get single payout details
     */
    public function showPayout(Request $request, int $payoutId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $payout = MarketplacePayout::where('id', $payoutId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        return $this->success([
            'payout' => $this->formatPayoutDetailed($payout),
        ]);
    }

    /**
     * Request a new payout
     */
    public function requestPayout(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        // Validate payout details exist
        if (empty($organizer->payout_details)) {
            return $this->error('Please set up your payout details first', 400);
        }

        // Check for pending payout
        if ($organizer->hasPendingPayout()) {
            return $this->error('You already have a pending payout request', 400);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        // Use requested amount or available balance
        $requestedAmount = $validated['amount'] ?? (float) $organizer->available_balance;

        // Validate minimum payout
        $minimumAmount = $organizer->getMinimumPayoutAmount();
        if ($requestedAmount < $minimumAmount) {
            return $this->error("Minimum payout amount is {$minimumAmount} RON", 400);
        }

        // Validate available balance
        if (!$organizer->canRequestPayout($requestedAmount)) {
            return $this->error('Insufficient available balance', 400);
        }

        try {
            DB::beginTransaction();

            // Calculate breakdown from completed orders in this period
            $lastPayout = $organizer->payouts()
                ->whereIn('status', ['completed'])
                ->orderByDesc('period_end')
                ->first();

            $periodStart = $lastPayout
                ? $lastPayout->period_end->addDay()
                : $organizer->created_at->toDateString();
            $periodEnd = now()->toDateString();

            $commissionRate = $organizer->getEffectiveCommissionRate();
            $grossAmount = $requestedAmount / (1 - $commissionRate / 100);
            $commissionAmount = $grossAmount - $requestedAmount;

            // Create payout request
            $payout = MarketplacePayout::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'amount' => $requestedAmount,
                'currency' => 'RON',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'gross_amount' => $grossAmount,
                'commission_amount' => $commissionAmount,
                'fees_amount' => 0,
                'adjustments_amount' => 0,
                'status' => 'pending',
                'payout_method' => $organizer->payout_details,
                'organizer_notes' => $validated['notes'] ?? null,
            ]);

            // Reserve the balance
            $organizer->reserveBalanceForPayout($requestedAmount);

            // Send notification
            $payout->notifyOrganizer('submitted');

            DB::commit();

            return $this->success([
                'payout' => $this->formatPayoutDetailed($payout),
                'balance' => [
                    'available' => (float) $organizer->fresh()->available_balance,
                    'pending' => (float) $organizer->fresh()->pending_balance,
                ],
            ], 'Payout request submitted successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create payout request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel a pending payout request
     */
    public function cancelPayout(Request $request, int $payoutId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $payout = MarketplacePayout::where('id', $payoutId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        if (!$payout->canBeCancelled()) {
            return $this->error('This payout request cannot be cancelled', 400);
        }

        try {
            DB::beginTransaction();

            // Return the balance
            $organizer->returnPendingBalance($payout->amount);

            // Cancel the payout
            $payout->cancel();

            DB::commit();

            return $this->success([
                'balance' => [
                    'available' => (float) $organizer->fresh()->available_balance,
                    'pending' => (float) $organizer->fresh()->pending_balance,
                ],
            ], 'Payout request cancelled');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to cancel payout request', 500);
        }
    }

    /**
     * Get monthly statements
     */
    public function statements(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $year = $request->input('year', now()->year);

        $statements = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            if ($startDate > now()) {
                break;
            }

            // Get transactions for this month
            $monthTransactions = $organizer->transactions()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $sales = $monthTransactions->where('type', 'sale')->sum('amount');
            $commissions = abs($monthTransactions->where('type', 'commission')->sum('amount'));
            $refunds = abs($monthTransactions->where('type', 'refund')->sum('amount'));
            $payouts = abs($monthTransactions->where('type', 'payout')->sum('amount'));

            $statements[] = [
                'month' => $startDate->format('Y-m'),
                'month_name' => $startDate->format('F Y'),
                'sales' => (float) $sales,
                'commissions' => (float) $commissions,
                'refunds' => (float) $refunds,
                'payouts' => (float) $payouts,
                'net' => (float) ($sales - $commissions - $refunds),
            ];
        }

        return $this->success([
            'year' => $year,
            'statements' => array_reverse($statements),
        ]);
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }

    /**
     * Format payout for list
     */
    protected function formatPayout(MarketplacePayout $payout): array
    {
        return [
            'id' => $payout->id,
            'reference' => $payout->reference,
            'amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'status' => $payout->status,
            'status_label' => $payout->status_label,
            'period_start' => $payout->period_start->toDateString(),
            'period_end' => $payout->period_end->toDateString(),
            'created_at' => $payout->created_at->toIso8601String(),
            'completed_at' => $payout->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Format payout with details
     */
    protected function formatPayoutDetailed(MarketplacePayout $payout): array
    {
        return [
            'id' => $payout->id,
            'reference' => $payout->reference,
            'amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'status' => $payout->status,
            'status_label' => $payout->status_label,
            'period_start' => $payout->period_start->toDateString(),
            'period_end' => $payout->period_end->toDateString(),
            'breakdown' => [
                'gross_amount' => (float) $payout->gross_amount,
                'commission_amount' => (float) $payout->commission_amount,
                'fees_amount' => (float) $payout->fees_amount,
                'adjustments_amount' => (float) $payout->adjustments_amount,
                'adjustments_note' => $payout->adjustments_note,
                'net_amount' => (float) $payout->amount,
            ],
            'payout_method' => [
                'bank_name' => $payout->payout_method['bank_name'] ?? null,
                'iban' => $payout->payout_method['iban'] ?? null,
                'account_holder' => $payout->payout_method['account_holder'] ?? null,
            ],
            'organizer_notes' => $payout->organizer_notes,
            'rejection_reason' => $payout->rejection_reason,
            'payment_reference' => $payout->payment_reference,
            'payment_notes' => $payout->payment_notes,
            'approved_at' => $payout->approved_at?->toIso8601String(),
            'processed_at' => $payout->processed_at?->toIso8601String(),
            'completed_at' => $payout->completed_at?->toIso8601String(),
            'rejected_at' => $payout->rejected_at?->toIso8601String(),
            'created_at' => $payout->created_at->toIso8601String(),
            'can_cancel' => $payout->canBeCancelled(),
        ];
    }
}
