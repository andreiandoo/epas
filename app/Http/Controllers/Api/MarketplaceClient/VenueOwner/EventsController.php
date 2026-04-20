<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceTicketType;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventsController extends BaseController
{
    /**
     * List marketplace events hosted at partner venues of this venue-owner's tenant.
     * ?scope=upcoming|past|all (default: all). Includes live stats per event.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $tenant = $request->attributes->get('venue_owner_tenant');

        if (!$tenant instanceof Tenant) {
            return $this->error('Venue owner tenant not resolved', 500);
        }

        $scope = $request->query('scope', 'all');

        $query = MarketplaceEvent::query()
            ->where('marketplace_client_id', $client->id)
            ->whereHas('venue', function ($q) use ($tenant, $client) {
                $q->where('tenant_id', $tenant->id)
                  ->partnerOfMarketplace($client->id);
            })
            ->with([
                'organizer:id,name,logo,slug',
                'venue:id,name,city,address',
            ]);

        if ($scope === 'upcoming') {
            $query->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', now());
            })->orderBy('starts_at', 'asc');
        } elseif ($scope === 'past') {
            $query->whereNotNull('ends_at')
                  ->where('ends_at', '<', now())
                  ->orderBy('starts_at', 'desc');
        } else {
            $query->orderBy('starts_at', 'desc');
        }

        $events = $query->get();
        $stats = $this->aggregateStats($events->pluck('id'));

        return $this->success([
            'events' => $events->map(fn ($e) => $this->formatEvent($e, $stats))->values()->toArray(),
        ]);
    }

    /**
     * Event details + live stats.
     */
    public function show(Request $request, int $event): JsonResponse
    {
        $resolved = $request->attributes->get('venue_owner_event');

        $eventModel = $resolved instanceof MarketplaceEvent
            ? $resolved
            : MarketplaceEvent::find($event);

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $eventModel->load([
            'organizer:id,name,logo,slug',
            'venue:id,name,city,address',
            'ticketTypes' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        $stats = $this->aggregateStats(collect([$eventModel->id]));

        $data = $this->formatEvent($eventModel, $stats, includeTickets: true);

        // Per-ticket-type breakdown for live stock view
        $ticketTypeStats = Ticket::where('marketplace_event_id', $eventModel->id)
            ->where('is_cancelled', false)
            ->selectRaw('marketplace_ticket_type_id, count(*) as sold, count(checked_in_at) as checked_in')
            ->groupBy('marketplace_ticket_type_id')
            ->get()
            ->keyBy('marketplace_ticket_type_id');

        $data['ticket_types'] = collect($data['ticket_types'] ?? [])->map(function ($tt) use ($ticketTypeStats) {
            $row = $ticketTypeStats->get((int) $tt['id']);
            $tt['sold'] = $row ? (int) $row->sold : 0;
            $tt['checked_in'] = $row ? (int) $row->checked_in : 0;
            return $tt;
        })->values()->toArray();

        return $this->success(['event' => $data]);
    }

    /**
     * Aggregate live stats (sold, checked-in, stock) for the given event ids.
     * Returns a collection keyed by event id with stdClass objects.
     */
    protected function aggregateStats(Collection $eventIds): Collection
    {
        if ($eventIds->isEmpty()) {
            return collect();
        }

        $ticketStats = Ticket::whereIn('marketplace_event_id', $eventIds)
            ->where('is_cancelled', false)
            ->selectRaw('marketplace_event_id, count(*) as tickets_sold, count(checked_in_at) as checked_in_count')
            ->groupBy('marketplace_event_id')
            ->get()
            ->keyBy('marketplace_event_id');

        $stockStats = MarketplaceTicketType::whereIn('marketplace_event_id', $eventIds)
            ->selectRaw('marketplace_event_id, SUM(COALESCE(quantity, 0)) as stock_total')
            ->groupBy('marketplace_event_id')
            ->get()
            ->keyBy('marketplace_event_id');

        return $eventIds->mapWithKeys(function ($id) use ($ticketStats, $stockStats) {
            $t = $ticketStats->get($id);
            $s = $stockStats->get($id);
            return [$id => (object) [
                'tickets_sold' => $t ? (int) $t->tickets_sold : 0,
                'checked_in_count' => $t ? (int) $t->checked_in_count : 0,
                'stock_total' => $s ? (int) $s->stock_total : 0,
            ]];
        });
    }

    protected function formatEvent(MarketplaceEvent $event, Collection $stats, bool $includeTickets = false): array
    {
        $s = $stats->get($event->id) ?: (object) [
            'tickets_sold' => 0, 'checked_in_count' => 0, 'stock_total' => 0,
        ];

        $data = [
            'id' => (string) $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'image' => $event->image,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'doors_open_at' => $event->doors_open_at?->toIso8601String(),
            'status' => $event->status,
            'venue' => $event->venue ? [
                'id' => (string) $event->venue->id,
                'name' => $event->venue->getTranslation('name'),
                'city' => $event->venue->city ?? null,
                'address' => $event->venue->address ?? null,
            ] : null,
            'organizer' => $event->organizer ? [
                'id' => (string) $event->organizer->id,
                'name' => $event->organizer->name,
                'logo' => $event->organizer->logo ?? null,
                'slug' => $event->organizer->slug ?? null,
            ] : null,
            'stats' => [
                'tickets_sold' => $s->tickets_sold,
                'checked_in_count' => $s->checked_in_count,
                'stock_total' => $s->stock_total,
            ],
        ];

        if ($includeTickets && $event->relationLoaded('ticketTypes')) {
            $data['ticket_types'] = $event->ticketTypes->map(fn ($tt) => [
                'id' => (string) $tt->id,
                'name' => $tt->name,
                'price' => (float) ($tt->price ?? 0),
                'currency' => $tt->currency ?? 'RON',
                'description' => $tt->description ?? null,
                'status' => $tt->status,
                'quantity' => $tt->quantity !== null ? (int) $tt->quantity : null,
            ])->values()->toArray();
        }

        return $data;
    }
}
