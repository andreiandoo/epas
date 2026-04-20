<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueStaff;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceEvent;
use App\Models\VenueStaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends BaseController
{
    /**
     * List marketplace events hosted at venues of the current staff's tenant.
     * Defaults to upcoming (ends_at in the future or null). Past events can be
     * requested with ?include_past=1.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $staff = $request->user();

        if (!$staff instanceof VenueStaffMember) {
            return $this->error('Unauthorized', 401);
        }

        $includePast = filter_var($request->query('include_past'), FILTER_VALIDATE_BOOLEAN);

        $query = MarketplaceEvent::query()
            ->where('marketplace_client_id', $client->id)
            ->whereHas('venue', fn ($q) => $q->where('tenant_id', $staff->tenant_id))
            ->with([
                'organizer:id,name,logo,slug',
                'venue:id,name,city,address',
            ]);

        if (!$includePast) {
            $query->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', now());
            });
        }

        $events = $query->orderBy('starts_at', 'asc')->get();

        return $this->success([
            'events' => $events->map(fn ($e) => $this->formatEvent($e))->values()->toArray(),
        ]);
    }

    /**
     * Return details of a single event (middleware has already authorized).
     */
    public function show(Request $request, int $event): JsonResponse
    {
        $resolved = $request->attributes->get('venue_staff_event');

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

        return $this->success([
            'event' => $this->formatEvent($eventModel, includeTickets: true),
        ]);
    }

    protected function formatEvent(MarketplaceEvent $event, bool $includeTickets = false): array
    {
        $data = [
            'id' => (string) $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'image' => $event->image,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'doors_open_at' => $event->doors_open_at?->toIso8601String(),
            'status' => $event->status,
            'is_public' => (bool) $event->is_public,
            'venue' => $event->venue ? [
                'id' => (string) $event->venue->id,
                'name' => $event->venue->name,
                'city' => $event->venue->city ?? null,
                'address' => $event->venue->address ?? null,
            ] : null,
            'organizer' => $event->organizer ? [
                'id' => (string) $event->organizer->id,
                'name' => $event->organizer->name,
                'logo' => $event->organizer->logo ?? null,
                'slug' => $event->organizer->slug ?? null,
            ] : null,
        ];

        if ($includeTickets) {
            $data['ticket_types'] = $event->ticketTypes->map(fn ($tt) => [
                'id' => (string) $tt->id,
                'name' => $tt->name,
                'price' => (float) ($tt->price ?? 0),
                'currency' => $tt->currency ?? 'RON',
                'description' => $tt->description ?? null,
                'status' => $tt->status,
            ])->values()->toArray();
        }

        return $data;
    }
}
