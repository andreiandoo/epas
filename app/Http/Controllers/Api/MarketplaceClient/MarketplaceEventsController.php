<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public API for browsing marketplace events (stored in events table)
 */
class MarketplaceEventsController extends BaseController
{
    /**
     * List all published marketplace events
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = Event::where('marketplace_client_id', $client->id)
            ->whereNull('is_cancelled')
            ->orWhere('is_cancelled', false)
            ->with([
                'marketplaceOrganizer:id,name,slug,logo,verified_at',
                'marketplaceEventCategory',
                'venue:id,name,city,address',
            ]);

        // Filter upcoming only by default
        if (!$request->has('include_past')) {
            $query->where('event_date', '>=', now()->toDateString());
        }

        // Filters
        if ($request->has('organizer_id')) {
            $query->where('marketplace_organizer_id', $request->organizer_id);
        }

        if ($request->has('organizer_slug')) {
            $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
                ->where('slug', $request->organizer_slug)
                ->first();
            if ($organizer) {
                $query->where('marketplace_organizer_id', $organizer->id);
            }
        }

        // Filter by category (slug or name)
        if ($request->has('category')) {
            $categorySlug = $request->category;
            $query->whereHas('marketplaceEventCategory', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug)
                    ->orWhere('name->ro', 'like', "%{$categorySlug}%")
                    ->orWhere('name->en', 'like', "%{$categorySlug}%");
            });
        }

        if ($request->has('city')) {
            $city = $request->city;
            $query->where(function ($q) use ($city) {
                $q->whereHas('marketplaceCity', function ($mq) use ($city) {
                    $mq->where('name->ro', 'like', "%{$city}%")
                        ->orWhere('name->en', 'like', "%{$city}%");
                })->orWhereHas('venue', function ($vq) use ($city) {
                    $vq->where('city', 'like', "%{$city}%");
                });
            });
        }

        if ($request->has('from_date')) {
            $query->where('event_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('event_date', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search, $language) {
                $q->where("title->{$language}", 'like', "%{$search}%")
                    ->orWhere("description->{$language}", 'like', "%{$search}%")
                    ->orWhereHas('venue', function ($vq) use ($search) {
                        $vq->where('name->ro', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    })
                    ->orWhereHas('marketplaceOrganizer', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Featured only
        if ($request->boolean('featured_only') || $request->boolean('featured')) {
            $query->where('is_homepage_featured', true)
                ->orWhere('is_general_featured', true);
        }

        // Sorting
        $sort = $request->get('sort', 'date_asc');
        switch ($sort) {
            case 'date_desc':
                $query->orderBy('event_date', 'desc')->orderBy('start_time', 'desc');
                break;
            case 'name_asc':
                $query->orderBy("title->{$language}", 'asc');
                break;
            case 'name_desc':
                $query->orderBy("title->{$language}", 'desc');
                break;
            case 'date_asc':
            default:
                $query->orderBy('event_date', 'asc')->orderBy('start_time', 'asc');
                break;
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events, function ($event) use ($language) {
            return $this->formatEvent($event, $language);
        });
    }

    /**
     * Get featured events
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $events = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where(function ($q) {
                $q->where('is_homepage_featured', true)
                    ->orWhere('is_general_featured', true);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->with(['marketplaceOrganizer:id,name,slug,logo', 'venue:id,name,city', 'marketplaceEventCategory'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(min((int) $request->get('limit', 10), 50))
            ->get()
            ->map(fn ($event) => $this->formatEvent($event, $language));

        return $this->success(['events' => $events]);
    }

    /**
     * Get single event details
     */
    public function show(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = Event::where('marketplace_client_id', $client->id);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->with([
            'marketplaceOrganizer',
            'venue',
            'marketplaceEventCategory',
            'ticketTypes',
        ])->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $venue = $event->venue;
        $organizer = $event->marketplaceOrganizer;

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('title', $language),
                'slug' => $event->slug,
                'description' => $event->getTranslation('description', $language),
                'short_description' => $event->getTranslation('short_description', $language),
                'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'cover_image' => $event->hero_image_url ? Storage::disk('public')->url($event->hero_image_url) : null,
                'category' => $event->marketplaceEventCategory?->getTranslation('name', $language),
                'starts_at' => $event->event_date?->format('Y-m-d') . 'T' . ($event->start_time ?? '00:00:00'),
                'ends_at' => $event->end_time ? $event->event_date?->format('Y-m-d') . 'T' . $event->end_time : null,
                'doors_open_at' => $event->door_time ? $event->event_date?->format('Y-m-d') . 'T' . $event->door_time : null,
                'venue_name' => $venue?->getTranslation('name', $language),
                'venue_address' => $venue?->address ?? $event->address,
                'venue_city' => $venue?->city,
                'capacity' => $venue?->capacity,
                'is_featured' => $event->is_homepage_featured || $event->is_general_featured,
            ],
            'organizer' => $organizer ? [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'logo' => $organizer->logo ? Storage::disk('public')->url($organizer->logo) : null,
                'description' => $organizer->description,
                'website' => $organizer->website,
                'social_links' => $organizer->social_links,
                'verified' => $organizer->verified_at !== null,
            ] : null,
            'ticket_types' => $event->ticketTypes->map(function ($tt) use ($language) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->getTranslation('name', $language) ?? $tt->name,
                    'description' => $tt->getTranslation('description', $language) ?? $tt->description,
                    'price' => (float) ($tt->price ?? $tt->price_cents / 100),
                    'currency' => $tt->currency ?? 'RON',
                    'available' => $tt->quantity_available ?? null,
                    'min_per_order' => $tt->min_per_order ?? 1,
                    'max_per_order' => $tt->max_per_order ?? 10,
                    'status' => $tt->status ?? 'on_sale',
                    'is_sold_out' => ($tt->quantity_available ?? 999) <= 0,
                ];
            }),
        ]);
    }

    /**
     * Get ticket availability for an event
     */
    public function availability(Request $request, int $eventId): JsonResponse
    {
        $client = $this->requireClient($request);

        $event = Event::where('marketplace_client_id', $client->id)
            ->where('id', $eventId)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $ticketTypes = $event->ticketTypes()
            ->get()
            ->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => (float) ($tt->price ?? $tt->price_cents / 100),
                    'available' => $tt->quantity_available ?? null,
                    'status' => ($tt->quantity_available ?? 999) <= 0 ? 'sold_out' : 'available',
                ];
            });

        return $this->success([
            'event_id' => $eventId,
            'ticket_types' => $ticketTypes,
            'last_updated' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get available categories
     */
    public function categories(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        // Get categories that have events
        $categoryIds = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->whereNotNull('marketplace_event_category_id')
            ->selectRaw('marketplace_event_category_id, COUNT(*) as event_count')
            ->groupBy('marketplace_event_category_id')
            ->pluck('event_count', 'marketplace_event_category_id');

        $categories = MarketplaceEventCategory::whereIn('id', $categoryIds->keys())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $language),
                'slug' => $cat->slug,
                'icon' => $cat->icon,
                'event_count' => $categoryIds[$cat->id] ?? 0,
            ]);

        return $this->success(['categories' => $categories]);
    }

    /**
     * Get available cities
     */
    public function cities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        // Get cities from venues of events
        $cities = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->with('venue:id,city')
            ->get()
            ->filter(fn ($e) => $e->venue?->city)
            ->groupBy(fn ($e) => $e->venue->city)
            ->map(fn ($group, $city) => [
                'name' => $city,
                'event_count' => $group->count(),
            ])
            ->sortByDesc('event_count')
            ->values();

        return $this->success(['cities' => $cities]);
    }

    /**
     * Format event for API response
     */
    protected function formatEvent(Event $event, string $language): array
    {
        $venue = $event->venue;
        $category = $event->marketplaceEventCategory;

        return [
            'id' => $event->id,
            'name' => $event->getTranslation('title', $language),
            'slug' => $event->slug,
            'short_description' => $event->getTranslation('short_description', $language),
            'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
            'category' => $category?->getTranslation('name', $language),
            'starts_at' => $event->event_date?->format('Y-m-d') . 'T' . ($event->start_time ?? '00:00:00'),
            'venue_name' => $venue?->getTranslation('name', $language),
            'venue_city' => $venue?->city,
            'is_featured' => $event->is_homepage_featured || $event->is_general_featured,
            'organizer' => $event->marketplaceOrganizer ? [
                'id' => $event->marketplaceOrganizer->id,
                'name' => $event->marketplaceOrganizer->name,
                'slug' => $event->marketplaceOrganizer->slug,
                'logo' => $event->marketplaceOrganizer->logo,
                'verified' => $event->marketplaceOrganizer->verified_at !== null,
            ] : null,
        ];
    }
}
