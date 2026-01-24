<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceOrganizer;
use App\Models\Platform\CoreCustomerEvent;
use App\Services\Analytics\RedisAnalyticsService;
use App\Services\GeoIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Filter upcoming only by default - exclude events that have already started
        if (!$request->has('include_past')) {
            $this->applyUpcomingFilter($query);
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

        // Filter by event genre (slug)
        if ($request->has('genre')) {
            $genreSlug = $request->genre;
            $query->whereHas('eventGenres', function ($q) use ($genreSlug) {
                $q->where('slug', $genreSlug);
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

        $query = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        $this->applyUpcomingFilter($query);

        // Featured type filter: homepage, general, category, or any
        $featuredType = $request->get('type', 'any');
        if ($featuredType === 'homepage') {
            $query->where('is_homepage_featured', true);
        } elseif ($featuredType === 'general') {
            $query->where('is_general_featured', true);
        } elseif ($featuredType === 'category') {
            $query->where('is_category_featured', true);
        } else {
            $query->where(function ($q) {
                $q->where('is_homepage_featured', true)
                    ->orWhere('is_general_featured', true)
                    ->orWhere('is_category_featured', true);
            });
        }

        // Require featured_image to be set (for category pages with banner display)
        if ($request->boolean('require_image')) {
            $query->whereNotNull('featured_image');
        }

        // Filter by category
        if ($request->has('category')) {
            $categorySlug = $request->category;
            $query->whereHas('marketplaceEventCategory', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug)
                    ->orWhere('name->ro', 'like', "%{$categorySlug}%")
                    ->orWhere('name->en', 'like', "%{$categorySlug}%");
            });
        }

        $events = $query->with([
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
            ->map(fn ($event) => $this->formatEventWithFeaturedImage($event, $language, $client));

        return $this->success(['events' => $events]);
    }

    /**
     * Format event for API response with featured_image
     */
    protected function formatEventWithFeaturedImage(Event $event, string $language, $client = null): array
    {
        $formatted = $this->formatEvent($event, $language, $client);

        // Add featured_image for featured events display
        $formatted['featured_image'] = $event->featured_image
            ? Storage::disk('public')->url($event->featured_image)
            : null;

        return $formatted;
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
            'venue.seatingLayouts' => function ($q) {
                $q->withoutGlobalScopes()
                    ->where('status', 'published')
                    ->with(['sections.rows.seats']);
            },
            'marketplaceEventCategory',
            'ticketTypes.seatingSections',
            'artists',
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
                // Event status flags
                'is_sold_out' => (bool) ($event->is_sold_out ?? false),
                'is_cancelled' => (bool) ($event->is_cancelled ?? false),
                'cancel_reason' => $event->is_cancelled ? ($event->cancel_reason ?? null) : null,
                'is_postponed' => (bool) ($event->is_postponed ?? false),
                'postponed_reason' => $event->is_postponed ? ($event->postponed_reason ?? null) : null,
                'postponed_date' => $event->is_postponed && $event->postponed_date ? $event->postponed_date->format('Y-m-d') : null,
                'postponed_start_time' => $event->is_postponed ? ($event->postponed_start_time ?? null) : null,
                'postponed_door_time' => $event->is_postponed ? ($event->postponed_door_time ?? null) : null,
                'postponed_end_time' => $event->is_postponed ? ($event->postponed_end_time ?? null) : null,
                // Custom related events flags
                'has_custom_related' => (bool) $event->has_custom_related,
                'custom_related_event_ids' => $event->custom_related_event_ids ?? [],
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

                // Get seating sections if assigned
                $seatingSections = [];
                if ($tt->relationLoaded('seatingSections') && $tt->seatingSections->isNotEmpty()) {
                    $seatingSections = $tt->seatingSections->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'color' => $s->color_hex,
                        'color_hex' => $s->color_hex,
                        'seat_color' => $s->seat_color,
                    ])->values()->toArray();
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
                    'has_seating' => !empty($seatingSections),
                    'seating_sections' => $seatingSections,
                ];
            })->values(),
            'artists' => $event->artists->map(function ($artist) use ($language) {
                // Get bio and truncate to first 80 words
                $bio = $artist->getTranslation('bio_html', $language) ?? '';
                if (is_array($bio)) {
                    $bio = $bio[$language] ?? $bio['ro'] ?? $bio['en'] ?? '';
                }
                $bioText = strip_tags($bio);
                $words = preg_split('/\s+/', $bioText, -1, PREG_SPLIT_NO_EMPTY);
                $truncatedBio = count($words) > 32
                    ? implode(' ', array_slice($words, 0, 32)) . '...'
                    : $bioText;

                // Build social links array (only include those that exist)
                $socialLinks = [];
                if ($artist->facebook_url) $socialLinks['facebook'] = $artist->facebook_url;
                if ($artist->instagram_url) $socialLinks['instagram'] = $artist->instagram_url;
                if ($artist->tiktok_url) $socialLinks['tiktok'] = $artist->tiktok_url;
                if ($artist->youtube_url) $socialLinks['youtube'] = $artist->youtube_url;
                if ($artist->spotify_url) $socialLinks['spotify'] = $artist->spotify_url;
                if ($artist->website) $socialLinks['website'] = $artist->website;

                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'slug' => $artist->slug,
                    'image_url' => $artist->main_image_full_url ?? $artist->image_url,
                    'bio' => $truncatedBio,
                    'social_links' => $socialLinks,
                    'is_headliner' => $artist->pivot->is_headliner ?? false,
                    'is_co_headliner' => $artist->pivot->is_co_headliner ?? false,
                ];
            })->values(),
            'commission_mode' => $commissionMode,
            'commission_rate' => (float) $commissionRate,
            'taxes' => $taxes,
            // Seating layout if venue has one (includes event_seating_id for seat holds)
            'seating_layout' => $this->getSeatingLayout($venue, $event),
            // Custom related events (array at root level for frontend)
            'custom_related_events' => $this->getCustomRelatedEvents($event, $language, $client),
        ]);
    }

    /**
     * Get formatted custom related events
     */
    protected function getCustomRelatedEvents(Event $event, string $language, $client = null): array
    {
        if (!$event->has_custom_related || empty($event->custom_related_event_ids)) {
            return [];
        }

        $relatedQuery = Event::whereIn('id', $event->custom_related_event_ids)
            ->where('id', '!=', $event->id)
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        $this->applyUpcomingFilter($relatedQuery);

        $relatedEvents = $relatedQuery->with([
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
            ->get();

        return $relatedEvents->map(fn ($e) => $this->formatEvent($e, $language, $client))->values()->toArray();
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
        $catQuery = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        $this->applyUpcomingFilter($catQuery);

        $categoryIds = $catQuery->whereNotNull('marketplace_event_category_id')
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
        $query = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        $this->applyUpcomingFilter($query);

        // Filter by genre if provided
        if ($request->has('genre')) {
            $genreSlug = $request->genre;
            $query->whereHas('eventGenres', function ($q) use ($genreSlug) {
                $q->where('slug', $genreSlug);
            });
        }

        // Filter by category if provided
        if ($request->has('category')) {
            $categorySlug = $request->category;
            $query->whereHas('marketplaceEventCategory', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        $cities = $query->with('venue:id,city')
            ->get()
            ->filter(fn ($e) => $e->venue?->city)
            ->groupBy(fn ($e) => strtolower(trim($e->venue->city))) // Normalize city names
            ->map(function ($group, $cityKey) {
                // Use the first occurrence's original city name for display
                return [
                    'name' => $group->first()->venue->city,
                    'event_count' => $group->count(),
                ];
            })
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

        // Create CoreCustomerEvent for analytics tracking
        $visitorId = $request->cookie('visitor_id') ?? $request->header('X-Visitor-ID') ?? Str::uuid()->toString();
        $sessionId = $request->cookie('session_id') ?? $request->header('X-Session-ID') ?? Str::uuid()->toString();

        // Get location data from IP (uses multi-provider fallback: ipgeolocation.io -> ip-api.com -> ipwhois.io)
        $geoIpService = app(GeoIpService::class);
        $location = $geoIpService->getLocation($request->ip());

        CoreCustomerEvent::create([
            'marketplace_client_id' => $client->id,
            'marketplace_event_id' => $event->id, // Use marketplace_event_id for analytics queries
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'event_type' => CoreCustomerEvent::TYPE_PAGE_VIEW,
            'page_type' => 'event',
            'content_id' => $event->id,
            'content_type' => 'event',
            'content_name' => $event->getTranslation('title', $client->language ?? 'ro'),
            'page_url' => $request->header('Referer'),
            'page_path' => '/bilete/' . $event->slug,
            // Referrer from frontend takes priority, fallback to HTTP header
            'referrer' => $request->input('referrer') ?: $request->header('Referer'),
            // UTM parameters
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
            // Ad platform click IDs
            'gclid' => $request->input('gclid'),         // Google Ads
            'fbclid' => $request->input('fbclid'),       // Facebook/Meta Ads
            'ttclid' => $request->input('ttclid'),       // TikTok Ads
            'li_fat_id' => $request->input('li_fat_id'), // LinkedIn Ads
            // Facebook browser cookies
            'fbc' => $request->input('fbc'),
            'fbp' => $request->input('fbp'),
            // Device and location info
            'ip_address' => $request->ip(),
            'country_code' => $location['country_code'] ?? null,
            'region' => $location['region'] ?? null,
            'city' => $location['city'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'device_type' => $this->detectDeviceType($request),
            'browser' => $this->detectBrowser($request),
            'os' => $this->detectOS($request),
            'occurred_at' => now(),
        ]);

        // INSTANT: Write to Redis for real-time analytics (globe, live visitors)
        try {
            $redisAnalytics = app(RedisAnalyticsService::class);
            $redisAnalytics->trackVisitor(
                $event->id,
                $visitorId,
                $location,
                CoreCustomerEvent::TYPE_PAGE_VIEW
            );
        } catch (\Exception $e) {
            // Don't fail the request if Redis is unavailable
            Log::debug('Redis analytics tracking failed', ['error' => $e->getMessage()]);
        }

        return $this->success([
            'views_count' => $event->views_count,
        ]);
    }

    /**
     * Detect device type from user agent
     */
    protected function detectDeviceType(Request $request): string
    {
        $userAgent = strtolower($request->userAgent() ?? '');
        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') || str_contains($userAgent, 'iphone')) {
            return 'mobile';
        }
        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Detect browser from user agent
     */
    protected function detectBrowser(Request $request): ?string
    {
        $userAgent = $request->userAgent() ?? '';
        if (str_contains($userAgent, 'Chrome')) return 'Chrome';
        if (str_contains($userAgent, 'Firefox')) return 'Firefox';
        if (str_contains($userAgent, 'Safari')) return 'Safari';
        if (str_contains($userAgent, 'Edge')) return 'Edge';
        if (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident')) return 'IE';
        return null;
    }

    /**
     * Detect OS from user agent
     */
    protected function detectOS(Request $request): ?string
    {
        $userAgent = $request->userAgent() ?? '';
        if (str_contains($userAgent, 'Windows')) return 'Windows';
        if (str_contains($userAgent, 'Mac OS')) return 'macOS';
        if (str_contains($userAgent, 'Linux')) return 'Linux';
        if (str_contains($userAgent, 'Android')) return 'Android';
        if (str_contains($userAgent, 'iOS') || str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) return 'iOS';
        return null;
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
            'is_sold_out' => (bool) ($event->is_sold_out ?? false),
            'is_cancelled' => (bool) ($event->is_cancelled ?? false),
            'is_postponed' => (bool) ($event->is_postponed ?? false),
            'postponed_date' => $event->is_postponed && $event->postponed_date ? $event->postponed_date->format('Y-m-d') : null,
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
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    });
                $this->applyUpcomingFilter($query);
            })
            ->withCount(['events' => function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id)
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    });
                $this->applyUpcomingFilter($query);
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

    /**
     * Get seating layout data for a venue with per-event seat status
     *
     * @param $venue The venue model
     * @param $event The event model (for per-event seating)
     */
    protected function getSeatingLayout($venue, $event = null): ?array
    {
        if (!$venue || !$venue->relationLoaded('seatingLayouts')) {
            return null;
        }

        $layout = $venue->seatingLayouts->first();
        if (!$layout) {
            return null;
        }

        // Get or create per-event seating if event is provided
        $eventSeatingId = null;
        $seatStatusMap = [];

        if ($event) {
            $seatingService = app(\App\Services\Seating\MarketplaceEventSeatingService::class);
            $eventSeating = $seatingService->getOrCreateEventSeatingByEventId($event->id);

            if ($eventSeating) {
                $eventSeatingId = $eventSeating->id;

                // Build seat status map for quick lookup
                $eventSeats = $eventSeating->seats()->get(['seat_uid', 'status']);
                foreach ($eventSeats as $es) {
                    $seatStatusMap[$es->seat_uid] = $es->status;
                }
            }
        }

        // Build geometry data
        $sections = [];
        foreach ($layout->sections as $section) {
            $rows = [];
            foreach ($section->rows as $row) {
                $seats = [];
                foreach ($row->seats as $seat) {
                    // Check if seat is marked as 'imposibil' in the base layout
                    $baseStatus = $seat->status ?? 'active';

                    // If seat is 'imposibil' in base layout, it's always disabled
                    // Otherwise, use event seating status
                    if ($baseStatus === 'imposibil') {
                        $status = 'disabled';
                    } else {
                        $status = $seatStatusMap[$seat->seat_uid] ?? 'available';
                    }

                    $seats[] = [
                        'id' => $seat->id,
                        'seat_uid' => $seat->seat_uid, // Include for cart/hold API
                        'label' => $seat->label,
                        'x' => (float) $seat->x,
                        'y' => (float) $seat->y,
                        'status' => $status,
                        'base_status' => $baseStatus, // Include base status for display
                    ];
                }
                $rows[] = [
                    'id' => $row->id,
                    'label' => $row->label,
                    'seats' => $seats,
                ];
            }
            $sectionData = [
                'id' => $section->id,
                'name' => $section->name,
                'section_type' => $section->section_type ?? 'standard',
                'color' => $section->color_hex,
                'color_hex' => $section->color_hex,
                'seat_color' => $section->seat_color,
                'x' => $section->x_position,
                'y' => $section->y_position,
                'width' => $section->width,
                'height' => $section->height,
                'rotation' => $section->rotation,
                'metadata' => $section->metadata ?? [
                    'seat_size' => 18,
                    'seat_spacing' => 20,
                    'row_spacing' => 25,
                    'seat_shape' => 'circle',
                ],
                'rows' => $rows,
            ];

            // For icon sections, include icon SVG data from config
            if ($section->section_type === 'icon') {
                $iconKey = $section->metadata['icon_key'] ?? 'info_point';
                $iconDefinitions = config('seating-icons', []);
                $sectionData['icon_svg'] = $iconDefinitions[$iconKey]['svg'] ?? '';
                $sectionData['icon_label'] = $iconDefinitions[$iconKey]['label'] ?? $section->name;
            }

            $sections[] = $sectionData;
        }

        // Generate seating map preview image URL
        $bgPath = $layout->background_image_path ?? $layout->background_image_url;
        $bgUrl = null;
        if ($bgPath) {
            $bgUrl = str_starts_with($bgPath, 'http') ? $bgPath : Storage::disk('public')->url($bgPath);
        }

        return [
            'id' => $layout->id,
            'event_seating_id' => $eventSeatingId, // For seat hold/release API
            'name' => $layout->name,
            'canvas_width' => $layout->canvas_w,
            'canvas_height' => $layout->canvas_h,
            'background_image' => $bgUrl,
            'background_scale' => $layout->background_scale,
            'background_x' => $layout->background_x,
            'background_y' => $layout->background_y,
            'background_opacity' => $layout->background_opacity,
            'sections' => $sections,
        ];
    }

    /**
     * Apply upcoming event filter - excludes events that have already started/ended
     * based on their duration mode and respective date+time fields.
     */
    private function applyUpcomingFilter($query): void
    {
        $now = now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        $query->where(function ($q) use ($today, $currentTime) {
            // Range events: use range_end_date + range_end_time
            $q->where(function ($q2) use ($today, $currentTime) {
                $q2->where('duration_mode', 'range')
                    ->where(function ($q3) use ($today, $currentTime) {
                        $q3->where('range_end_date', '>', $today)
                            ->orWhere(function ($q4) use ($today, $currentTime) {
                                $q4->where('range_end_date', $today)
                                    ->where(function ($q5) use ($currentTime) {
                                        $q5->where('range_end_time', '>', $currentTime)
                                            ->orWhereNull('range_end_time');
                                    });
                            });
                    });
            })
            // Single day events: use event_date + start_time
            ->orWhere(function ($q2) use ($today, $currentTime) {
                $q2->where(function ($q3) {
                        $q3->where('duration_mode', 'single_day')
                            ->orWhereNull('duration_mode');
                    })
                    ->where(function ($q3) use ($today, $currentTime) {
                        $q3->where('event_date', '>', $today)
                            ->orWhere(function ($q4) use ($today, $currentTime) {
                                $q4->where('event_date', $today)
                                    ->where(function ($q5) use ($currentTime) {
                                        $q5->where('start_time', '>', $currentTime)
                                            ->orWhereNull('start_time');
                                    });
                            });
                    });
            })
            // Multi-day: check first slot date (simplified)
            ->orWhere(function ($q2) use ($today) {
                $q2->where('duration_mode', 'multi_day')
                    ->where('event_date', '>=', $today);
            });
        });
    }
}
