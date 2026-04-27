<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public tour pages on the marketplace storefront (e.g. ambilet.ro/turnee/{slug}).
 * Returns the tour with its rich content + a list of published linked events.
 */
class ToursController extends Controller
{
    use ResolvesTenant;

    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveRequestTenant($request);
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $locale = $request->query('locale', 'ro');

        // Match tours by either:
        //   - tour.tenant_id = current tenant (direct ownership)
        //   - any event in this tour belongs to the current tenant
        // The second branch covers legacy tours created via EventResource
        // before tenant_id was being set on the tour itself.
        $tour = Tour::query()
            ->where('slug', $slug)
            ->where(function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id)
                    ->orWhereHas('events', fn ($eq) => $eq->where('tenant_id', $tenant->id));
            })
            ->with([
                'artist:id,name,slug,main_image_url',
                'marketplaceOrganizer:id,name,logo,description',
            ])
            ->first();

        if (!$tour) {
            return response()->json(['success' => false, 'message' => 'Tour not found'], 404);
        }

        $events = $tour->events()
            ->where('is_published', true)
            ->with(['venue:id,name,city,slug', 'ticketTypes:id,event_id,name,price_cents,quota_total,quota_sold,meta'])
            ->orderBy('event_date')
            ->get();

        $period = $tour->period;

        $resolveTrans = function ($value) use ($locale) {
            if (is_array($value)) {
                return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? reset($value) ?: null;
            }
            return $value ?: null;
        };

        $eventTitle = function ($event) use ($locale, $resolveTrans) {
            return $resolveTrans($event->title);
        };

        $venueName = function ($venue) use ($resolveTrans) {
            if (!$venue) return null;
            return $resolveTrans($venue->name);
        };

        $storageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
            return Storage::disk('public')->url($path);
        };

        // Build distinct artists/cities lists (already exposed by Tour accessors)
        $cities = $tour->cities;
        $artists = $tour->distinct_artists->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'slug' => $a->slug,
            'image' => $storageUrl($a->main_image_url),
        ])->values();

        $eventsPayload = $events->map(function ($e) use ($eventTitle, $venueName, $storageUrl) {
            // Skip the "Invitatie" ticket types from the public preview
            $ticketTypes = $e->ticketTypes
                ->reject(fn ($tt) => (bool) (data_get($tt->meta, 'is_invitation') === true))
                ->map(fn ($tt) => [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => $tt->price_cents ? round($tt->price_cents / 100, 2) : 0,
                    'quota_total' => (int) ($tt->quota_total ?? 0),
                    'quota_sold' => (int) ($tt->quota_sold ?? 0),
                ])
                ->values();

            return [
                'id' => $e->id,
                'name' => $eventTitle($e),
                'slug' => $e->slug,
                'event_date' => $e->event_date?->toIso8601String(),
                'starts_at' => $e->starts_at?->toIso8601String() ?? $e->event_date?->toIso8601String(),
                'image' => $storageUrl($e->hero_image_url),
                'venue' => $e->venue ? [
                    'id' => $e->venue->id,
                    'name' => $venueName($e->venue),
                    'city' => $e->venue->city,
                    'slug' => $e->venue->slug,
                ] : null,
                'ticket_types' => $ticketTypes,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'tour' => [
                    'id' => $tour->id,
                    'name' => $tour->name,
                    'slug' => $tour->slug,
                    'type' => $tour->type,
                    'status' => $tour->status,
                    'cover_url' => $storageUrl($tour->cover_url),
                    'poster_url' => $storageUrl($tour->poster_url),
                    'short_description' => $resolveTrans($tour->short_description),
                    'description' => $resolveTrans($tour->description),
                    'setlist' => $tour->setlist ?? [],
                    'setlist_duration_minutes' => $tour->setlist_duration_minutes,
                    'faq' => $tour->faq ?? [],
                    'age_min' => $tour->age_min,
                    'period' => [
                        'start' => $period['start']?->toIso8601String(),
                        'end' => $period['end']?->toIso8601String(),
                    ],
                ],
                'artist' => $tour->artist ? [
                    'id' => $tour->artist->id,
                    'name' => $tour->artist->name,
                    'slug' => $tour->artist->slug,
                    'image' => $storageUrl($tour->artist->main_image_url),
                ] : null,
                'organizer' => $tour->marketplaceOrganizer ? [
                    'id' => $tour->marketplaceOrganizer->id,
                    'name' => $tour->marketplaceOrganizer->name,
                    'logo' => $storageUrl($tour->marketplaceOrganizer->logo),
                ] : null,
                'aggregates' => [
                    'total_events' => $events->count(),
                    'total_capacity' => $tour->total_capacity,
                    'total_sold' => $tour->total_sold,
                    'cities' => $cities->values(),
                    'artists' => $artists,
                ],
                'events' => $eventsPayload,
            ],
        ]);
    }
}
