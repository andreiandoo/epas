<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leisure venue endpoints (F1 — minim viabil).
 *
 * Activate doar pentru evenimente cu display_template === 'leisure_venue'.
 * Filtreaza pe marketplace_client_id al organizatorului autenticat.
 *
 * Modelul fiscal: organizatorul poate avea 2 societati emitente
 * (primary = company_*, secondary = secondary_company_*). Fiecare TicketType
 * are issuing_company ('primary'|'secondary'|NULL=primary).
 */
class LeisureController extends BaseController
{
    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/config
     *
     * Returneaza configurarea leisure: ticket types cu societatea emitenta efectiva
     * + datele celor 2 societati ale organizatorului (primary intotdeauna,
     * secondary doar daca has_secondary_issuer=true).
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

        // Organizatorul evenimentului (in cazul multi-organizer marketplace,
        // poate diferi de organizer-ul autentificat — folosim cel al evenimentului
        // pentru datele juridice).
        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;

        $ticketTypes = TicketType::query()
            ->where('event_id', $eventModel->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $issuers = [
            'primary' => $eventOrganizer?->getIssuerData('primary') ?? [],
        ];
        if ($eventOrganizer?->has_secondary_issuer) {
            $issuers['secondary'] = $eventOrganizer->getIssuerData('secondary');
        }

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $this->localizedTitle($eventModel),
                'display_template' => $eventModel->display_template,
            ],
            'organizer' => $eventOrganizer ? [
                'id' => $eventOrganizer->id,
                'name' => $eventOrganizer->name,
                'has_secondary_issuer' => (bool) $eventOrganizer->has_secondary_issuer,
            ] : null,
            'issuers' => $issuers,
            'ticket_types' => $ticketTypes->map(function (TicketType $tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'sku' => $tt->sku,
                    'service_category' => $tt->effective_service_category,
                    'is_parking' => (bool) $tt->is_parking,
                    'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                    'daily_capacity' => $tt->daily_capacity,
                    'ticket_group' => $tt->ticket_group,
                    'issuing_company' => $tt->effective_issuing_company,
                    'issuing_explicit' => (bool) $tt->issuing_company,
                ];
            })->all(),
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/reports/by-issuer?from=&to=
     *
     * Break-down vanzari pe societate emitenta a organizatorului (primary | secondary)
     * pentru perioada specificata.
     */
    public function reportsByIssuer(Request $request, int $event): JsonResponse
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

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->subDays(30)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::today()->endOfDay();

        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->with([
                'tickets:id,order_id,ticket_type_id,price,status',
                'tickets.ticketType:id,name,issuing_company,service_category',
            ])
            ->get(['id', 'event_id', 'paid_at', 'status', 'currency']);

        $buckets = [
            'primary' => $this->emptyBucket(),
            'secondary' => $this->emptyBucket(),
        ];

        foreach ($orders as $order) {
            foreach ($order->tickets as $ticket) {
                if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
                    continue;
                }

                $tt = $ticket->ticketType;
                $company = $tt?->effective_issuing_company ?: 'primary';
                if (!isset($buckets[$company])) {
                    $buckets[$company] = $this->emptyBucket();
                }

                $bucket = &$buckets[$company];
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

        $rows = [];
        foreach ($buckets as $company => $b) {
            // Skip secondary daca nu are date si nu e activat pe organizer
            if ($company === 'secondary'
                && $b['tickets_count'] === 0
                && !$eventOrganizer?->has_secondary_issuer) {
                continue;
            }

            $rows[] = [
                'company' => $company,
                'issuer' => $eventOrganizer?->getIssuerData($company),
                'orders_count' => count($b['orders']),
                'tickets_count' => $b['tickets_count'],
                'subtotal' => round($b['subtotal'], 2),
                'by_category' => $b['by_category'],
            ];
        }

        return $this->success([
            'event_id' => $eventModel->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $orders->first()?->currency ?? 'RON',
            'rows' => $rows,
        ]);
    }

    protected function emptyBucket(): array
    {
        return [
            'orders' => [],
            'tickets_count' => 0,
            'subtotal' => 0.0,
            'by_category' => [],
        ];
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
