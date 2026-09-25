<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\Tenant;

/**
 * Money a venue owner collected at the door (POS) for an event and must hand
 * over to the event's organizer. Venue-owner POS orders carry
 * source='venue_owner_pos' and meta.venue_owner_tenant_id; the cash / card
 * split comes from meta.payment_method. Test POS carts are stored as
 * 'pos_test' and never count here.
 *
 * Amounts are the price of the valid / used tickets (same basis as the
 * mobile sales breakdown), so cancelled / refunded tickets drop out.
 */
class VenueOwnerSettlementService
{
    /**
     * @return array<int, array{tenant_id:int, venue_name:string, orders:int, tickets:int, cash:float, card:float, total:float}>
     */
    public function forEvent(int $eventId, int $marketplaceClientId, ?int $venueTenantId = null): array
    {
        $orders = Order::with(['tickets' => fn ($q) => $q->whereIn('status', ['valid', 'used'])])
            ->where('event_id', $eventId)
            ->where('marketplace_client_id', $marketplaceClientId)
            ->where('source', 'venue_owner_pos')
            ->whereIn('status', ['confirmed', 'completed', 'paid'])
            ->get(['id', 'meta', 'status', 'source']);

        $rows = [];
        foreach ($orders as $order) {
            $meta = is_array($order->meta) ? $order->meta : [];
            $tenantId = (int) ($meta['venue_owner_tenant_id'] ?? 0);
            if (!$tenantId || ($venueTenantId && $tenantId !== $venueTenantId)) {
                continue;
            }
            $amount = (float) $order->tickets->sum('price');
            $count = $order->tickets->count();
            if ($count === 0) {
                continue;
            }

            if (!isset($rows[$tenantId])) {
                $rows[$tenantId] = [
                    'tenant_id' => $tenantId,
                    'venue_name' => '',
                    'orders' => 0,
                    'tickets' => 0,
                    'cash' => 0.0,
                    'card' => 0.0,
                    'total' => 0.0,
                ];
            }
            $method = ($meta['payment_method'] ?? 'cash') === 'cash' ? 'cash' : 'card';
            $rows[$tenantId]['orders']++;
            $rows[$tenantId]['tickets'] += $count;
            $rows[$tenantId][$method] += $amount;
            $rows[$tenantId]['total'] += $amount;
        }

        if ($rows) {
            $names = Tenant::whereIn('id', array_keys($rows))->get(['id', 'public_name', 'company_name', 'name'])
                ->mapWithKeys(fn ($t) => [$t->id => $t->public_name ?: ($t->company_name ?: ($t->name ?: 'Locație'))]);
            foreach ($rows as $id => &$row) {
                $row['venue_name'] = (string) ($names[$id] ?? 'Locație');
                $row['cash'] = round($row['cash'], 2);
                $row['card'] = round($row['card'], 2);
                $row['total'] = round($row['total'], 2);
            }
            unset($row);
        }

        return array_values($rows);
    }
}
