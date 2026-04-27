<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class VenuesController extends BaseController
{
    /**
     * Get venues listing with filters
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Venue::query()
            ->with('venueCategories')
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id));

        // Filter by city — case-insensitive, also matches without diacritics
        if ($request->filled('city')) {
            $city = $request->input('city');
            $query->whereRaw('LOWER(city) = LOWER(?)', [$city]);
        }

        // Filter by category
        if ($request->filled('category')) {
            $categorySlug = $request->input('category');
            $query->whereHas('venueCategories', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        // Featured only
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Partner only — check pivot table
        if ($request->boolean('partner')) {
            $query->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id)->where('is_partner', true));
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');

        switch ($sort) {
            case 'events':
                $query->withCount(['marketplaceEvents' => function ($q) {
                    $q->where('status', 'published')
                      ->where('starts_at', '>=', now());
                }])->orderBy('marketplace_events_count', 'desc');
                break;
            case 'capacity':
                $query->orderBy('capacity', $order);
                break;
            default:
                $query->orderBy('name', $order);
        }

        $perPage = min($request->input('per_page', 12), 50);
        $paginator = $query->paginate($perPage);

        $language = $client->language ?? 'ro';

        $venues = collect($paginator->items())->map(function ($venue) use ($language, $client) {
            return $this->formatVenue($venue, $language, $client->id);
        });

        return response()->json([
            'success' => true,
            'data' => $venues,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get featured venues
     * Returns venues marked as featured (random order so all promoted venues
     * rotate when more than `limit` exist), or falls back to venues with most
     * upcoming events.
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $limit = $request->input('limit', 6);

        // First try to get explicitly featured venues. inRandomOrder is what
        // gives the rotation when more than `limit` venues are flagged
        // featured — every page load shows a different subset.
        $venues = Venue::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id))
            ->where('is_featured', true)
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        // If no featured venues, get venues with most upcoming events
        if ($venues->isEmpty()) {
            $venues = Venue::query()
                ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id))
                ->get()
                ->map(function ($venue) use ($client) {
                    $venue->upcoming_events_count = $this->countVenueEvents($venue, $client->id);
                    return $venue;
                })
                ->filter(fn ($v) => $v->upcoming_events_count > 0)
                ->sortByDesc('upcoming_events_count')
                ->take($limit)
                ->values();
        }

        $language = $client->language ?? 'ro';

        return $this->success([
            'venues' => $venues->map(fn ($v) => $this->formatVenue($v, $language, $client->id)),
        ]);
    }

    /**
     * Get single venue by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $venue = Venue::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id))
            ->where('slug', $slug)
            ->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $language = $client->language ?? 'ro';
        $venueName = $venue->getTranslation('name', 'ro') ?: $venue->name;

        // Get upcoming marketplace events at this venue
        // Match by venue_id OR by venue_name/city for events without venue_id
        $upcomingEventsLimit = min((int) ($request->get('events_limit', 50)), 100);

        $upcomingEvents = \App\Models\MarketplaceEvent::query()
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->where(function ($query) use ($venue, $venueName) {
                $query->where('venue_id', $venue->id)
                    ->orWhere(function ($q) use ($venueName, $venue) {
                        $q->whereNull('venue_id')
                          ->whereRaw('LOWER(venue_name) = ?', [strtolower($venueName)])
                          ->whereRaw('LOWER(venue_city) = ?', [strtolower($venue->city)]);
                    });
            })
            ->with(['ticketTypes', 'marketplaceOrganizer:id,default_commission_mode,commission_rate', 'marketplaceEventCategory'])
            ->orderBy('starts_at')
            ->limit($upcomingEventsLimit)
            ->get()
            ->map(function ($event) use ($language, $client) {
                // Calculate min price from active ticket types
                $minPrice = $event->ticketTypes
                    ->filter(fn ($tt) => $tt->status === 'active')
                    ->map(function ($tt) {
                        return ($tt->sale_price_cents ?? $tt->price_cents) / 100;
                    })->filter()->min();

                // Get image URL — MarketplaceEvent has a single `image` column;
                // echo it into poster_url + hero_image_url so the horizontal
                // event card (which reads hero_image_url) actually renders,
                // matching what the artist-single page returns.
                $imageUrl = $this->formatImageUrl($event->image);

                // Get commission settings (event > organizer > marketplace default)
                $organizer = $event->marketplaceOrganizer;
                $commissionMode = $event->commission_mode ?? $organizer?->default_commission_mode ?? $client?->commission_mode ?? 'included';
                $commissionRate = (float) ($event->commission_rate ?? $organizer?->commission_rate ?? $client?->commission_rate ?? 5.0);

                // Get category name
                $categoryName = $event->marketplaceEventCategory?->getTranslation('name', $language);

                // Resolve artist names from artist_ids JSON
                $artistNames = [];
                if (!empty($event->artist_ids)) {
                    $artistNames = \App\Models\Artist::whereIn('id', $event->artist_ids)
                        ->pluck('name')
                        ->toArray();
                }

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'start_time' => $event->starts_at?->format('H:i'),
                    'price_from' => $minPrice,
                    'currency' => $event->ticketTypes->first()?->currency ?? 'RON',
                    'image' => $imageUrl,
                    'poster_url' => $imageUrl,
                    'hero_image_url' => $imageUrl,
                    'is_sold_out' => $event->is_sold_out ?? false,
                    'category' => $categoryName ? ['name' => $categoryName] : null,
                    'commission_mode' => $commissionMode,
                    'commission_rate' => $commissionRate,
                    'artists' => $artistNames,
                ];
            });

        // Also get upcoming events from Event table (events created via organizer panel)
        $upcomingCoreEvents = \App\Models\Event::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->where('venue_id', $venue->id)
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('event_date')->where('event_date', '>=', now()->toDateString());
                })->orWhere(function ($inner) {
                    $inner->whereNull('event_date')->where('starts_at', '>=', now());
                });
            })
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->with(['ticketTypes', 'artists:id,name', 'marketplaceEventCategory'])
            ->orderBy('starts_at')
            ->limit($upcomingEventsLimit)
            ->get()
            ->map(function ($event) use ($language, $client) {
                $minPrice = $event->ticketTypes
                    ->filter(fn ($tt) => $tt->status === 'active')
                    ->map(fn ($tt) => ($tt->sale_price_cents ?? $tt->price_cents) / 100)
                    ->filter()->min();

                // Core Event rows store images in dedicated columns. Return
                // all three so the horizontal card (hero_image_url) and any
                // poster fallback both have something to render, matching
                // the artist-single response shape.
                $posterUrl = $this->formatImageUrl($event->poster_url);
                $heroUrl = $this->formatImageUrl($event->hero_image_url);
                $imageUrl = $heroUrl ?? $posterUrl ?? $this->formatImageUrl($event->image_url);
                $startsAt = $event->starts_at ?? $event->event_date;

                // Event category — emit {name, slug} so the venue page's
                // "Evenimente viitoare" filter tabs can group events by
                // category. Mirror the shape used by the MarketplaceEvent
                // branch above.
                $category = null;
                $categoryModel = $event->marketplaceEventCategory;
                if ($categoryModel) {
                    $categoryName = $categoryModel->getTranslation('name', $language);
                    if (!$categoryName) {
                        $raw = $categoryModel->name;
                        $categoryName = is_array($raw) ? ($raw['ro'] ?? $raw['en'] ?? array_values($raw)[0] ?? null) : $raw;
                    }
                    if ($categoryName) {
                        $category = [
                            'name' => $categoryName,
                            'slug' => $categoryModel->slug,
                        ];
                    }
                }

                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $language) ?: $event->title,
                    'slug' => $event->slug,
                    'starts_at' => $startsAt?->toIso8601String(),
                    'start_time' => $event->start_time ?? $startsAt?->format('H:i'),
                    'price_from' => $minPrice,
                    'currency' => $event->ticketTypes->first()?->currency ?? 'RON',
                    'image' => $imageUrl,
                    'poster_url' => $posterUrl,
                    'hero_image_url' => $heroUrl,
                    'is_sold_out' => $event->is_sold_out ?? false,
                    'category' => $category,
                    'commission_mode' => $client?->commission_mode ?? 'included',
                    'commission_rate' => (float) ($client?->commission_rate ?? 5.0),
                    'artists' => $event->artists->pluck('name')->toArray(),
                ];
            });

        // Merge both event sources and sort by starts_at
        $allUpcomingEvents = $upcomingEvents->concat($upcomingCoreEvents)
            ->sortBy('starts_at')
            ->values()
            ->take($upcomingEventsLimit);

        // Resolve facilities keys → labels (with emoji icon prefix)
        $facilitiesResolved = collect($venue->facilities ?? [])->map(function ($key) {
            foreach (Venue::FACILITIES as $cat) {
                if (isset($cat['items'][$key])) {
                    $label = $cat['items'][$key];
                    // Label format: "🅿️ Parcare" — split icon from name
                    $parts = preg_split('/\s+/', $label, 2);
                    return [
                        'key'   => $key,
                        'icon'  => $parts[0] ?? '',
                        'label' => $parts[1] ?? $label,
                    ];
                }
            }
            return null;
        })->filter()->values()->toArray();

        // Build response
        $data = [
            'id' => $venue->id,
            'name' => $venue->getTranslation('name', $language),
            'slug' => $venue->slug,
            'description' => $venue->getTranslation('description', $language),
            'city' => $venue->city,
            'state' => $venue->state,
            'address' => $venue->address,
            'postal_code' => $venue->postal_code,
            'country' => $venue->country,
            'latitude' => $venue->lat,
            'longitude' => $venue->lng,
            'google_maps_url' => $venue->google_maps_url,
            'google_reviews' => $venue->google_reviews_payload,
            'capacity' => $venue->capacity_total ?? $venue->capacity,
            'image' => $this->formatImageUrl($venue->image_url),
            'portrait' => $this->formatImageUrl($venue->meta['portrait'] ?? null),
            'cover_image' => $this->formatImageUrl($venue->cover_image_url),
            'gallery' => collect($venue->gallery ?? [])->map(fn ($img) => $this->formatImageUrl($img))->filter()->values()->toArray(),
            'schedule' => $venue->schedule,
            'facilities' => $facilitiesResolved,
            'video_type' => $venue->video_type,
            'video_url' => $venue->video_url,
            'established_at' => $venue->established_at?->format('Y'),
            'is_partner' => $venue->is_partner ?? false,
            'is_featured' => $venue->is_featured ?? false,
            'phone' => $venue->phone,
            'email' => $venue->email,
            'website' => $venue->website_url,
            'contact' => [
                'phone' => $venue->phone,
                'email' => $venue->email,
                'website' => $venue->website_url,
            ],
            'social' => [
                'facebook' => $venue->facebook_url,
                'instagram' => $venue->instagram_url,
                'tiktok' => $venue->tiktok_url,
            ],
            'categories' => $venue->venueCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $language),
                'slug' => $cat->slug,
            ]),
            'upcoming_events' => $allUpcomingEvents,
            'events_count' => $this->countVenueEvents($venue, $client->id),
            // Similar venues — same city first; if there aren't enough, pad
            // with venues that share at least one category. Excludes self.
            'similar_venues' => $this->buildSimilarVenues($venue, $client, $language, 4),
        ];

        return $this->success($data);
    }

    /**
     * Find up to $limit venues similar to the given one within the same
     * marketplace: prefer same-city matches, then fall back to venues
     * sharing at least one category. Never returns the venue itself.
     */
    protected function buildSimilarVenues(Venue $venue, $client, string $language, int $limit = 4): array
    {
        $baseQuery = Venue::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id))
            ->where('id', '!=', $venue->id);

        $sameCity = collect();
        if (!empty($venue->city)) {
            $sameCity = (clone $baseQuery)
                ->whereRaw('LOWER(city) = LOWER(?)', [$venue->city])
                ->with('venueCategories')
                ->limit($limit)
                ->get();
        }

        $results = $sameCity;

        // Pad with category-matched venues if we have room left.
        if ($results->count() < $limit) {
            $categoryIds = $venue->venueCategories->pluck('id')->all();
            if (!empty($categoryIds)) {
                $missing = $limit - $results->count();
                $alreadyIncluded = $results->pluck('id')->all();
                $categoryMatches = (clone $baseQuery)
                    ->whereNotIn('id', $alreadyIncluded)
                    // Table is `marketplace_venue_categories` (see the
                    // MarketplaceVenueCategory model). The qualifier is
                    // required when using whereHas with multiple joins.
                    ->whereHas('venueCategories', fn ($q) => $q->whereIn('marketplace_venue_categories.id', $categoryIds))
                    ->with('venueCategories')
                    ->limit($missing)
                    ->get();
                $results = $results->concat($categoryMatches);
            }
        }

        return $results->map(function ($v) use ($language, $client) {
            return [
                'id' => $v->id,
                'name' => $v->getTranslation('name', $language) ?: $v->name,
                'slug' => $v->slug,
                'city' => $v->city,
                'image' => $this->formatImageUrl($v->image_url) ?? $this->formatImageUrl($v->cover_image_url),
                'events_count' => $this->countVenueEvents($v, $client->id),
                'categories' => $v->venueCategories->map(fn ($cat) => [
                    'name' => $cat->getTranslation('name', $language),
                    'slug' => $cat->slug,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Format venue for list display
     */
    protected function formatVenue(Venue $venue, string $language, ?int $marketplaceClientId = null): array
    {
        return [
            'id' => $venue->id,
            'name' => $venue->getTranslation('name', $language),
            'slug' => $venue->slug,
            'city' => $venue->city,
            'address' => $venue->address,
            'capacity' => $venue->capacity,
            'image' => $this->formatImageUrl($venue->image_url),
            'portrait' => $this->formatImageUrl($venue->meta['portrait'] ?? null),
            'events_count' => $this->countVenueEvents($venue, $marketplaceClientId),
            'is_featured' => $venue->is_featured ?? false,
            'is_partner' => true, // All venues returned by this API are in the marketplace's partner list
            'categories' => $venue->venueCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $language),
                'slug' => $cat->slug,
            ]),
        ];
    }

    /**
     * Count upcoming published events for a venue within a specific marketplace
     */
    protected function countVenueEvents(Venue $venue, ?int $marketplaceClientId = null): int
    {
        $venueName = $venue->getTranslation('name', 'ro') ?: $venue->name;
        $count = 0;

        // Count from MarketplaceEvent table
        $count += \App\Models\MarketplaceEvent::where('marketplace_client_id', $marketplaceClientId)
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->where(function ($query) use ($venue, $venueName) {
                $query->where('venue_id', $venue->id)
                    ->orWhere(function ($q) use ($venueName, $venue) {
                        $q->whereNull('venue_id')
                          ->whereRaw('LOWER(venue_name) = ?', [strtolower($venueName)])
                          ->whereRaw('LOWER(venue_city) = ?', [strtolower($venue->city)]);
                    });
            })
            ->count();

        // Count from Event table
        $count += \App\Models\Event::where('marketplace_client_id', $marketplaceClientId)
            ->where('is_published', true)
            ->where('venue_id', $venue->id)
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('event_date')->where('event_date', '>=', now()->toDateString());
                })->orWhere(function ($inner) {
                    $inner->whereNull('event_date')->where('starts_at', '>=', now());
                });
            })
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->count();

        return $count;
    }

    /**
     * Send a contact message to a venue
     */
    public function contact(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $venue = Venue::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_client_id', $client->id))
            ->where('slug', $slug)
            ->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $venueEmail = $venue->email;
        if (!$venueEmail) {
            return $this->error('Această locație nu are o adresă de email configurată.', 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:2000',
        ]);

        $siteName = $client->name ?? 'Bilete.online';

        $body = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">';
        $body .= '<h2 style="color:#A51C30;">Mesaj nou de pe ' . e($siteName) . '</h2>';
        $body .= '<p><strong>De la:</strong> ' . e($validated['name']) . ' (' . e($validated['email']) . ')</p>';
        $body .= '<p><strong>Subiect:</strong> ' . e($validated['subject']) . '</p>';
        $body .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">';
        $body .= '<div style="white-space:pre-line;color:#374151;">' . e($validated['message']) . '</div>';
        $body .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">';
        $body .= '<p style="color:#9ca3af;font-size:12px;">Acest mesaj a fost trimis prin intermediul ' . e($siteName) . ' pentru locația ' . e($venue->name) . '.</p>';
        $body .= '</div>';

        // Route through the marketplace's configured SMTP transport so
        // venue contact emails come from the marketplace's domain (and
        // never fall back to localhost). sendViaMarketplace() handles
        // logging + transactional vs primary routing.
        $this->sendMarketplaceEmail(
            $client,
            $venueEmail,
            $venue->name,
            '[' . $siteName . '] ' . $validated['subject'],
            $body,
            [
                'template_slug' => 'venue_contact',
                'reply_to_email' => $validated['email'],
                'reply_to_name' => $validated['name'],
            ]
        );

        return $this->success(['message' => 'Mesajul a fost trimis cu succes.']);
    }

    /**
     * Format image URL with full domain
     */
    protected function formatImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($imagePath, '/');
    }
}
