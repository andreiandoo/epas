<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public marketplace API for tour landing pages (e.g. ambilet.ro/turnee/{slug}).
 * Scoped by the marketplace client resolved from the API key in marketplace.auth.
 */
class ToursController extends BaseController
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $tour = Tour::query()
            ->where('slug', $slug)
            ->where('marketplace_client_id', $client->id)
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
            ->where('marketplace_client_id', $client->id)
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

        $eventTitle = fn ($event) => $resolveTrans($event->title);
        $venueName = fn ($venue) => $venue ? $resolveTrans($venue->name) : null;

        $storageUrl = function ($path) {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
            return Storage::disk('public')->url($path);
        };

        $cities = $tour->cities;
        $artists = $tour->distinct_artists->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'slug' => $a->slug,
            'image' => $storageUrl($a->main_image_url),
        ])->values();

        $eventsPayload = $events->map(function ($e) use ($eventTitle, $venueName, $storageUrl) {
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

            // Available capacity = the same number admin sees in the event form's
            // "Capacitate generală" badge: general_quota - non-independent quota_sold.
            // Falls back to total_capacity when general_quota isn't set; -1 means unlimited.
            $availableCapacity = $e->shared_pool_remaining;
            if ($availableCapacity === null) {
                $availableCapacity = $e->total_capacity;
            }

            // Build a clean ISO timestamp from event_date + start_time (both are
            // separate columns on events). $e->starts_at is auto-cast to UTC
            // midnight when start_time is unset, which produces 03:00 EET on the
            // frontend for every event — not what we want.
            $startsAt = null;
            if ($e->event_date) {
                $time = $e->start_time ?: '00:00:00';
                if (substr_count($time, ':') === 1) $time .= ':00';
                $startsAt = $e->event_date->format('Y-m-d') . 'T' . $time;
            }

            return [
                'id' => $e->id,
                'name' => $eventTitle($e),
                'slug' => $e->slug,
                'event_date' => $e->event_date?->format('Y-m-d'),
                'start_time' => $e->start_time,
                'starts_at' => $startsAt,
                'image' => $storageUrl($e->hero_image_url),
                'general_quota' => $e->general_quota,
                'available_capacity' => $availableCapacity,
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
