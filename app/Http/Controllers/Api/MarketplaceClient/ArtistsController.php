<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Artist;
use App\Models\ArtistGenre;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ArtistsController extends BaseController
{
    /**
     * Get artists listing with filters
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Artist::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $client->id))
            ->where('is_active', true);

        // Filter by letter
        if ($request->filled('letter')) {
            $query->where('letter', strtoupper($request->input('letter')));
        }

        // Filter by genre
        if ($request->filled('genre')) {
            $genreSlug = $request->input('genre');
            $query->whereHas('artistGenres', function ($q) use ($genreSlug) {
                $q->where('slug', $genreSlug);
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $typeSlug = $request->input('type');
            $query->whereHas('artistTypes', function ($q) use ($typeSlug) {
                $q->where('slug', $typeSlug);
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', $request->input('city'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        // Featured only
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // With upcoming events only
        if ($request->boolean('with_events')) {
            $query->whereHas('events', function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->where('is_cancelled', false);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');

        switch ($sort) {
            case 'followers':
                $query->orderByRaw('COALESCE(spotify_monthly_listeners, 0) + COALESCE(instagram_followers, 0) DESC');
                break;
            case 'events':
                $query->withCount(['events' => function ($q) {
                    $q->where('event_date', '>=', now()->toDateString());
                }])->orderBy('events_count', 'desc');
                break;
            default:
                $query->orderBy('name', $order);
        }

        $perPage = min($request->input('per_page', 12), 50);
        $paginator = $query->paginate($perPage);

        $language = $client->language ?? 'ro';

        $artists = collect($paginator->items())->map(function ($artist) use ($language, $client) {
            return $this->formatArtist($artist, $language, false, $client);
        });

        return response()->json([
            'success' => true,
            'data' => $artists,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get featured artists
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $artists = Artist::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $client->id))
            ->where('is_featured', true)
            ->where('is_active', true)
            ->limit($request->input('limit', 6))
            ->get();

        $language = $client->language ?? 'ro';

        return $this->success([
            'artists' => $artists->map(fn ($a) => $this->formatArtist($a, $language, false, $client)),
        ]);
    }

    /**
     * Get trending artists (by upcoming events + followers)
     */
    public function trending(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $artists = Artist::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $client->id))
            ->where('is_active', true)
            ->whereHas('events', function ($q) use ($client) {
                $q->where('marketplace_client_id', $client->id)
                  ->where('is_published', true)
                  ->where('status', 'published')
                  ->where('event_date', '>=', now()->toDateString())
                  ->where('is_cancelled', false);
            })
            ->withCount(['events' => function ($q) use ($client) {
                $q->where('marketplace_client_id', $client->id)
                  ->where('is_published', true)
                  ->where('status', 'published')
                  ->where('event_date', '>=', now()->toDateString())
                  ->where('is_cancelled', false);
            }])
            ->orderByRaw('COALESCE(spotify_monthly_listeners, 0) + COALESCE(instagram_followers, 0) DESC')
            ->limit($request->input('limit', 8))
            ->get();

        $language = $client->language ?? 'ro';

        return $this->success([
            'artists' => $artists->map(fn ($a) => $this->formatArtist($a, $language, false, $client)),
        ]);
    }

    /**
     * Get genre counts for filter
     */
    public function genreCounts(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Postgres doesn't allow aliased subquery columns in HAVING (unlike
        // MySQL), so we filter the genres with zero artists in PHP after
        // fetching. The set is small (a few dozen genres at most), so the
        // post-filter is cheap.
        $genres = ArtistGenre::query()
            ->withCount(['artists' => function ($q) use ($client) {
                $q->whereHas('marketplaceClients', fn ($mq) => $mq->where('marketplace_artist_partners.marketplace_client_id', $client->id))
                  ->where('is_active', true);
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn ($genre) => ($genre->artists_count ?? 0) > 0)
            ->values()
            ->map(function ($genre) {
                return [
                    'id' => $genre->id,
                    'name' => $genre->name,
                    'slug' => $genre->slug,
                    'count' => $genre->artists_count,
                ];
            });

        return $this->success([
            'genres' => $genres,
        ]);
    }

    /**
     * Get available alphabet letters
     */
    public function alphabet(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $letters = Artist::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $client->id))
            ->where('is_active', true)
            ->whereNotNull('letter')
            ->distinct()
            ->pluck('letter')
            ->sort()
            ->values();

        return $this->success([
            'letters' => $letters,
        ]);
    }

    /**
     * Get single artist by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        // First, try to find a partner artist of THIS marketplace.
        $artist = Artist::query()
            ->whereHas('marketplaceClients', fn ($q) => $q
                ->where('marketplace_artist_partners.marketplace_client_id', $client->id)
                ->where('marketplace_artist_partners.is_partner', true))
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$artist) {
            // Slug exists somewhere but not a partner here → distinguish three cases.
            $existing = Artist::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if (!$existing) {
                // True 404 — slug doesn't match any artist.
                return $this->error('Artist not found', 404);
            }

            $hasAnyPartnership = $existing->marketplaceClients()->exists();

            if (!$hasAnyPartnership) {
                // Core-only artist — render the existing "coming soon" UI.
                return $this->success([
                    'id' => $existing->id,
                    'name' => $existing->name,
                    'slug' => $existing->slug,
                    'image' => $existing->main_image_full_url,
                    'portrait' => $existing->portrait_full_url,
                    'logo' => $existing->logo_full_url,
                    'is_coming_soon' => true,
                ]);
            }

            // Artist exists and is partnered with another marketplace (or with this
            // one but is_partner=false). Frontend treats this as "redirect to
            // /artisti" — the artist is just not relevant on this marketplace.
            return $this->success([
                'id' => $existing->id,
                'name' => $existing->name,
                'slug' => $existing->slug,
                'is_partner' => false,
            ]);
        }

        $language = $client->language ?? 'ro';

        // Optional tour-slug filter: when present, restricts upcoming_events to
        // a specific serie_evenimente grouping (used by the /{artist}/{tour} URL).
        $tourSlugFilter = $request->query('tour_slug');
        $tourIdFilter = null;
        if ($tourSlugFilter) {
            $tourIdFilter = \App\Models\Tour::where('slug', $tourSlugFilter)
                ->where('marketplace_client_id', $client->id)
                ->where('type', 'serie_evenimente')
                ->value('id');
        }

        // Get upcoming events with ticket types for price calculation.
        // Public artist page must only show events that are (a) live on THIS
        // marketplace, (b) actually published (is_published=true is the
        // visibility flag), AND (c) have status='published' (not draft, not
        // archived — both are separate from is_published in this codebase).
        //
        // "Upcoming" is decided per duration_mode — using only event_date
        // hides range / multi_day / recurring events whose date columns
        // live elsewhere (range_end_date, multi_slots JSON, recurring_*).
        //   single_day → event_date >= today
        //   range      → range_end_date >= today (still running counts)
        //   multi_day  → at least one slot date >= today (refined in PHP
        //                after fetch — SQL pre-filter is non-NULL slots)
        //   recurring  → template parents are visible (children resolve
        //                their own per-occurrence date)
        //   legacy / null mode → fall back to event_date >= today
        $today = now()->toDateString();
        $upcomingEvents = $artist->events()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->where('status', 'published')
            ->with(['ticketTypes' => function ($q) {
                $q->where('status', 'active');
            }, 'venue', 'marketplaceOrganizer:id,default_commission_mode,commission_rate', 'marketplaceEventCategory'])
            ->where('is_cancelled', false)
            ->where(function ($q) use ($today) {
                $q->where(function ($q1) use ($today) {
                    $q1->where('duration_mode', 'single_day')
                       ->where('event_date', '>=', $today);
                })->orWhere(function ($q1) use ($today) {
                    $q1->where('duration_mode', 'range')
                       ->where('range_end_date', '>=', $today);
                })->orWhere(function ($q1) {
                    $q1->where('duration_mode', 'multi_day')
                       ->whereNotNull('multi_slots');
                })->orWhere(function ($q1) {
                    $q1->where('duration_mode', 'recurring');
                })->orWhere(function ($q1) use ($today) {
                    $q1->whereNull('duration_mode')
                       ->where('event_date', '>=', $today);
                });
            })
            ->when($tourIdFilter, fn ($q) => $q->where('tour_id', $tourIdFilter))
            ->orderByRaw('COALESCE(event_date, range_start_date) ASC NULLS LAST')
            ->when(!$tourIdFilter, fn ($q) => $q->limit(50))
            ->get()
            ->filter(function ($event) use ($today) {
                // Drop multi_day events whose every slot is in the past.
                // (SQL kept all multi_day rows with non-NULL multi_slots
                // because filtering JSON arrays per-DB is messy; this
                // post-fetch check is cheap on the limited result set.)
                if ($event->duration_mode !== 'multi_day') {
                    return true;
                }
                $latest = collect($event->multi_slots ?? [])
                    ->pluck('date')
                    ->filter()
                    ->max();
                return $latest !== null && $latest >= $today;
            })
            ->values()
            ->map(function ($event) use ($language, $client) {
                // For child events (multi-day), inherit ticket types from parent
                $ticketTypes = $event->ticketTypes;
                if ($ticketTypes->isEmpty() && $event->parent_id) {
                    $ticketTypes = \App\Models\TicketType::where('event_id', $event->parent_id)
                        ->where('status', 'active')->get();
                }

                // Calculate min price — for child events, check performance overrides
                $matchedPerformance = null;
                if ($event->parent_id && $event->event_date) {
                    $childDate = $event->event_date->format('Y-m-d');
                    $childTime = $event->start_time ? substr($event->start_time, 0, 5) : null;
                    $matchedPerformance = \App\Models\Performance::where('event_id', $event->parent_id)
                        ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                        ->get()
                        ->first(function ($p) use ($childDate, $childTime) {
                            return $p->starts_at->format('Y-m-d') === $childDate
                                && (!$childTime || $p->starts_at->format('H:i') === $childTime);
                        });
                }

                $minPriceCents = $ticketTypes
                    ->map(function ($tt) use ($matchedPerformance) {
                        $baseCents = $tt->sale_price_cents ?? $tt->price_cents;
                        // Check performance override
                        if ($matchedPerformance) {
                            $overrideCents = $matchedPerformance->getEffectivePrice($tt);
                            if ($overrideCents !== null) {
                                return $overrideCents;
                            }
                        }
                        return $baseCents;
                    })
                    ->filter()
                    ->min();
                $minPrice = $minPriceCents ? $minPriceCents / 100 : null;
                $currency = $ticketTypes->first()?->currency ?? 'RON';

                // is_sold_out: use event flag if explicitly set, otherwise compute from ticket types
                $isSoldOut = (bool) $event->is_sold_out;
                if (!$isSoldOut && $ticketTypes->isNotEmpty()) {
                    $isSoldOut = $ticketTypes
                        ->where('status', 'active')
                        ->filter(fn ($tt) => $tt->quota_total >= 0)
                        ->isNotEmpty()
                        && $ticketTypes
                            ->where('status', 'active')
                            ->filter(fn ($tt) => $tt->quota_total >= 0)
                            ->every(fn ($tt) => $tt->quota_total <= ($tt->quota_sold ?? 0));
                }

                // Get commission settings (event > organizer > marketplace default)
                $organizer = $event->marketplaceOrganizer;
                $commissionMode = $event->commission_mode ?? $organizer?->default_commission_mode ?? $client?->commission_mode ?? 'included';
                $commissionRate = (float) ($event->commission_rate ?? $organizer?->commission_rate ?? $client?->commission_rate ?? 5.0);

                // Get category name
                $categoryName = $event->marketplaceEventCategory?->getTranslation('name', $language);

                // Resolve the effective starting date/time per duration_mode
                // so the frontend (artist-single.js / event card render) gets
                // a usable Date instead of NaN for range / multi_day events
                // whose event_date column is intentionally NULL.
                $effectiveDate = $event->event_date;
                $effectiveTime = $event->start_time;
                $rangeEndDate = null;
                if ($event->duration_mode === 'range') {
                    $effectiveDate = $event->range_start_date;
                    $effectiveTime = $event->range_start_time;
                    $rangeEndDate = $event->range_end_date;
                } elseif ($event->duration_mode === 'multi_day' && ! empty($event->multi_slots)) {
                    $sorted = collect($event->multi_slots)
                        ->filter(fn ($s) => ! empty($s['date']))
                        ->sortBy('date');
                    $first = $sorted->first();
                    if ($first) {
                        $effectiveDate = \Carbon\Carbon::parse($first['date']);
                        $effectiveTime = $first['start_time'] ?? null;
                    }
                    $last = $sorted->last();
                    if ($last) {
                        $rangeEndDate = \Carbon\Carbon::parse($last['date']);
                    }
                } elseif ($event->duration_mode === 'recurring' && $event->recurring_start_date) {
                    $effectiveDate = $event->recurring_start_date instanceof \Carbon\Carbon
                        ? $event->recurring_start_date
                        : \Carbon\Carbon::parse($event->recurring_start_date);
                    $effectiveTime = $event->recurring_start_time;
                }

                $effectiveDateStr = $effectiveDate
                    ? ($effectiveDate instanceof \Carbon\Carbon ? $effectiveDate->format('Y-m-d') : (string) $effectiveDate)
                    : null;

                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $language),
                    'slug' => $event->slug,
                    'duration_mode' => $event->duration_mode,
                    // event_date / start_time / starts_at now carry the
                    // effective values per mode so the frontend doesn't
                    // need to know about range_* / multi_slots.
                    'event_date' => $effectiveDateStr,
                    'start_time' => $effectiveTime,
                    'starts_at' => ($effectiveDateStr ?? '') . 'T' . ($effectiveTime ?? '00:00:00'),
                    'date_label' => $event->displayDateLabel(),
                    'range_end_date' => $rangeEndDate instanceof \Carbon\Carbon
                        ? $rangeEndDate->format('Y-m-d')
                        : ($rangeEndDate ? (string) $rangeEndDate : null),
                    'parent_slug' => $event->parent_id ? $event->parent?->slug : null,
                    'venue_name' => $event->venue?->getTranslation('name', $language) ?? $event->venue?->name,
                    'venue_city' => $event->venue?->city,
                    'price_from' => $minPrice,
                    'currency' => $currency,
                    'image' => $event->main_image_url ?? $event->poster_url,
                    'hero_image_url' => $event->hero_image_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($event->hero_image_url) : null,
                    'poster_url' => $event->poster_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($event->poster_url) : null,
                    'is_sold_out' => $isSoldOut,
                    'category' => $categoryName ? ['name' => $categoryName] : null,
                    'commission_mode' => $commissionMode,
                    'commission_rate' => $commissionRate,
                ];
            });

        // Get past events count — accept 'published' or 'archived' here since
        // events that have ended are routinely flipped to status='archived'
        // by the system, but they were once visible and the count should
        // reflect that history.
        $pastEventsCount = $artist->events()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->whereIn('status', ['published', 'archived'])
            ->where('event_date', '<', now()->toDateString())
            ->count();

        // Get similar artists (same genres)
        $genreIds = $artist->artistGenres()->pluck('id');
        $similarArtists = collect();

        if ($genreIds->isNotEmpty()) {
            $similarArtists = Artist::query()
                ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $client->id))
                ->where('is_active', true)
                ->where('id', '!=', $artist->id)
                ->whereHas('artistGenres', function ($q) use ($genreIds) {
                    $q->whereIn('artist_genres.id', $genreIds);
                })
                ->limit(6)
                ->get()
                ->map(fn ($a) => $this->formatArtist($a, $language, true, $client));
        }

        // Get event groupings (tours/series) for this artist's events.
        // Only currently-published events count — archived/draft tours
        // should not surface on the public artist page.
        $tourIds = $artist->events()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->where('status', 'published')
            ->whereNotNull('tour_id')
            ->pluck('tour_id')
            ->unique()
            ->values();

        $eventGroupings = collect();
        if ($tourIds->isNotEmpty()) {
            $tours = \App\Models\Tour::whereIn('id', $tourIds)->get();
            $eventGroupings = $tours->map(function ($tour) use ($language, $client) {
                $liveEvents = \App\Models\Event::where('tour_id', $tour->id)
                    ->where('marketplace_client_id', $client->id)
                    ->where('is_published', true)
                    ->where('is_cancelled', false)
                    ->where(function ($q) {
                        $q->whereNull('event_date')
                          ->orWhere('event_date', '>=', now()->toDateString())
                          ->orWhere('range_end_date', '>=', now()->toDateString());
                    })
                    ->with('venue:id,name,city')
                    ->orderByRaw('COALESCE(event_date, range_start_date) ASC')
                    ->get();

                if ($liveEvents->isEmpty()) return null;

                return [
                    'id' => $tour->id,
                    'name' => $tour->name,
                    'slug' => $tour->slug,
                    'type' => $tour->type,
                    'events' => $liveEvents->map(function ($event) use ($language) {
                        return [
                            'id' => $event->id,
                            'name' => $event->getTranslation('title', $language),
                            'slug' => $event->slug,
                            'starts_at' => $event->start_date?->format('Y-m-d') . 'T' . ($event->start_time ?? '00:00:00'),
                            'venue_name' => $event->venue?->getTranslation('name', $language) ?? $event->venue?->name,
                            'venue_city' => $event->venue?->city,
                            'image' => $event->poster_url,
                            'is_sold_out' => (bool) ($event->is_sold_out ?? false),
                        ];
                    }),
                ];
            })->filter()->values();
        }

        // Build response
        $data = [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'image' => $artist->main_image_full_url,
            'logo' => $artist->logo_full_url,
            'portrait' => $artist->portrait_full_url,
            'biography' => $artist->getTranslation('bio_html', $language),
            'biography_translations' => array_filter([
                'ro' => $artist->getTranslation('bio_html', 'ro'),
                'en' => $artist->getTranslation('bio_html', 'en'),
            ]),
            'city' => $artist->city,
            'country' => $artist->country,
            'is_verified' => $artist->is_featured,
            'genres' => $artist->artistGenres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->getTranslation('name', $language) ?: ($g->name['en'] ?? $g->name ?? ''),
                'slug' => $g->slug,
            ]),
            'types' => $artist->artistTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->getTranslation('name', $language) ?: ($t->name['en'] ?? $t->name ?? ''),
                'slug' => $t->slug,
            ]),
            'stats' => [
                'spotify_listeners' => $artist->spotify_monthly_listeners,
                'spotify_popularity' => $artist->spotify_popularity,
                'instagram_followers' => $artist->instagram_followers,
                'youtube_subscribers' => $artist->followers_youtube,
                'youtube_total_views' => $artist->youtube_total_views,
                'facebook_followers' => $artist->followers_facebook,
                'tiktok_followers' => $artist->followers_tiktok,
                'upcoming_events' => $upcomingEvents->count(),
                'past_events' => $pastEventsCount,
            ],
            'social' => [
                'instagram' => $artist->instagram_url,
                'youtube' => $artist->youtube_url,
                'spotify' => $artist->spotify_url,
                'facebook' => $artist->facebook_url,
                'tiktok' => $artist->tiktok_url,
                'website' => $artist->website,
            ],
            'external_ids' => [
                'spotify_id' => $artist->spotify_id,
                'youtube_id' => $artist->youtube_id,
            ],
            'youtube_videos' => $artist->youtube_videos ?? [],
            'booking_agency' => $artist->booking_agency ?? null,
            'upcoming_events' => $upcomingEvents,
            'event_groupings' => $eventGroupings,
            'similar_artists' => $similarArtists,
        ];

        return $this->success($data);
    }

    /**
     * Get artist events
     */
    public function events(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $artist = Artist::query()
            ->whereHas('marketplaceClients', fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $client->id))
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$artist) {
            return $this->error('Artist not found', 404);
        }

        $language = $client->language ?? 'ro';

        $query = $artist->events()
            ->where('marketplace_client_id', $client->id)
            ->where('is_published', true)
            ->with(['ticketTypes' => function ($q) {
                $q->where('status', 'active');
            }, 'venue'])
            ->where('is_cancelled', false);

        // Filter: upcoming or past. Upcoming-only filter wants strict
        // status='published' (no archived). Past list includes archived
        // because that's where the system parks finished events.
        if ($request->input('filter') === 'past') {
            $query->whereIn('status', ['published', 'archived'])
                  ->where('event_date', '<', now()->toDateString())
                  ->orderBy('event_date', 'desc');
        } else {
            $query->where('status', 'published')
                  ->where('event_date', '>=', now()->toDateString())
                  ->orderBy('event_date');
        }

        $perPage = min($request->input('per_page', 10), 50);
        $paginator = $query->paginate($perPage);

        $events = collect($paginator->items())->map(function ($event) use ($language) {
            // For child events (multi-day occurrences), inherit ticket data from parent
            $ticketTypes = $event->ticketTypes;
            if ($ticketTypes->isEmpty() && $event->parent_id) {
                $ticketTypes = \App\Models\TicketType::where('event_id', $event->parent_id)
                    ->where('status', 'active')
                    ->get();
            }

            // Calculate min price — check performance overrides for child events
            $matchedPerformance = null;
            if ($event->parent_id && $event->event_date) {
                $childDate = $event->event_date->format('Y-m-d');
                $childTime = $event->start_time ? substr($event->start_time, 0, 5) : null;
                $matchedPerformance = \App\Models\Performance::where('event_id', $event->parent_id)
                    ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                    ->get()
                    ->first(function ($p) use ($childDate, $childTime) {
                        return $p->starts_at->format('Y-m-d') === $childDate
                            && (!$childTime || $p->starts_at->format('H:i') === $childTime);
                    });
            }

            $minPriceCents = $ticketTypes
                ->map(function ($tt) use ($matchedPerformance) {
                    $baseCents = $tt->sale_price_cents ?? $tt->price_cents;
                    if ($matchedPerformance) {
                        $overrideCents = $matchedPerformance->getEffectivePrice($tt);
                        if ($overrideCents !== null) return $overrideCents;
                    }
                    return $baseCents;
                })
                ->filter()
                ->min();
            $minPrice = $minPriceCents ? $minPriceCents / 100 : null;
            $currency = $ticketTypes->first()?->currency ?? 'RON';

            // is_sold_out: use event flag if explicitly set, otherwise compute from active ticket types
            $isSoldOut = (bool) $event->is_sold_out;
            if (!$isSoldOut && $ticketTypes->isNotEmpty()) {
                $active = $ticketTypes->where('status', 'active')->filter(fn ($tt) => $tt->quota_total >= 0);
                $isSoldOut = $active->isNotEmpty() && $active->every(fn ($tt) => $tt->quota_total <= ($tt->quota_sold ?? 0));
            }

            return [
                'id' => $event->id,
                'title' => $event->getTranslation('title', $language),
                'slug' => $event->slug,
                'event_date' => $event->event_date,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'parent_slug' => $event->parent_id ? $event->parent?->slug : null,
                'venue' => $event->venue ? [
                    'name' => $event->venue->getTranslation('name', $language) ?? $event->venue->name,
                    'city' => $event->venue->city,
                    'slug' => $event->venue->slug,
                ] : null,
                'min_price' => $minPrice,
                'currency' => $currency,
                'image' => $event->main_image_url ?? $event->poster_url,
                'hero_image_url' => $event->hero_image_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($event->hero_image_url) : null,
                'poster_url' => $event->poster_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($event->poster_url) : null,
                'is_sold_out' => $isSoldOut,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $events,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Format artist for list display
     */
    protected function formatArtist(Artist $artist, string $language, bool $minimal = false, ?\App\Models\MarketplaceClient $client = null): array
    {
        $data = [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'image' => $artist->main_image_full_url,
            'portrait' => $artist->portrait_full_url,
            'logo' => $artist->logo_full_url,
            'city' => $artist->city,
            'is_verified' => $artist->is_featured,
        ];

        if (!$minimal) {
            $data['genres'] = $artist->artistGenres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->getTranslation('name', $language) ?: ($g->name['en'] ?? $g->name ?? ''),
                'slug' => $g->slug,
            ]);
            $data['types'] = $artist->artistTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->getTranslation('name', $language) ?: ($t->name['en'] ?? $t->name ?? ''),
                'slug' => $t->slug,
            ]);
            $data['stats'] = [
                'spotify_listeners' => $artist->spotify_monthly_listeners,
                'instagram_followers' => $artist->instagram_followers,
                'youtube_subscribers' => $artist->followers_youtube,
                'facebook_followers' => $artist->followers_facebook,
                'tiktok_followers' => $artist->followers_tiktok,
            ];
            // Upcoming events count — same constraints as the rest of the
            // public artist payload: status='published' AND is_published=true
            // AND not cancelled AND in the future, scoped to THIS marketplace.
            $upcomingQuery = $artist->events()
                ->where('event_date', '>=', now()->toDateString())
                ->where('is_cancelled', false)
                ->where('is_published', true)
                ->where('status', 'published');
            if ($client) {
                $upcomingQuery->where('marketplace_client_id', $client->id);
            }
            $data['upcoming_events_count'] = $upcomingQuery->count();
        }

        return $data;
    }
}
