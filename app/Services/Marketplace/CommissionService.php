<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\Tenant;

/**
 * CommissionService
 *
 * Handles commission calculations for marketplace orders.
 * Ensures Tixello always gets 1% and marketplace gets their configured commission.
 */
class CommissionService
{
    /**
     * Calculate and store commission breakdown for an order.
     *
     * @param Order $order The order to calculate commission for
     * @return array The commission breakdown
     */
    public function calculateForOrder(Order $order): array
    {
        $tenant = $order->tenant;
        $organizer = $order->organizer;

        // For standard tenants (not marketplace), only calculate Tixello commission
        if (!$tenant->isMarketplace() || !$organizer) {
            $tixelloCommission = round($order->total * Tenant::TIXELLO_PLATFORM_FEE, 2);

            $order->update([
                'tixello_commission' => $tixelloCommission,
                'marketplace_commission' => 0,
                'organizer_revenue' => 0,
            ]);

            return [
                'order_total' => $order->total,
                'tixello_commission' => $tixelloCommission,
                'marketplace_commission' => 0,
                'organizer_revenue' => 0,
            ];
        }

        // Calculate full commission breakdown for marketplace orders
        $breakdown = $tenant->calculateMarketplaceCommission($order->total, $organizer);

        // Update order with commission breakdown
        $order->update([
            'tixello_commission' => $breakdown['tixello_commission'],
            'marketplace_commission' => $breakdown['marketplace_commission'],
            'organizer_revenue' => $breakdown['organizer_revenue'],
        ]);

        return $breakdown;
    }

    /**
     * Preview commission for a given amount (for UI displays).
     *
     * @param Tenant $tenant The marketplace tenant
     * @param float $amount The order total to preview
     * @param MarketplaceOrganizer|null $organizer Optional organizer for override
     * @return array Commission breakdown preview
     */
    public function preview(Tenant $tenant, float $amount, ?MarketplaceOrganizer $organizer = null): array
    {
        return $tenant->calculateMarketplaceCommission($amount, $organizer);
    }

    /**
     * Calculate commission totals for a collection of orders.
     *
     * @param \Illuminate\Support\Collection $orders Collection of Order models
     * @return array Aggregated commission data
     */
    public function calculateTotalsForOrders($orders): array
    {
        $totals = [
            'gross_revenue' => 0,
            'tixello_fees' => 0,
            'marketplace_fees' => 0,
            'organizer_revenue' => 0,
            'orders_count' => 0,
        ];

        foreach ($orders as $order) {
            $totals['gross_revenue'] += $order->total;
            $totals['tixello_fees'] += (float) ($order->tixello_commission ?? 0);
            $totals['marketplace_fees'] += (float) ($order->marketplace_commission ?? 0);
            $totals['organizer_revenue'] += (float) ($order->organizer_revenue ?? 0);
            $totals['orders_count']++;
        }

        return $totals;
    }

    /**
     * Recalculate commission for all orders of an organizer.
     * Useful when commission rates change.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @param bool $onlyUnpaid Only recalculate orders not yet paid out
     * @return int Number of orders updated
     */
    public function recalculateForOrganizer(MarketplaceOrganizer $organizer, bool $onlyUnpaid = true): int
    {
        $query = $organizer->orders();

        if ($onlyUnpaid) {
            $query->whereNull('payout_id');
        }

        $orders = $query->get();
        $count = 0;

        foreach ($orders as $order) {
            $this->calculateForOrder($order);
            $count++;
        }

        return $count;
    }

    /**
     * Get commission summary for a period.
     *
     * @param Tenant $tenant The marketplace tenant
     * @param \Carbon\Carbon $startDate Period start
     * @param \Carbon\Carbon $endDate Period end
     * @param MarketplaceOrganizer|null $organizer Optional specific organizer
     * @return array Summary data
     */
    public function getPeriodSummary(
        Tenant $tenant,
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate,
        ?MarketplaceOrganizer $organizer = null
    ): array {
        $query = Order::where('tenant_id', $tenant->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($organizer) {
            $query->where('organizer_id', $organizer->id);
        } else {
            $query->whereNotNull('organizer_id');
        }

        $orders = $query->get();

        $summary = $this->calculateTotalsForOrders($orders);
        $summary['period_start'] = $startDate->toDateString();
        $summary['period_end'] = $endDate->toDateString();

        return $summary;
    }
}
