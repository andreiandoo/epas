<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTaxRegistry;
use App\Models\Order;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leisure venue endpoints (F1 — minim viabil).
 *
 * Activate doar pentru evenimente cu display_template === 'leisure_venue'.
 * Toate verificarile filtreaza pe marketplace_client_id al organizatorului autenticat.
 */
class LeisureController extends BaseController
{
    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/config
     *
     * Returneaza configurarea leisure: ticket types cu societatea emitenta efectiva
     * (issuing_tax_registry_id sau fallback la event.marketplace_tax_registry_id) si
     * lista societatilor emitente disponibile pentru marketplace-ul curent.
     */
    public function config(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        if (($eventModel->display_template ?? 'standard') !== 'leisure_venue') {
            return $this->error('Event is not a leisure venue', 422);
        }

        $registries = MarketplaceTaxRegistry::query()
            ->where('marketplace_client_id', $marketplace->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'subname', 'cif', 'iban', 'invoice_series']);

        $ticketTypes = TicketType::query()
            ->where('event_id', $eventModel->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('issuingTaxRegistry:id,name,subname,cif,invoice_series')
            ->get();

        $eventDefaultRegistryId = $eventModel->marketplace_tax_registry_id;

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $this->localizedTitle($eventModel),
                'display_template' => $eventModel->display_template,
                'default_tax_registry_id' => $eventDefaultRegistryId,
            ],
            'tax_registries' => $registries->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'subname' => $r->subname,
                'cif' => $r->cif,
                'iban' => $r->iban,
                'invoice_series' => $r->invoice_series,
            ])->all(),
            'ticket_types' => $ticketTypes->map(function (TicketType $tt) use ($eventDefaultRegistryId) {
                $issuingId = $tt->issuing_tax_registry_id ?: $eventDefaultRegistryId;
                $issuing = $tt->issuingTaxRegistry;

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'sku' => $tt->sku,
                    'service_category' => $tt->effective_service_category,
                    'is_parking' => (bool) $tt->is_parking,
                    'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                    'daily_capacity' => $tt->daily_capacity,
                    'ticket_group' => $tt->ticket_group,
                    'issuing_tax_registry_id' => $issuingId,
                    'issuing_explicit' => (bool) $tt->issuing_tax_registry_id,
                    'issuing_tax_registry' => $issuing ? [
                        'id' => $issuing->id,
                        'name' => $issuing->name,
                        'subname' => $issuing->subname,
                        'cif' => $issuing->cif,
                    ] : null,
                ];
            })->all(),
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/reports/by-registry?from=&to=
     *
     * Break-down vanzari pe societate emitenta pentru perioada specificata.
     * Foloseste Order completed/paid + tickets. Tickets fara issuing_tax_registry_id
     * cad pe registry-ul implicit al evenimentului (sau "unassigned" cand acela e null).
     */
    public function reportsByRegistry(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : Carbon::today()->subDays(30)->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::today()->endOfDay();

        $eventDefaultRegistryId = $eventModel->marketplace_tax_registry_id;

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->with(['tickets:id,order_id,ticket_type_id,price,status', 'tickets.ticketType:id,name,issuing_tax_registry_id,service_category'])
            ->get(['id', 'event_id', 'paid_at', 'status', 'currency']);

        $registries = MarketplaceTaxRegistry::query()
            ->where('marketplace_client_id', $marketplace->id)
            ->get(['id', 'name', 'subname', 'cif', 'invoice_series'])
            ->keyBy('id');

        $buckets = [];

        foreach ($orders as $order) {
            foreach ($order->tickets as $ticket) {
                if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
                    continue;
                }

                $tt = $ticket->ticketType;
                $registryId = $tt?->issuing_tax_registry_id ?: $eventDefaultRegistryId;
                $key = $registryId ?: 'unassigned';

                if (!isset($buckets[$key])) {
                    $registry = $registryId ? ($registries->get($registryId)) : null;
                    $buckets[$key] = [
                        'registry' => $registry ? [
                            'id' => $registry->id,
                            'name' => $registry->name,
                            'subname' => $registry->subname,
                            'cif' => $registry->cif,
                            'invoice_series' => $registry->invoice_series,
                        ] : null,
                        'orders' => [],
                        'tickets_count' => 0,
                        'subtotal' => 0.0,
                        'by_category' => [],
                    ];
                }

                $bucket = &$buckets[$key];
                $bucket['orders'][$order->id] = true;
                $bucket['tickets_count']++;
                $bucket['subtotal'] += (float) ($ticket->price ?? 0);

                $cat = $tt?->effective_service_category ?? 'access';
                if (!isset($bucket['by_category'][$cat])) {
                    $bucket['by_category'][$cat] = ['count' => 0, 'subtotal' => 0.0];
                }
                $bucket['by_category'][$cat]['count']++;
                $bucket['by_category'][$cat]['subtotal'] += (float) ($ticket->price ?? 0);
                unset($bucket);
            }
        }

        $rows = array_map(function ($key, $b) {
            return [
                'registry' => $b['registry'],
                'orders_count' => count($b['orders']),
                'tickets_count' => $b['tickets_count'],
                'subtotal' => round($b['subtotal'], 2),
                'by_category' => $b['by_category'],
            ];
        }, array_keys($buckets), array_values($buckets));

        return $this->success([
            'event_id' => $eventModel->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $orders->first()?->currency ?? 'RON',
            'rows' => $rows,
        ]);
    }

    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }

    protected function localizedTitle(Event $event): string
    {
        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? (reset($title) ?: '');
        }
        return (string) ($title ?? '');
    }
}
