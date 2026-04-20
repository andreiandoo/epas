<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EventsController extends BaseController
{
    /**
     * List core events hosted at partner venues of this venue-owner's tenant.
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

        $query = Event::query()
            ->where('marketplace_client_id', $client->id)
            ->whereHas('venue', function ($q) use ($tenant, $client) {
                $q->where('tenant_id', $tenant->id)
                  ->partnerOfMarketplace($client->id);
            })
            ->with([
                'venue:id,name,city,address',
                'marketplaceOrganizer:id,name,logo,slug',
                'tenant:id,name,public_name',
                'artists:id,name,slug',
            ]);

        if ($scope === 'upcoming') {
            $query->upcoming();
        } elseif ($scope === 'past') {
            $query->past();
        }

        // Sort: upcoming by earliest first, past by most recent first
        $events = $query->get();

        // Sort in-memory using effective start date (handles all duration modes cleanly)
        $events = $scope === 'past'
            ? $events->sortByDesc(fn ($e) => optional($e->start_date)->timestamp)->values()
            : $events->sortBy(fn ($e) => optional($e->start_date)->timestamp)->values();

        $stats = $this->aggregateStats($events->pluck('id'));

        return $this->success([
            'events' => $events->map(fn ($e) => $this->formatEvent($e, $stats))->values()->toArray(),
        ]);
    }

    /**
     * Event details + live stats + per-ticket-type breakdown.
     */
    public function show(Request $request, int $event): JsonResponse
    {
        $resolved = $request->attributes->get('venue_owner_event');

        $eventModel = $resolved instanceof Event
            ? $resolved
            : Event::find($event);

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $eventModel->load([
            'venue:id,name,city,address',
            'marketplaceOrganizer:id,name,logo,slug',
            'tenant:id,name,public_name',
            'artists:id,name,slug',
            'ticketTypes' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        $stats = $this->aggregateStats(collect([$eventModel->id]));
        $data = $this->formatEvent($eventModel, $stats, includeTickets: true);

        // Per-ticket-type breakdown
        $ticketTypeStats = Ticket::where('event_id', $eventModel->id)
            ->where('is_cancelled', false)
            ->selectRaw('ticket_type_id, count(*) as sold, count(checked_in_at) as checked_in')
            ->groupBy('ticket_type_id')
            ->get()
            ->keyBy('ticket_type_id');

        $data['ticket_types'] = collect($data['ticket_types'] ?? [])->map(function ($tt) use ($ticketTypeStats) {
            $row = $ticketTypeStats->get((int) $tt['id']);
            $tt['sold'] = $row ? (int) $row->sold : 0;
            $tt['checked_in'] = $row ? (int) $row->checked_in : 0;
            return $tt;
        })->values()->toArray();

        return $this->success(['event' => $data]);
    }

    /**
     * Aggregate live stats keyed by event id.
     */
    protected function aggregateStats(Collection $eventIds): Collection
    {
        if ($eventIds->isEmpty()) {
            return collect();
        }

        $ticketStats = Ticket::whereIn('event_id', $eventIds)
            ->where('is_cancelled', false)
            ->selectRaw('event_id, count(*) as tickets_sold, count(checked_in_at) as checked_in_count')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

        // quota_total = -1 means unlimited — exclude from stock_total
        $stockStats = TicketType::whereIn('event_id', $eventIds)
            ->selectRaw('event_id, SUM(CASE WHEN quota_total >= 0 THEN quota_total ELSE 0 END) as stock_total')
            ->groupBy('event_id')
            ->get()
            ->keyBy('event_id');

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

    protected function formatEvent(Event $event, Collection $stats, bool $includeTickets = false): array
    {
        $s = $stats->get($event->id) ?: (object) [
            'tickets_sold' => 0, 'checked_in_count' => 0, 'stock_total' => 0,
        ];

        $startDate = $event->start_date; // accessor (handles duration modes)
        $endDate = $event->end_date;

        $data = [
            'id' => (string) $event->id,
            'title' => $event->getTranslation('title'),
            'slug' => $event->slug,
            'poster_url' => $event->poster_url,
            'featured_image' => $event->featured_image,
            'duration_mode' => $event->duration_mode,
            'start_date' => $startDate ? $startDate->toDateString() : null,
            'start_time' => $event->start_time,
            'end_date' => $endDate ? $endDate->toDateString() : null,
            'end_time' => $event->end_time,
            'door_time' => $event->door_time,
            'is_cancelled' => (bool) $event->is_cancelled,
            'is_postponed' => (bool) $event->is_postponed,
            'is_published' => (bool) $event->is_published,
            'venue' => $event->venue ? [
                'id' => (string) $event->venue->id,
                'name' => $event->venue->getTranslation('name'),
                'city' => $event->venue->city ?? null,
                'address' => $event->venue->address ?? null,
            ] : null,
            'tenant' => $event->tenant ? [
                'id' => (string) $event->tenant->id,
                'name' => $event->tenant->name,
                'public_name' => $event->tenant->public_name ?? $event->tenant->name,
            ] : null,
            'marketplace_organizer' => $event->marketplaceOrganizer ? [
                'id' => (string) $event->marketplaceOrganizer->id,
                'name' => $event->marketplaceOrganizer->name,
                'logo' => $event->marketplaceOrganizer->logo ?? null,
                'slug' => $event->marketplaceOrganizer->slug ?? null,
            ] : null,
            'artists' => $event->relationLoaded('artists') ? $event->artists->map(fn ($a) => [
                'id' => (string) $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'is_headliner' => (bool) ($a->pivot->is_headliner ?? false),
            ])->values()->toArray() : [],
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
                'quota_total' => $tt->quota_total !== null ? (int) $tt->quota_total : null,
                'quota_sold' => $tt->quota_sold !== null ? (int) $tt->quota_sold : 0,
                'unlimited' => $tt->quota_total !== null && (int) $tt->quota_total < 0,
            ])->values()->toArray();
        }

        return $data;
    }
}
