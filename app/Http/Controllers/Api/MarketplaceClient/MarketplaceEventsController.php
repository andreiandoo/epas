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
                'marketplaceOrganizer:id,name,slug,logo,verified_at,default_commission_mode,commission_rate',
                'marketplaceEventCategory',
                'venue:id,name,city,address',
                'ticketTypes' => function ($query) {
                    $query->select('id', 'event_id', 'price_cents', 'sale_price_cents')
                        ->where('status', 'active');
                },
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

        // Filter by artist (slug or name)
        if ($request->has('artist')) {
            $artistSlug = $request->artist;
            $query->whereHas('artists', function ($q) use ($artistSlug) {
                $q->where('slug', $artistSlug)
                    ->orWhere('name', 'like', "%{$artistSlug}%");
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
            case 'latest':
                // Sort by creation date (newest first)
                $query->orderBy('created_at', 'desc');
                break;
            case 'date_asc':
            default:
                $query->orderBy('event_date', 'asc')->orderBy('start_time', 'asc');
                break;
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events, function ($event) use ($language, $client) {
            return $this->formatEvent($event, $language, $client);
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
            ->with([
                'marketplaceOrganizer:id,name,slug,logo,verified_at,default_commission_mode,commission_rate',
                'venue:id,name,city',
                'marketplaceEventCategory',
                'ticketTypes' => function ($query) {
                    $query->select('id', 'event_id', 'price_cents', 'sale_price_cents')
                        ->where('status', 'active');
                },
            ])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(min((int) $request->get('limit', 10), 50))
            ->get()
            ->map(fn ($event) => $this->formatEvent($event, $language, $client));

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

        // Get commission settings (event > organizer > marketplace default)
        $commissionMode = $event->commission_mode ?? $organizer?->commission_mode ?? $client->commission_mode ?? 'included';
        $commissionRate = $event->commission_rate ?? $organizer?->commission_rate ?? $client->commission_rate ?? 5.0;

        // Get target_price for discount display
        // Debug: Log raw value from database
        \Log::info('[MarketplaceEventsController] Event ID: ' . $event->id);
        \Log::info('[MarketplaceEventsController] Raw target_price from DB: ' . var_export($event->getAttributes()['target_price'] ?? 'NOT_SET', true));
        \Log::info('[MarketplaceEventsController] Accessor target_price: ' . var_export($event->target_price, true));

        $targetPrice = $event->target_price ? (float) $event->target_price : null;
        \Log::info('[MarketplaceEventsController] Final targetPrice: ' . var_export($targetPrice, true));

        // Get ALL global active taxes (tenant_id is NULL)
        // We fetch all global taxes, not filtered by eventTypes, because:
        // 1. Event might not have eventTypes assigned
        // 2. Taxes like "Timbru Muzical" and "UCMR-ADA" should apply to all music events
        $applicableTaxes = \App\Models\Tax\GeneralTax::query()
            ->whereNull('tenant_id') // Global taxes only
            ->active()
            ->validOn()
            ->byPriority()
            ->get();

        // Debug: Log tax count
        \Log::info('[MarketplaceEventsController] Found ' . $applicableTaxes->count() . ' global taxes');

        $taxes = $applicableTaxes->map(fn ($tax) => [
            'name' => $tax->name,
            'value' => (float) $tax->value,
            'value_type' => $tax->value_type,
            'is_added_to_price' => $tax->is_added_to_price,
            'is_active' => $tax->is_active,
        ])->values()->toArray();

        // Build venue object
        $venueData = null;
        if ($venue) {
            $venueData = [
                'id' => $venue->id,
                'name' => $venue->getTranslation('name', $language),
                'slug' => $venue->slug,
                'description' => $venue->getTranslation('description', $language) ?? '',
                'address' => $venue->address ?? $event->address,
                'city' => $venue->city,
                'state' => $venue->state,
                'country' => $venue->country,
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'google_maps_url' => $venue->google_maps_url,
                'image' => $venue->image_url ? Storage::disk('public')->url($venue->image_url) : null,
                'capacity' => $venue->capacity,
            ];
        }

        // Get image URLs
        $posterImage = $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null;
        $coverImage = $event->hero_image_url ? Storage::disk('public')->url($event->hero_image_url) : null;

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('title', $language),
                'slug' => $event->slug,
                'description' => $event->getTranslation('description', $language),
                'short_description' => $event->getTranslation('short_description', $language),
                // Image fields - provide both naming conventions for compatibility
                'image' => $posterImage,
                'image_url' => $posterImage,
                'cover_image' => $coverImage,
                'cover_image_url' => $coverImage ?? $posterImage,
                'category' => $event->marketplaceEventCategory?->getTranslation('name', $language),
                // Schedule info
                'duration_mode' => $event->duration_mode ?? 'single_day',
                // Single day fields
                'starts_at' => $event->event_date?->format('Y-m-d') . 'T' . ($event->start_time ?? '00:00:00'),
                'ends_at' => $event->end_time ? $event->event_date?->format('Y-m-d') . 'T' . $event->end_time : null,
                'doors_open_at' => $event->door_time ? $event->event_date?->format('Y-m-d') . 'T' . $event->door_time : null,
                // Range/festival fields
                'range_start_date' => $event->range_start_date?->format('Y-m-d'),
                'range_end_date' => $event->range_end_date?->format('Y-m-d'),
                'range_start_time' => $event->range_start_time,
                'range_end_time' => $event->range_end_time,
                // Multi-day slots
                'multi_slots' => $event->multi_slots,
                // Keep flat venue fields for backwards compatibility
                'venue_name' => $venue?->getTranslation('name', $language),
                'venue_address' => $venue?->address ?? $event->address,
                'venue_city' => $venue?->city,
                'capacity' => $venue?->capacity,
                'is_featured' => $event->is_homepage_featured || $event->is_general_featured,
                'target_price' => $targetPrice,
                'views_count' => (int) ($event->views_count ?? 0),
                'interested_count' => (int) ($event->interested_count ?? 0),
            ],
            'venue' => $venueData,
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
            'ticket_types' => $event->ticketTypes->filter(fn ($tt) => $tt->status === 'active')->map(function ($tt) use ($language, $targetPrice, $commissionMode, $commissionRate) {
                $available = max(0, ($tt->quota_total ?? 0) - ($tt->quota_sold ?? 0));
                $basePrice = ($tt->sale_price_cents ?? $tt->price_cents) / 100;

                // Calculate original_price:
                // 1. If ticket has its own original_price, use that
                // 2. If event has target_price and no ticket original_price, use target_price
                $originalPrice = null;
                if ($tt->original_price_cents && $tt->original_price_cents > ($tt->sale_price_cents ?? $tt->price_cents)) {
                    $originalPrice = $tt->original_price_cents / 100;
                } elseif ($targetPrice && $targetPrice > $basePrice) {
                    $originalPrice = $targetPrice;
                }

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $basePrice,
                    'original_price' => $originalPrice ? (float) $originalPrice : null,
                    'currency' => $tt->currency ?? 'RON',
                    'available' => $available,
                    'min_per_order' => $tt->min_per_order ?? 1,
                    'max_per_order' => $tt->max_per_order ?? 10,
                    'status' => $tt->status,
                    'is_sold_out' => $available <= 0,
                ];
            })->values(),
            'commission_mode' => $commissionMode,
            'commission_rate' => (float) $commissionRate,
            'taxes' => $taxes,
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
            ->where('status', 'active')
            ->get()
            ->map(function ($tt) {
                $available = max(0, ($tt->quota_total ?? 0) - ($tt->quota_sold ?? 0));
                $displayPrice = ($tt->sale_price_cents ?? $tt->price_cents) / 100;
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => (float) $displayPrice,
                    'available' => $available,
                    'status' => $available <= 0 ? 'sold_out' : 'available',
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
     * Track a view for an event
     */
    public function trackView(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::where('marketplace_client_id', $client->id);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Increment views count
        $event->increment('views_count');

        return $this->success([
            'views_count' => $event->views_count,
        ]);
    }

    /**
     * Toggle interest for an event
     */
    public function toggleInterest(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::where('marketplace_client_id', $client->id);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Get session ID for anonymous users
        // Use header, cookie, or generate based on IP+User-Agent
        $sessionId = $request->header('X-Session-ID')
            ?? $request->cookie('ambilet_session')
            ?? md5($request->ip() . $request->userAgent());

        // Check if already interested
        $existing = \DB::table('event_interests')
            ->where('event_id', $event->id)
            ->where('session_id', $sessionId)
            ->first();

        if ($existing) {
            // Remove interest
            \DB::table('event_interests')
                ->where('event_id', $event->id)
                ->where('session_id', $sessionId)
                ->delete();

            $event->decrement('interested_count');

            return $this->success([
                'is_interested' => false,
                'interested_count' => max(0, $event->interested_count),
            ]);
        }

        // Add interest
        \DB::table('event_interests')->insert([
            'event_id' => $event->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event->increment('interested_count');

        return $this->success([
            'is_interested' => true,
            'interested_count' => $event->interested_count,
        ]);
    }

    /**
     * Check if user is interested in an event
     */
    public function checkInterest(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::where('marketplace_client_id', $client->id);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Get session ID for anonymous users
        // Use header, cookie, or generate based on IP+User-Agent
        $sessionId = $request->header('X-Session-ID')
            ?? $request->cookie('ambilet_session')
            ?? md5($request->ip() . $request->userAgent());

        // Check if interested
        $isInterested = \DB::table('event_interests')
            ->where('event_id', $event->id)
            ->where('session_id', $sessionId)
            ->exists();

        return $this->success([
            'is_interested' => $isInterested,
            'interested_count' => $event->interested_count ?? 0,
            'views_count' => $event->views_count ?? 0,
        ]);
    }

    /**
     * Format event for API response
     */
    protected function formatEvent(Event $event, string $language, $client = null): array
    {
        $venue = $event->venue;
        $category = $event->marketplaceEventCategory;
        $organizer = $event->marketplaceOrganizer;

        // Get commission settings (event > organizer > marketplace default)
        $commissionMode = $event->commission_mode ?? $organizer?->default_commission_mode ?? $client?->commission_mode ?? 'included';
        $commissionRate = (float) ($event->commission_rate ?? $organizer?->commission_rate ?? $client?->commission_rate ?? 5.0);

        // Get minimum price from ticket types (price stored in cents)
        // Use sale_price_cents if set, otherwise price_cents
        $minPrice = null;
        if ($event->relationLoaded('ticketTypes') && $event->ticketTypes->isNotEmpty()) {
            $minPriceCents = $event->ticketTypes->map(function ($ticket) {
                // Use sale price if set and greater than 0, otherwise regular price
                if ($ticket->sale_price_cents !== null && $ticket->sale_price_cents > 0) {
                    return $ticket->sale_price_cents;
                }
                return $ticket->price_cents;
            })->min();

            if ($minPriceCents !== null) {
                $minPrice = $minPriceCents / 100;
            }
        }
        if ($minPrice === null && $event->min_price !== null) {
            $minPrice = $event->min_price;
        }

        return [
            'id' => $event->id,
            'name' => $event->getTranslation('title', $language),
            'slug' => $event->slug,
            'short_description' => $event->getTranslation('short_description', $language),
            'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
            ] : null,
            'starts_at' => $event->event_date?->format('Y-m-d') . 'T' . ($event->start_time ?? '00:00:00'),
            'duration_mode' => $event->duration_mode ?? 'single_day',
            'range_start_date' => $event->range_start_date?->format('Y-m-d'),
            'range_end_date' => $event->range_end_date?->format('Y-m-d'),
            'venue_name' => $venue?->getTranslation('name', $language),
            'venue_city' => $venue?->city,
            'is_featured' => $event->is_homepage_featured || $event->is_general_featured,
            'is_sold_out' => $event->is_sold_out ?? false,
            'price_from' => $minPrice,
            'commission_mode' => $commissionMode,
            'commission_rate' => $commissionRate,
            'organizer' => $organizer ? [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'logo' => $organizer->logo,
                'verified' => $organizer->verified_at !== null,
            ] : null,
        ];
    }

    /**
     * Get event genres with event counts
     */
    public function genres(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        // Get all event genres that have events in this marketplace
        $genres = \App\Models\EventGenre::query()
            ->whereHas('events', function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id)
                    ->where('event_date', '>=', now()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    });
            })
            ->withCount(['events' => function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id)
                    ->where('event_date', '>=', now()->toDateString())
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    });
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($genre) use ($language) {
                return [
                    'id' => $genre->id,
                    'name' => $genre->getTranslation('name', $language),
                    'slug' => $genre->slug,
                    'event_count' => $genre->events_count,
                ];
            });

        return $this->success([
            'genres' => $genres,
        ]);
    }
}
