<?php

namespace App\Services\Marketplace;

use App\Models\Order;
use App\Models\Tenant;

/**
 * Money a venue owner collected at the door (POS) for an event and must hand
 * over to the event's organizer. Venue-owner POS orders carry
 * source='venue_owner_pos' and meta.venue_owner_tenant_id; the cash / card
 * split comes from meta.payment_method.
 *
 * Test POS carts are stored as 'pos_test' and never count in the amounts to
 * hand over. With $withTest they are reported separately (test_tickets /
 * test_total) so the venue can see its test sale went through.
 *
 * Amounts are the price of the valid / used tickets (same basis as the
 * mobile sales breakdown), so cancelled / refunded tickets drop out.
 */
class VenueOwnerSettlementService
{
    /**
     * @return array<int, array{tenant_id:int, venue_name:string, orders:int, tickets:int, cash:float, card:float, total:float, test_tickets:int, test_total:float}>
     */
    public function forEvent(int $eventId, int $marketplaceClientId, ?int $venueTenantId = null, bool $withTest = false): array
    {
        $rows = $this->forEvents([$eventId], $marketplaceClientId, $venueTenantId, $withTest)[$eventId] ?? [];

        // Organizer views list only venues that actually owe something.
        if (!$withTest) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['tickets'] > 0));
        }

        return $rows;
    }

    /**
     * Same as forEvent() for many events in one query.
     *
     * @param  iterable<int>  $eventIds
     * @return array<int, array<int, array>>  event id → list of venue rows
     */
    public function forEvents(iterable $eventIds, int $marketplaceClientId, ?int $venueTenantId = null, bool $withTest = false): array
    {
        $ids = collect($eventIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $sources = $withTest ? ['venue_owner_pos', 'pos_test'] : ['venue_owner_pos'];

        $orders = Order::with(['tickets' => fn ($q) => $q->whereIn('status', ['valid', 'used'])])
            ->whereIn('event_id', $ids)
            ->where('marketplace_client_id', $marketplaceClientId)
            ->whereIn('source', $sources)
            ->whereIn('status', ['confirmed', 'completed', 'paid'])
            ->get(['id', 'event_id', 'meta', 'status', 'source']);

        $byEvent = [];
        foreach ($orders as $order) {
            $meta = is_array($order->meta) ? $order->meta : [];
            $tenantId = (int) ($meta['venue_owner_tenant_id'] ?? 0);
            // pos_test orders from the organizer's own POS have no venue tag.
            if (!$tenantId || ($venueTenantId && $tenantId !== $venueTenantId)) {
                continue;
            }
            $count = $order->tickets->count();
            if ($count === 0) {
                continue;
            }
            $amount = (float) $order->tickets->sum('price');
            $eventId = (int) $order->event_id;

            if (!isset($byEvent[$eventId][$tenantId])) {
                $byEvent[$eventId][$tenantId] = [
                    'tenant_id' => $tenantId,
                    'venue_name' => '',
                    'orders' => 0,
                    'tickets' => 0,
                    'cash' => 0.0,
                    'card' => 0.0,
                    'total' => 0.0,
                    'test_tickets' => 0,
                    'test_total' => 0.0,
                ];
            }
            $row = &$byEvent[$eventId][$tenantId];
            if ($order->source === 'pos_test') {
                $row['test_tickets'] += $count;
                $row['test_total'] += $amount;
            } else {
                $method = ($meta['payment_method'] ?? 'cash') === 'cash' ? 'cash' : 'card';
                $row['orders']++;
                $row['tickets'] += $count;
                $row[$method] += $amount;
                $row['total'] += $amount;
            }
            unset($row);
        }

        if (!$byEvent) {
            return [];
        }

        $tenantIds = collect($byEvent)->flatMap(fn ($rows) => array_keys($rows))->unique()->values();
        $names = Tenant::whereIn('id', $tenantIds)->get(['id', 'public_name', 'company_name', 'name'])
            ->mapWithKeys(fn ($t) => [$t->id => $t->public_name ?: ($t->company_name ?: ($t->name ?: 'Locație'))]);

        $result = [];
        foreach ($byEvent as $eventId => $rows) {
            foreach ($rows as $tenantId => $row) {
                $row['venue_name'] = (string) ($names[$tenantId] ?? 'Locație');
                foreach (['cash', 'card', 'total', 'test_total'] as $k) {
                    $row[$k] = round($row[$k], 2);
                }
                $result[$eventId][] = $row;
            }
        }

        return $result;
    }
}
