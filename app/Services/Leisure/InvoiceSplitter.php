<?php

namespace App\Services\Leisure;

use App\Models\Leisure\TenantTaxRegistry;
use App\Models\Order;
use App\Models\Tenant;

/**
 * Groups order line items by their TicketType.tenant_tax_registry_id and
 * returns the buckets needed to emit one fiscal invoice per issuer.
 *
 * This service does NOT touch the actual Invoice model — it only computes
 * the split. The integration with InvoiceGeneratorService / eFactura is
 * left to the call-site so the existing fiscal pipeline stays in charge of
 * series numbering, ANAF submission, etc.
 */
class InvoiceSplitter
{
    /**
     * Returns an array of buckets:
     *   [
     *     [
     *       'registry' => TenantTaxRegistry|null,   // null = no registry (fallback to tenant.company_name)
     *       'items'    => [\App\Models\OrderItem|object, ...],
     *       'subtotal_cents' => int,
     *       'total_cents'    => int,
     *     ],
     *     ...
     *   ]
     */
    public function split(Order $order): array
    {
        $tenantId = $order->tenant_id;
        $registries = TenantTaxRegistry::where('tenant_id', $tenantId)
            ->get()
            ->keyBy('id');

        $defaultRegistry = $registries->firstWhere('is_default', true);

        $buckets = [];

        // OrderItem relationships vary across this codebase — some have
        // ticket_type relation, others store ticket_type_id directly. We
        // resolve defensively.
        $items = method_exists($order, 'items') ? $order->items : ($order->orderItems ?? collect());
        if (is_object($items) && method_exists($items, 'all') === false) {
            // Likely an Eloquent relationship — load it.
            $items = $order->items()->with('ticketType')->get();
        }

        foreach ($items as $item) {
            $ticketType = $item->ticketType ?? null;
            $registryId = $ticketType?->tenant_tax_registry_id;

            $registry = $registryId ? ($registries[$registryId] ?? $defaultRegistry) : $defaultRegistry;
            $key = $registry?->id ?? 0; // 0 = unassigned / fallback to tenant

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'registry' => $registry,
                    'items' => [],
                    'subtotal_cents' => 0,
                    'total_cents' => 0,
                ];
            }

            $buckets[$key]['items'][] = $item;
            $buckets[$key]['subtotal_cents'] += (int) ($item->subtotal_cents ?? $item->price_cents ?? 0);
            $buckets[$key]['total_cents'] += (int) ($item->total_cents ?? $item->subtotal_cents ?? $item->price_cents ?? 0);
        }

        return array_values($buckets);
    }

    /**
     * True if the order needs more than one invoice (items from multiple
     * tax registries).
     */
    public function isSplitRequired(Order $order): bool
    {
        return count($this->split($order)) > 1;
    }
}
