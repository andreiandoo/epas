<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplacePayout;
use App\Models\Marketplace\MarketplacePayoutItem;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PayoutService
 *
 * Handles payout generation and processing for marketplace organizers.
 */
class PayoutService
{
    protected CommissionService $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * Generate a payout for an organizer for a specific period.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param Carbon $periodStart Period start date
     * @param Carbon $periodEnd Period end date
     * @return MarketplacePayout|null The created payout or null if no eligible orders
     */
    public function generatePayout(
        MarketplaceOrganizer $organizer,
        Carbon $periodStart,
        Carbon $periodEnd
    ): ?MarketplacePayout {
        // Get all unpaid orders in the period
        $orders = Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNull('payout_id')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        // Calculate totals
        $totals = $this->commissionService->calculateTotalsForOrders($orders);

        // Check minimum payout threshold
        if ($totals['organizer_revenue'] < $organizer->minimum_payout) {
            return null;
        }

        // Count tickets
        $ticketsCount = 0;
        foreach ($orders as $order) {
            $ticketsCount += $order->tickets()->count();
        }

        return DB::transaction(function () use ($organizer, $orders, $totals, $ticketsCount, $periodStart, $periodEnd) {
            // Create payout
            $payout = MarketplacePayout::create([
                'tenant_id' => $organizer->tenant_id,
                'organizer_id' => $organizer->id,
                'reference' => 'PAY-' . strtoupper(Str::random(10)),
                'amount' => $totals['organizer_revenue'],
                'currency' => $organizer->payout_currency,
                'status' => MarketplacePayout::STATUS_PENDING,
                'method' => $organizer->payout_method,
                'method_details' => $organizer->payout_details,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'orders_count' => $totals['orders_count'],
                'tickets_count' => $ticketsCount,
                'gross_revenue' => $totals['gross_revenue'],
                'tixello_fees' => $totals['tixello_fees'],
                'marketplace_fees' => $totals['marketplace_fees'],
                'refunds_total' => 0,
            ]);

            // Create payout items and link orders
            foreach ($orders as $order) {
                MarketplacePayoutItem::create([
                    'payout_id' => $payout->id,
                    'order_id' => $order->id,
                    'order_total' => $order->total,
                    'tixello_fee' => $order->tixello_commission ?? 0,
                    'marketplace_fee' => $order->marketplace_commission ?? 0,
                    'organizer_amount' => $order->organizer_revenue ?? 0,
                ]);

                $order->update(['payout_id' => $payout->id]);
            }

            // Update organizer statistics
            $organizer->refreshStatistics();

            return $payout;
        });
    }

    /**
     * Generate payouts for all eligible organizers.
     *
     * @param Carbon $periodStart Period start date
     * @param Carbon $periodEnd Period end date
     * @param int|null $tenantId Optional specific marketplace tenant
     * @return array Array of created payouts
     */
    public function generatePayoutsForAll(
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $tenantId = null
    ): array {
        $query = MarketplaceOrganizer::active();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $organizers = $query->get();
        $payouts = [];

        foreach ($organizers as $organizer) {
            $payout = $this->generatePayout($organizer, $periodStart, $periodEnd);
            if ($payout) {
                $payouts[] = $payout;
            }
        }

        return $payouts;
    }

    /**
     * Process a pending payout.
     *
     * @param MarketplacePayout $payout The payout to process
     * @param int|null $processedBy User ID who processes
     * @return bool Success status
     */
    public function processPayout(MarketplacePayout $payout, ?int $processedBy = null): bool
    {
        if (!$payout->canBeProcessed()) {
            return false;
        }

        return $payout->markAsProcessing($processedBy);
    }

    /**
     * Complete a processed payout.
     *
     * @param MarketplacePayout $payout The payout to complete
     * @param string|null $bankReference Optional bank reference
     * @return bool Success status
     */
    public function completePayout(MarketplacePayout $payout, ?string $bankReference = null): bool
    {
        if (!$payout->isProcessing()) {
            return false;
        }

        return $payout->markAsCompleted($bankReference);
    }

    /**
     * Fail a payout.
     *
     * @param MarketplacePayout $payout The payout
     * @param string $reason Failure reason
     * @return bool Success status
     */
    public function failPayout(MarketplacePayout $payout, string $reason): bool
    {
        return $payout->markAsFailed($reason);
    }

    /**
     * Cancel a pending payout.
     *
     * @param MarketplacePayout $payout The payout
     * @return bool Success status
     */
    public function cancelPayout(MarketplacePayout $payout): bool
    {
        return $payout->cancel();
    }

    /**
     * Get payout summary for a marketplace.
     *
     * @param int $tenantId The marketplace tenant ID
     * @return array Summary statistics
     */
    public function getMarketplaceSummary(int $tenantId): array
    {
        $payouts = MarketplacePayout::where('tenant_id', $tenantId);

        return [
            'total_payouts' => $payouts->count(),
            'pending_count' => (clone $payouts)->pending()->count(),
            'processing_count' => (clone $payouts)->processing()->count(),
            'completed_count' => (clone $payouts)->completed()->count(),
            'failed_count' => (clone $payouts)->failed()->count(),
            'total_paid_out' => (clone $payouts)->completed()->sum('amount'),
            'total_pending_amount' => (clone $payouts)->pending()->sum('amount'),
        ];
    }

    /**
     * Get payout summary for an organizer.
     *
     * @param int $organizerId The organizer ID
     * @return array Summary statistics
     */
    public function getOrganizerSummary(int $organizerId): array
    {
        $payouts = MarketplacePayout::where('organizer_id', $organizerId);

        return [
            'total_payouts' => $payouts->count(),
            'pending_count' => (clone $payouts)->pending()->count(),
            'completed_count' => (clone $payouts)->completed()->count(),
            'total_paid_out' => (clone $payouts)->completed()->sum('amount'),
            'pending_amount' => (clone $payouts)->pending()->sum('amount'),
            'last_payout_date' => (clone $payouts)->completed()->latest()->first()?->completed_at,
        ];
    }

    /**
     * Calculate the default payout period based on organizer's frequency.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @return array ['start' => Carbon, 'end' => Carbon]
     */
    public function calculatePayoutPeriod(MarketplaceOrganizer $organizer): array
    {
        $now = now();

        // Get last payout end date
        $lastPayout = $organizer->payouts()
            ->whereIn('status', [MarketplacePayout::STATUS_COMPLETED, MarketplacePayout::STATUS_PROCESSING])
            ->latest()
            ->first();

        $start = $lastPayout
            ? $lastPayout->period_end->addDay()
            : $organizer->created_at->startOfDay();

        $end = match ($organizer->payout_frequency) {
            MarketplaceOrganizer::PAYOUT_WEEKLY => $start->copy()->addWeek()->subDay(),
            MarketplaceOrganizer::PAYOUT_BIWEEKLY => $start->copy()->addWeeks(2)->subDay(),
            default => $start->copy()->addMonth()->subDay(), // monthly
        };

        // Don't go past today
        $end = $end->gt($now) ? $now : $end;

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
