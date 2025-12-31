<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Admin;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutsController extends BaseController
{
    /**
     * List all payouts
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.view')) {
            return $this->error('Unauthorized', 403);
        }

        $clientId = $admin->marketplace_client_id;

        $query = MarketplacePayout::where('marketplace_client_id', $clientId)
            ->with('organizer:id,name,email');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('organizer_id')) {
            $query->where('marketplace_organizer_id', $request->organizer_id);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $payouts = $query->paginate($perPage);

        return $this->paginated($payouts, function ($payout) {
            return [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'amount' => (float) $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status,
                'gross_amount' => (float) $payout->gross_amount,
                'commission_amount' => (float) $payout->commission_amount,
                'period_start' => $payout->period_start->format('Y-m-d'),
                'period_end' => $payout->period_end->format('Y-m-d'),
                'organizer' => $payout->organizer ? [
                    'id' => $payout->organizer->id,
                    'name' => $payout->organizer->name,
                    'email' => $payout->organizer->email,
                ] : null,
                'created_at' => $payout->created_at->toIso8601String(),
                'approved_at' => $payout->approved_at?->toIso8601String(),
                'completed_at' => $payout->completed_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Get pending payouts
     */
    public function pending(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.view')) {
            return $this->error('Unauthorized', 403);
        }

        $payouts = MarketplacePayout::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('status', 'pending')
            ->with('organizer:id,name,email,payout_details')
            ->orderBy('created_at')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'amount' => (float) $p->amount,
                'currency' => $p->currency,
                'gross_amount' => (float) $p->gross_amount,
                'commission_amount' => (float) $p->commission_amount,
                'fees_amount' => (float) $p->fees_amount,
                'adjustments_amount' => (float) $p->adjustments_amount,
                'adjustments_note' => $p->adjustments_note,
                'period_start' => $p->period_start->format('Y-m-d'),
                'period_end' => $p->period_end->format('Y-m-d'),
                'payout_method' => $p->payout_method,
                'organizer_notes' => $p->organizer_notes,
                'organizer' => $p->organizer ? [
                    'id' => $p->organizer->id,
                    'name' => $p->organizer->name,
                    'email' => $p->organizer->email,
                    'payout_details' => $p->organizer->payout_details,
                ] : null,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        $totalAmount = $payouts->sum('amount');

        return $this->success([
            'payouts' => $payouts,
            'count' => $payouts->count(),
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Get single payout details
     */
    public function show(Request $request, int $payoutId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.view')) {
            return $this->error('Unauthorized', 403);
        }

        $payout = MarketplacePayout::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $payoutId)
            ->with(['organizer', 'approvedBy:id,name', 'processedBy:id,name', 'rejectedBy:id,name'])
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        return $this->success([
            'payout' => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'amount' => (float) $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status,
                'gross_amount' => (float) $payout->gross_amount,
                'commission_amount' => (float) $payout->commission_amount,
                'fees_amount' => (float) $payout->fees_amount,
                'adjustments_amount' => (float) $payout->adjustments_amount,
                'adjustments_note' => $payout->adjustments_note,
                'period_start' => $payout->period_start->format('Y-m-d'),
                'period_end' => $payout->period_end->format('Y-m-d'),
                'payout_method' => $payout->payout_method,
                'payment_reference' => $payout->payment_reference,
                'payment_method' => $payout->payment_method,
                'payment_notes' => $payout->payment_notes,
                'admin_notes' => $payout->admin_notes,
                'organizer_notes' => $payout->organizer_notes,
                'rejection_reason' => $payout->rejection_reason,
                'approved_at' => $payout->approved_at?->toIso8601String(),
                'approved_by' => $payout->approvedBy?->name,
                'processed_at' => $payout->processed_at?->toIso8601String(),
                'processed_by' => $payout->processedBy?->name,
                'completed_at' => $payout->completed_at?->toIso8601String(),
                'rejected_at' => $payout->rejected_at?->toIso8601String(),
                'rejected_by' => $payout->rejectedBy?->name,
                'created_at' => $payout->created_at->toIso8601String(),
            ],
            'organizer' => $payout->organizer ? [
                'id' => $payout->organizer->id,
                'name' => $payout->organizer->name,
                'email' => $payout->organizer->email,
                'company_name' => $payout->organizer->company_name,
                'company_tax_id' => $payout->organizer->company_tax_id,
                'payout_details' => $payout->organizer->payout_details,
                'available_balance' => (float) $payout->organizer->available_balance,
            ] : null,
        ]);
    }

    /**
     * Approve a payout
     */
    public function approve(Request $request, int $payoutId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.process')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $payout = MarketplacePayout::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $payoutId)
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        if ($payout->status !== 'pending') {
            return $this->error('Payout is not pending', 400);
        }

        $payout->update([
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? $payout->admin_notes,
        ]);

        Log::channel('marketplace')->info('Payout approved', [
            'payout_id' => $payout->id,
            'admin_id' => $admin->id,
            'amount' => $payout->amount,
        ]);

        return $this->success([
            'payout' => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'status' => $payout->status,
                'approved_at' => $payout->approved_at->toIso8601String(),
            ],
        ], 'Payout approved');
    }

    /**
     * Mark payout as processing
     */
    public function markProcessing(Request $request, int $payoutId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.process')) {
            return $this->error('Unauthorized', 403);
        }

        $payout = MarketplacePayout::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $payoutId)
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        if ($payout->status !== 'approved') {
            return $this->error('Payout must be approved first', 400);
        }

        $payout->update([
            'status' => 'processing',
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        Log::channel('marketplace')->info('Payout marked as processing', [
            'payout_id' => $payout->id,
            'admin_id' => $admin->id,
        ]);

        return $this->success([
            'payout' => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'status' => $payout->status,
            ],
        ], 'Payout marked as processing');
    }

    /**
     * Complete a payout
     */
    public function complete(Request $request, int $payoutId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.process')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'payment_reference' => 'required|string|max:255',
            'payment_method' => 'required|string|in:bank_transfer,paypal,stripe,other',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        $payout = MarketplacePayout::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $payoutId)
            ->with('organizer')
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        if (!in_array($payout->status, ['approved', 'processing'])) {
            return $this->error('Payout must be approved or processing', 400);
        }

        try {
            DB::beginTransaction();

            $payout->update([
                'status' => 'completed',
                'completed_at' => now(),
                'payment_reference' => $validated['payment_reference'],
                'payment_method' => $validated['payment_method'],
                'payment_notes' => $validated['payment_notes'],
            ]);

            // Record transaction in organizer's ledger
            if ($payout->organizer) {
                MarketplaceTransaction::create([
                    'marketplace_client_id' => $payout->marketplace_client_id,
                    'marketplace_organizer_id' => $payout->marketplace_organizer_id,
                    'type' => 'payout',
                    'amount' => -$payout->amount, // Negative for payout
                    'currency' => $payout->currency,
                    'balance_after' => $payout->organizer->available_balance - $payout->amount,
                    'marketplace_payout_id' => $payout->id,
                    'description' => "Payout {$payout->reference} completed",
                    'metadata' => [
                        'payment_reference' => $validated['payment_reference'],
                        'payment_method' => $validated['payment_method'],
                    ],
                ]);

                // Update organizer balances
                $payout->organizer->decrement('available_balance', $payout->amount);
                $payout->organizer->increment('total_paid_out', $payout->amount);
            }

            DB::commit();

            Log::channel('marketplace')->info('Payout completed', [
                'payout_id' => $payout->id,
                'admin_id' => $admin->id,
                'amount' => $payout->amount,
                'payment_reference' => $validated['payment_reference'],
            ]);

            // TODO: Send notification to organizer

            return $this->success([
                'payout' => [
                    'id' => $payout->id,
                    'reference' => $payout->reference,
                    'status' => $payout->status,
                    'completed_at' => $payout->completed_at->toIso8601String(),
                ],
            ], 'Payout completed');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('marketplace')->error('Failed to complete payout', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to complete payout', 500);
        }
    }

    /**
     * Reject a payout
     */
    public function reject(Request $request, int $payoutId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.process')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $payout = MarketplacePayout::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $payoutId)
            ->with('organizer')
            ->first();

        if (!$payout) {
            return $this->error('Payout not found', 404);
        }

        if (!in_array($payout->status, ['pending', 'approved'])) {
            return $this->error('Cannot reject this payout', 400);
        }

        try {
            DB::beginTransaction();

            $payout->update([
                'status' => 'rejected',
                'rejected_by' => $admin->id,
                'rejected_at' => now(),
                'rejection_reason' => $validated['reason'],
            ]);

            // If organizer balance was reserved, restore it
            if ($payout->organizer) {
                $payout->organizer->increment('available_balance', $payout->amount);
                $payout->organizer->decrement('pending_balance', $payout->amount);
            }

            DB::commit();

            Log::channel('marketplace')->info('Payout rejected', [
                'payout_id' => $payout->id,
                'admin_id' => $admin->id,
                'reason' => $validated['reason'],
            ]);

            // TODO: Send notification to organizer

            return $this->success([
                'payout' => [
                    'id' => $payout->id,
                    'reference' => $payout->reference,
                    'status' => $payout->status,
                ],
            ], 'Payout rejected');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to reject payout', 500);
        }
    }

    /**
     * Get payout statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('payouts.view')) {
            return $this->error('Unauthorized', 403);
        }

        $clientId = $admin->marketplace_client_id;

        $stats = [
            'pending' => [
                'count' => MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'pending')->count(),
                'amount' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'pending')->sum('amount'),
            ],
            'approved' => [
                'count' => MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'approved')->count(),
                'amount' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'approved')->sum('amount'),
            ],
            'processing' => [
                'count' => MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'processing')->count(),
                'amount' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'processing')->sum('amount'),
            ],
            'completed_this_month' => [
                'count' => MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->whereMonth('completed_at', now()->month)
                    ->whereYear('completed_at', now()->year)
                    ->count(),
                'amount' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->whereMonth('completed_at', now()->month)
                    ->whereYear('completed_at', now()->year)
                    ->sum('amount'),
            ],
            'total_completed' => [
                'count' => MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')->count(),
                'amount' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')->sum('amount'),
            ],
        ];

        return $this->success(['stats' => $stats]);
    }

    /**
     * Require authenticated admin
     */
    protected function requireAdmin(Request $request): MarketplaceAdmin
    {
        $admin = $request->user();

        if (!$admin instanceof MarketplaceAdmin) {
            abort(401, 'Unauthorized');
        }

        return $admin;
    }
}
