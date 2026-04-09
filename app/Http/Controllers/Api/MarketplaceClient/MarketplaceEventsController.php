<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceEventCategory;
use App\Models\MarketplaceOrganizer;
use App\Models\ServiceOrder;
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
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->with([
                'marketplaceOrganizer:id,name,slug,logo,verified_at,default_commission_mode,commission_rate',
                'marketplaceEventCategory',
                'venue:id,name,city,address',
                'ticketTypes' => function ($query) {
                    $query->select('id', 'event_id', 'price_cents', 'sale_price_cents', 'is_entry_ticket', 'meta', 'status', 'currency')
                        ->where('status', 'active')
                        ->where(function ($q) {
                            $q->where('is_entry_ticket', false)->orWhereNull('is_entry_ticket');
                        });
                },
                'parent.ticketTypes' => fn ($q) => $q->where('status', 'active'),
                'parent.performances' => fn ($q) => $q->where(fn ($q2) => $q2->where('status', 'active')->orWhereNull('status')),
            ]);

        // Filter by time scope: upcoming (default), past, or all
        $timeScope = $request->get('time_scope', 'upcoming');
        if ($timeScope === 'past') {
            $this->applyPastFilter($query);
        } elseif ($timeScope !== 'all' && !$request->has('include_past')) {
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

        // Filter by category (resolve by slug, partial slug, or case-insensitive name)
        if ($request->has('category')) {
            $categoryParam = $request->category;
            $category = $this->resolveCategory($client->id, $categoryParam);

            if ($category) {
                $categoryIds = collect([$category->id]);
                $childIds = $category->children()->pluck('id');
                if ($childIds->isNotEmpty()) {
                    $categoryIds = $categoryIds->merge($childIds);
                }
                $query->whereIn('marketplace_event_category_id', $categoryIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->has('city')) {
            $cityParam = $request->city;

            // Try to resolve city slug to a MarketplaceCity record
            $marketplaceCity = MarketplaceCity::where('marketplace_client_id', $client->id)
                ->where('slug', $cityParam)
                ->first();

            if ($marketplaceCity) {
                // Match events by marketplace_city_id OR by venue city name
                $cityName = $marketplaceCity->getTranslation('name', $language)
                    ?: (is_array($marketplaceCity->name)
                        ? ($marketplaceCity->name['ro'] ?? $marketplaceCity->name['en'] ?? reset($marketplaceCity->name))
                        : $marketplaceCity->name);

                $query->where(function ($q) use ($marketplaceCity, $cityName) {
                    $q->where('marketplace_city_id', $marketplaceCity->id)
                        ->orWhereHas('venue', function ($vq) use ($cityName) {
                            $vq->where('city', 'like', "%{$cityName}%");
                        });
                });
            } else {
                // Fallback: try matching by name (replace hyphens with spaces for slug-like input)
                $cityName = str_replace('-', ' ', $cityParam);
                $query->where(function ($q) use ($cityParam, $cityName) {
                    $q->whereHas('marketplaceCity', function ($mq) use ($cityParam, $cityName) {
                        $mq->where('name->ro', 'like', "%{$cityName}%")
                            ->orWhere('name->en', 'like', "%{$cityName}%")
                            ->orWhere('slug', $cityParam);
                    })->orWhereHas('venue', function ($vq) use ($cityParam, $cityName) {
                        $vq->where('city', 'like', "%{$cityParam}%")
                            ->orWhere('city', 'like', "%{$cityName}%");
                    });
                });
            }
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

        // Filter by named date range (accepts both 'date' and 'date_filter' params)
        // Normalizes: this_week→week, this_month→month, next_month→next-month
        $dateParam = $request->get('date_filter') ?? $request->get('date');
        if ($dateParam && !$request->has('from_date')) {
            // Normalize underscored variants from category/genre pages
            $dateParam = match ($dateParam) {
                'this_week' => 'week',
                'this_month' => 'month',
                'next_month' => 'next-month',
                default => $dateParam,
            };

            $today = now()->startOfDay();
            switch ($dateParam) {
                case 'today':
                    $query->whereDate('event_date', $today);
                    break;
                case 'tomorrow':
                    $query->whereDate('event_date', $today->copy()->addDay());
                    break;
                case 'weekend':
                    $saturday = $today->copy()->next(\Carbon\Carbon::SATURDAY);
                    if ($today->isSaturday()) $saturday = $today->copy();
                    if ($today->isSunday()) $saturday = $today->copy()->subDay();
                    $sunday = $saturday->copy()->addDay();
                    $query->whereBetween('event_date', [$saturday->toDateString(), $sunday->toDateString()]);
                    break;
                case 'week':
                    $query->whereBetween('event_date', [$today->toDateString(), $today->copy()->endOfWeek()->toDateString()]);
                    break;
                case 'month':
                    $query->whereBetween('event_date', [$today->toDateString(), $today->copy()->endOfMonth()->toDateString()]);
                    break;
                case 'next-month':
                    $nextMonth = $today->copy()->addMonth()->startOfMonth();
                    $query->whereBetween('event_date', [$nextMonth->toDateString(), $nextMonth->copy()->endOfMonth()->toDateString()]);
                    break;
            }
        }

        // Filter by promoted events:
        // 1. Active paid ServiceOrder featuring, OR
        // 2. Manual is_promoted flag (with optional promoted_until date)
        if ($request->boolean('promoted')) {
            $today = now()->toDateString();
            $query->where(function ($q) use ($today) {
                // Paid promotion via ServiceOrder
                $q->whereHas('activeFeaturingOrders', function ($sq) use ($today) {
                    $sq->where('service_type', ServiceOrder::TYPE_FEATURING)
                        ->where('status', ServiceOrder::STATUS_ACTIVE)
                        ->where('service_start_date', '<=', $today)
                        ->where('service_end_date', '>=', $today);
                })
                // Manual promotion via admin toggle
                ->orWhere(function ($sq) use ($today) {
                    $sq->where('is_promoted', true)
                        ->where(function ($sq2) use ($today) {
                            $sq2->whereNull('promoted_until')
                                ->orWhere('promoted_until', '>=', $today);
                        });
                });
            });
        }

        // Filter by recommended (homepage or general featured, excluding paid promotions for variety)
        if ($request->boolean('recommended')) {
            $query->where(function ($q) {
                $q->where('is_homepage_featured', true)
                    ->orWhere('is_general_featured', true);
            });
        }

        // Filter by price range
        if ($request->has('price')) {
            $priceFilter = $request->price;
            if ($priceFilter === 'free') {
                $query->whereHas('ticketTypes', function ($q) {
                    $q->where('status', 'active')
                        ->where(function ($sq) {
                            $sq->where('price_cents', 0)
                                ->orWhereNull('price_cents');
                        });
                });
            } elseif (preg_match('/^(\d+)-(\d+)$/', $priceFilter, $matches)) {
                $minCents = (int) $matches[1] * 100;
                $maxCents = (int) $matches[2] * 100;
                $query->where(function ($q) use ($minCents, $maxCents) {
                    // Own ticket types match
                    $q->whereHas('ticketTypes', function ($tq) use ($minCents, $maxCents) {
                        $tq->where('status', 'active')
                            ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) >= ?', [$minCents])
                            ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) <= ?', [$maxCents]);
                    })
                    // OR child event whose parent has matching ticket types
                    ->orWhere(function ($q2) use ($minCents, $maxCents) {
                        $q2->whereNotNull('parent_id')
                            ->whereExists(function ($sub) use ($minCents, $maxCents) {
                                $sub->selectRaw('1')
                                    ->from('ticket_types')
                                    ->whereColumn('ticket_types.event_id', 'events.parent_id')
                                    ->where('ticket_types.status', 'active')
                                    ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) >= ?', [$minCents])
                                    ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) <= ?', [$maxCents]);
                            });
                    });
                });
            } elseif (preg_match('/^(\d+)\+$/', $priceFilter, $matches)) {
                $minCents = (int) $matches[1] * 100;
                $query->where(function ($q) use ($minCents) {
                    $q->whereHas('ticketTypes', function ($tq) use ($minCents) {
                        $tq->where('status', 'active')
                            ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) >= ?', [$minCents]);
                    })
                    ->orWhere(function ($q2) use ($minCents) {
                        $q2->whereNotNull('parent_id')
                            ->whereExists(function ($sub) use ($minCents) {
                                $sub->selectRaw('1')
                                    ->from('ticket_types')
                                    ->whereColumn('ticket_types.event_id', 'events.parent_id')
                                    ->where('ticket_types.status', 'active')
                                    ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) >= ?', [$minCents]);
                            });
                    });
                });
            }
        }

        // Filter by min_price / max_price (sent by category/events page filters)
        if (!$request->has('price') && ($request->has('min_price') || $request->has('max_price'))) {
            $minCents = $request->has('min_price') ? (int) $request->min_price * 100 : 0;
            $maxCents = $request->has('max_price') ? (int) $request->max_price * 100 : null;

            $query->where(function ($q) use ($minCents, $maxCents) {
                $priceFilter = function ($tq) use ($minCents, $maxCents) {
                    $tq->where('status', 'active')
                        ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) >= ?', [$minCents]);
                    if ($maxCents !== null) {
                        $tq->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) <= ?', [$maxCents]);
                    }
                };

                $q->whereHas('ticketTypes', $priceFilter)
                    ->orWhere(function ($q2) use ($minCents, $maxCents) {
                        $q2->whereNotNull('parent_id')
                            ->whereExists(function ($sub) use ($minCents, $maxCents) {
                                $sub->selectRaw('1')
                                    ->from('ticket_types')
                                    ->whereColumn('ticket_types.event_id', 'events.parent_id')
                                    ->where('ticket_types.status', 'active')
                                    ->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) >= ?', [$minCents]);
                                if ($maxCents !== null) {
                                    $sub->whereRaw('COALESCE(NULLIF(sale_price_cents, 0), price_cents) <= ?', [$maxCents]);
                                }
                            });
                    });
            });
        }

        // Featured only
        if ($request->boolean('featured_only') || $request->boolean('featured')) {
            $query->where('is_homepage_featured', true)
                ->orWhere('is_general_featured', true);
        }

        // Sorting — for past events, default to newest-first (date_desc)
        $defaultSort = ($timeScope === 'past') ? 'date_desc' : 'date_asc';
        $sort = $request->get('sort', $defaultSort);
        // JS sends 'date' — normalize to the appropriate date sort
        if ($sort === 'date') {
            $sort = $defaultSort;
        }
        switch ($sort) {
            case 'date_desc':
                $query->orderBy('event_date', 'desc')->orderBy('start_time', 'desc');
                break;
            case 'price_asc':
                $query->orderByRaw('COALESCE((SELECT MIN(CASE WHEN sale_price_cents > 0 THEN sale_price_cents ELSE price_cents END) FROM ticket_types WHERE ticket_types.event_id = events.id AND ticket_types.status = ?), 999999999) ASC', ['active']);
                break;
            case 'price_desc':
                $query->orderByRaw('COALESCE((SELECT MIN(CASE WHEN sale_price_cents > 0 THEN sale_price_cents ELSE price_cents END) FROM ticket_types WHERE ticket_types.event_id = events.id AND ticket_types.status = ?), 0) DESC', ['active']);
                break;
            case 'name_asc':
                $query->orderBy("title->{$language}", 'asc');
                break;
            case 'name_desc':
                $query->orderBy("title->{$language}", 'desc');
                break;
            case 'popularity':
                $query->orderBy('views_count', 'desc')->orderBy('event_date', 'asc');
                break;
            case 'newest':
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
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        $this->applyUpcomingFilter($query);

        // Featured type filter: homepage, general, category, city, or any
        $featuredType = $request->get('type', 'any');
        if ($featuredType === 'homepage') {
            $query->where('is_homepage_featured', true);
        } elseif ($featuredType === 'general') {
            $query->where('is_general_featured', true);
        } elseif ($featuredType === 'category') {
            $query->where('is_category_featured', true);
        } elseif ($featuredType === 'city') {
            $query->where('is_city_featured', true);
            // Optionally filter by a specific city slug/name
            if ($request->has('city')) {
                $city = $request->get('city');
                $query->whereHas('venue', fn ($q) => $q->where('city', 'like', "%{$city}%"));
            }
        } else {
            $query->where(function ($q) {
                $q->where('is_homepage_featured', true)
                    ->orWhere('is_general_featured', true)
                    ->orWhere('is_category_featured', true)
                    ->orWhere('is_city_featured', true);
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
                    $query->select('id', 'event_id', 'price_cents', 'sale_price_cents', 'is_entry_ticket', 'meta', 'status', 'currency')
                        ->where('status', 'active')
                        ->where(function ($q) {
                            $q->where('is_entry_ticket', false)->orWhereNull('is_entry_ticket');
                        });
                },
                'parent.ticketTypes' => fn ($q) => $q->where('status', 'active'),
                'parent.performances' => fn ($q) => $q->where(fn ($q2) => $q2->where('status', 'active')->orWhereNull('status')),
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

        // Only show published events unless preview mode is enabled
        if (!$request->boolean('preview')) {
            $query->where('is_published', true)
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                });
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
            'ticketTypes.seatingRows.section',
            'artists',
        ])->first();

        if (!$event) {
            // Check if this is a child event (ended performance link) — try with parent_id first
            $endedEvent = Event::where('marketplace_client_id', $client->id)
                ->where(is_numeric($identifier) ? 'id' : 'slug', $identifier)
                ->first();

            // If not found at all, try to find parent by slug pattern (slug ends with -XXXX)
            $parentEvt = null;
            if ($endedEvent && $endedEvent->parent_id) {
                $parentEvt = Event::where('id', $endedEvent->parent_id)
                    ->where('is_published', true)
                    ->first();
            } elseif (!$endedEvent && !is_numeric($identifier)) {
                // Try to match parent slug: remove trailing -XXXX from slug
                if (preg_match('/^(.+)-(\d+)$/', $identifier, $m)) {
                    $parentEvt = Event::where('marketplace_client_id', $client->id)
                        ->where('slug', $m[1])
                        ->where('is_published', true)
                        ->first();
                }
            }

            if ($parentEvt) {
                $upcomingPerfs = $parentEvt->performances()
                    ->where(function ($q) {
                        $q->where('status', 'active')->orWhereNull('status');
                    })
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at')
                    ->get();

                return response()->json([
                    'success' => false,
                    'message' => 'Această reprezentație s-a încheiat',
                    'ended' => true,
                    'parent_slug' => $parentEvt->slug,
                    'parent_title' => $parentEvt->getTranslation('title', $language) ?: $parentEvt->getTranslation('title', 'en'),
                    'upcoming_performances' => $upcomingPerfs->map(fn ($p) => [
                        'id' => $p->id,
                        'date' => $p->starts_at->format('Y-m-d'),
                        'start_time' => $p->starts_at->format('H:i'),
                        'end_time' => $p->ends_at?->format('H:i'),
                        'label' => $p->label,
                    ])->toArray(),
                ], 410);
            }

            return $this->error('Event not found', 404);
        }

        // Child events (multi-day occurrences) inherit ticket types + performances from parent
        $parentEvent = null;
        $selectedPerformanceId = null;
        if ($event->parent_id && $event->ticketTypes->isEmpty()) {
            $parentEvent = Event::with([
                'ticketTypes.seatingSections',
                'ticketTypes.seatingRows.section',
                'performances' => fn ($q) => $q->where(fn ($qq) => $qq->where('status', 'active')->orWhereNull('status'))->orderBy('starts_at'),
            ])->find($event->parent_id);

            if ($parentEvent) {
                // Use parent's ticket types for this child event
                $event->setRelation('ticketTypes', $parentEvent->ticketTypes);

                // Match child's date/time to a specific performance for auto-selection
                if ($event->event_date && $parentEvent->relationLoaded('performances')) {
                    $childDate = $event->event_date->format('Y-m-d');
                    $childTime = $event->start_time ? substr($event->start_time, 0, 5) : null;
                    $matchedPerf = $parentEvent->performances->first(function ($p) use ($childDate, $childTime) {
                        $perfDate = $p->starts_at->format('Y-m-d');
                        $perfTime = $p->starts_at->format('H:i');
                        return $perfDate === $childDate && (!$childTime || $perfTime === $childTime);
                    });
                    $selectedPerformanceId = $matchedPerf?->id;
                }
            }
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
                'poster_url' => $posterImage,
                'hero_image_url' => $coverImage,
                'cover_image' => $coverImage,
                'cover_image_url' => $coverImage ?? $posterImage,
                'category' => $event->marketplaceEventCategory?->getTranslation('name', $language),
                // Schedule info
                'duration_mode' => $parentEvent ? ($parentEvent->duration_mode ?? 'multi_day') : ($event->duration_mode ?? 'single_day'),
                'selected_performance_id' => $selectedPerformanceId,
                // Single day fields
                'starts_at' => ($event->event_date?->format('Y-m-d') ?? $event->range_start_date?->format('Y-m-d')) . 'T' . ($event->start_time ?? '00:00:00'),
                'ends_at' => $event->end_time ? $event->event_date?->format('Y-m-d') . 'T' . $event->end_time : null,
                'doors_open_at' => $event->door_time ? $event->event_date?->format('Y-m-d') . 'T' . $event->door_time : null,
                // Range/festival fields
                'range_start_date' => $event->range_start_date?->format('Y-m-d'),
                'range_end_date' => $event->range_end_date?->format('Y-m-d'),
                'range_start_time' => $event->range_start_time,
                'range_end_time' => $event->range_end_time,
                // Multi-day slots
                'multi_slots' => $parentEvent ? $parentEvent->multi_slots : $event->multi_slots,
                // Keep flat venue fields for backwards compatibility
                'venue_name' => $venue?->getTranslation('name', $language),
                'venue_address' => $venue?->address ?? $event->address,
                'venue_city' => $venue?->city,
                'capacity' => $venue?->capacity,
                'is_featured' => $event->is_homepage_featured || $event->is_general_featured,
                'is_homepage_featured'  => (bool) $event->is_homepage_featured,
                'is_general_featured'   => (bool) $event->is_general_featured,
                'is_category_featured'  => (bool) $event->is_category_featured,
                'is_city_featured'      => (bool) ($event->is_city_featured ?? false),
                'target_price' => $targetPrice,
                'views_count' => (int) ($event->views_count ?? 0),
                'interested_count' => (int) ($event->interested_count ?? 0),
                // Event status flags
                'is_password_protected' => !empty($event->access_password),
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
                'ticket_terms' => (function () use ($event, $venue, $language) {
                    $terms = $event->getTranslation('ticket_terms', $language)
                        ?? $event->getTranslation('ticket_terms', 'ro')
                        ?? $event->getTranslation('ticket_terms', 'en')
                        ?? null;
                    $venueConditions = $venue?->venue_conditions;
                    if ($venueConditions && trim(strip_tags($venueConditions))) {
                        $venueName = is_array($venue->name) ? ($venue->name[$language] ?? $venue->name['ro'] ?? reset($venue->name) ?? '') : ($venue->name ?? '');
                        $conditionsBlock = '<div class="mt-2 py-2"><strong>Condiții ' . e($venueName) . '</strong>' . $venueConditions . '</div>';
                        $terms = $terms ? $terms . $conditionsBlock : $conditionsBlock;
                    }
                    return $terms;
                })(),
            ],
            'performances' => ($parentEvent ?? $event)->performances()
                ->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                })
                ->where('starts_at', '>=', now()->subHours(2)) // Hide past performances (with 2h buffer for ongoing shows)
                ->orderBy('starts_at')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'date' => $p->starts_at->format('Y-m-d'),
                    'start_time' => $p->starts_at->format('H:i'),
                    'end_time' => $p->ends_at?->format('H:i'),
                    'door_time' => $p->door_time,
                    'label' => $p->label,
                    'status' => $p->status ?? 'active',
                    'ticket_overrides' => collect($p->ticket_overrides ?? [])
                        ->mapWithKeys(fn ($o) => [
                            $o['ticket_type_id'] => [
                                'price' => ($o['price_cents'] ?? 0) / 100,
                                'quota' => $o['quota'] ?? null,
                            ],
                        ])
                        ->toArray(),
                ])
                ->toArray(),
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
            'ticket_types' => $event->ticketTypes->sortBy('sort_order')->filter(fn ($tt) => $tt->status === 'active' && !$tt->is_entry_ticket && !($tt->meta['is_invitation'] ?? false))->map(function ($tt) use ($language, $targetPrice, $commissionMode, $commissionRate) {
                // Debug: log ticket type color and seating row data
                \Log::info('[MarketplaceEventsController] TicketType #' . $tt->id . ' "' . $tt->name . '"'
                    . ' | color=' . var_export($tt->color, true)
                    . ' | seatingRows loaded=' . ($tt->relationLoaded('seatingRows') ? 'yes' : 'no')
                    . ' | seatingRows count=' . ($tt->relationLoaded('seatingRows') ? $tt->seatingRows->count() : 'N/A')
                    . ' | seatingSections count=' . ($tt->relationLoaded('seatingSections') ? $tt->seatingSections->count() : 'N/A')
                );

                $available = ($tt->quota_total < 0 ? PHP_INT_MAX : max(0, $tt->quota_total - ($tt->quota_sold ?? 0)));
                $basePrice = ($tt->sale_price_cents ?? $tt->price_cents) / 100;

                // Calculate original_price:
                // 1. If event has target_price and it's > display price, use target_price (highest priority)
                // 2. If ticket has sale_price_cents set and it's < price_cents, use price_cents as original (promotional discount)
                $originalPrice = null;
                if ($targetPrice && $targetPrice > $basePrice) {
                    $originalPrice = $targetPrice;
                } elseif ($tt->sale_price_cents && $tt->price_cents && $tt->sale_price_cents < $tt->price_cents) {
                    $originalPrice = $tt->price_cents / 100;
                }

                // Get seating sections — derive from assigned rows or direct section assignments
                $seatingSections = [];
                if ($tt->relationLoaded('seatingRows') && $tt->seatingRows->isNotEmpty()) {
                    // Derive sections from assigned rows (new model)
                    $seatingSections = $tt->seatingRows
                        ->filter(fn ($r) => $r->relationLoaded('section') && $r->section)
                        ->pluck('section')
                        ->unique('id')
                        ->map(fn ($s) => [
                            'id' => $s->id,
                            'name' => $s->name,
                            'color' => $s->color_hex,
                            'color_hex' => $s->color_hex,
                            'seat_color' => $s->seat_color,
                        ])->values()->toArray();
                } elseif ($tt->relationLoaded('seatingSections') && $tt->seatingSections->isNotEmpty()) {
                    // Fallback: direct section assignments (legacy)
                    $seatingSections = $tt->seatingSections->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'color' => $s->color_hex,
                        'color_hex' => $s->color_hex,
                        'seat_color' => $s->seat_color,
                    ])->values()->toArray();
                }

                // Get effective commission for this ticket type
                // If ticket has custom commission, use it; otherwise use event/organizer defaults
                $ticketCommission = null;
                if ($tt->commission_type) {
                    $ticketCommission = [
                        'type' => $tt->commission_type,
                        'rate' => (float) ($tt->commission_rate ?? 0),
                        'fixed' => (float) ($tt->commission_fixed ?? 0),
                        'mode' => $tt->commission_mode ?? $commissionMode,
                    ];
                }

                // Build seating_rows for row-level ticket type mapping
                $seatingRows = [];
                if ($tt->relationLoaded('seatingRows') && $tt->seatingRows->isNotEmpty()) {
                    $seatingRows = $tt->seatingRows->map(fn ($r) => [
                        'id' => $r->id,
                        'label' => $r->label,
                        'section_id' => $r->section_id,
                    ])->values()->toArray();
                }

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $basePrice,
                    'original_price' => $originalPrice ? (float) $originalPrice : null,
                    'currency' => $tt->currency ?? 'RON',
                    'color' => $tt->color ?? null,
                    'available' => $available,
                    'min_per_order' => $tt->min_per_order ?? 1,
                    'max_per_order' => $tt->max_per_order ?? 10,
                    'multiplier' => $tt->multiplier ?? 1,
                    'status' => $tt->status,
                    'is_sold_out' => $available <= 0,
                    'has_seating' => !empty($seatingSections),
                    'seating_sections' => $seatingSections,
                    'seating_rows' => $seatingRows,
                    // Per-ticket commission (null = use event defaults)
                    'commission' => $ticketCommission,
                    'is_refundable' => (bool) ($tt->is_refundable ?? false),
                    'ticket_group' => $tt->ticket_group,
                    'perks' => $tt->perks ?? [],
                ];
            })->values(),
            'enable_ticket_groups' => (bool) $event->enable_ticket_groups,
            'enable_ticket_perks' => (bool) $event->enable_ticket_perks,
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
                    'sort_order' => $artist->pivot->sort_order ?? 0,
                    'is_headliner' => (bool) ($artist->pivot->is_headliner ?? false),
                    'is_co_headliner' => (bool) ($artist->pivot->is_co_headliner ?? false),
                ];
            })->values(),
            'commission_mode' => $commissionMode,
            'commission_rate' => (float) $commissionRate,
            'taxes' => $taxes,
            // Seating layout if venue has one (includes event_seating_id for seat holds)
            'seating_layout' => $this->getSeatingLayout($venue, $event),
            // Custom related events (array at root level for frontend)
            'custom_related_events' => $this->getCustomRelatedEvents($event, $language, $client),
            // Tour
            'tour_name' => $event->tour_id ? \App\Models\Tour::find($event->tour_id)?->name : null,
            'tour_type' => $event->tour_id ? (\App\Models\Tour::find($event->tour_id)?->type ?? 'serie_evenimente') : null,
            'tour_events' => $event->tour_id
                ? Event::where('tour_id', $event->tour_id)
                    ->where('id', '!=', $event->id)
                    ->where('is_published', true)
                    ->with('venue:id,name,city')
                    ->orderByRaw("COALESCE(event_date, DATE(starts_at)) ASC")
                    ->get()
                    ->map(function ($te) use ($language) {
                        return [
                            'id' => $te->id,
                            'slug' => $te->slug,
                            'name' => $te->getTranslation('title', $language) ?? $te->getTranslation('title', 'ro') ?? $te->name ?? '',
                            'event_date' => $te->event_date?->format('Y-m-d'),
                            'start_time' => $te->start_time,
                            'city' => $te->venue?->city ?? null,
                            'venue_name' => $te->venue ? (is_array($te->venue->name) ? ($te->venue->name[$language] ?? $te->venue->name['ro'] ?? $te->venue->name['en'] ?? null) : $te->venue->name) : null,
                            'image_url' => $te->poster_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($te->poster_url) : ($te->hero_image_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($te->hero_image_url) : null),
                        ];
                    })
                : [],
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
                    $query->select('id', 'event_id', 'price_cents', 'sale_price_cents', 'is_entry_ticket')
                        ->where('status', 'active')
                        ->where(function ($q) {
                            $q->where('is_entry_ticket', false)->orWhereNull('is_entry_ticket');
                        });
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
            ->where(function ($q) {
                $q->where('is_entry_ticket', false)->orWhereNull('is_entry_ticket');
            })
            ->get()
            ->filter(fn ($tt) => !($tt->meta['is_invitation'] ?? false))
            ->map(function ($tt) {
                $available = ($tt->quota_total < 0 ? PHP_INT_MAX : max(0, $tt->quota_total - ($tt->quota_sold ?? 0)));
                $displayPrice = ($tt->sale_price_cents ?? $tt->price_cents) / 100;

                // Per-ticket commission if set
                $ticketCommission = null;
                if ($tt->commission_type) {
                    $ticketCommission = [
                        'type' => $tt->commission_type,
                        'rate' => (float) ($tt->commission_rate ?? 0),
                        'fixed' => (float) ($tt->commission_fixed ?? 0),
                        'mode' => $tt->commission_mode,
                    ];
                }

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => (float) $displayPrice,
                    'available' => $available,
                    'min_per_order' => $tt->min_per_order ?? 1,
                    'max_per_order' => $tt->max_per_order ?? 10,
                    'multiplier' => $tt->multiplier ?? 1,
                    'status' => $available <= 0 ? 'sold_out' : 'available',
                    'commission' => $ticketCommission,
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

        // Get categories that have published events
        $catQuery = Event::where('marketplace_client_id', $client->id)
            ->where('is_published', true)
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

        // Normalize Romanian diacritics for grouping
        $normalizeCityKey = function (string $city): string {
            $city = mb_strtolower(trim($city));
            return strtr($city, [
                'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            ]);
        };

        $cities = $query->with('venue:id,city')
            ->get()
            ->filter(fn ($e) => $e->venue?->city)
            ->groupBy(fn ($e) => $normalizeCityKey($e->venue->city))
            ->map(function ($group) {
                // Use the cleanest city name for display (trimmed, prefer one with diacritics)
                $bestName = $group->sortByDesc(fn ($e) => mb_strlen($e->venue->city) - strlen($e->venue->city))->first()->venue->city;
                return [
                    'name' => trim($bestName),
                    'event_count' => $group->count(),
                ];
            })
            ->sortByDesc('event_count')
            ->values();

        return $this->success(['cities' => $cities]);
    }

    /**
     * Verify event access password
     */
    public function verifyPassword(Request $request, $identifier): JsonResponse
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

        if (empty($event->access_password)) {
            return $this->success(['valid' => true]);
        }

        $password = $request->input('password', '');

        if ($password === $event->access_password) {
            return $this->success(['valid' => true]);
        }

        return $this->error('Parolă incorectă', 403);
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
        // Skip 0-price tickets when paid tickets also exist
        $minPrice = null;
        if ($event->relationLoaded('ticketTypes') && $event->ticketTypes->isNotEmpty()) {
            // Exclude invitation ticket types from price calculations
            $publicTickets = $event->ticketTypes->filter(fn ($tt) => !($tt->meta['is_invitation'] ?? false));
            $allPrices = $publicTickets->map(function ($ticket) {
                if ($ticket->sale_price_cents !== null && $ticket->sale_price_cents > 0) {
                    return $ticket->sale_price_cents;
                }
                return $ticket->price_cents ?? 0;
            });

            $paidPrices = $allPrices->filter(fn ($p) => $p > 0);
            $minPriceCents = $paidPrices->isNotEmpty() ? $paidPrices->min() : $allPrices->min();

            if ($minPriceCents !== null) {
                $minPrice = $minPriceCents / 100;
            }
        }
        if ($minPrice === null && $event->min_price !== null) {
            $minPrice = $event->min_price;
        }

        // Child events (multi-day occurrences) inherit price + availability from parent
        if ($minPrice === null && $event->parent_id) {
            $parent = $event->relationLoaded('parent') ? $event->parent : Event::with(['ticketTypes' => fn ($q) => $q->where('status', 'active')])->find($event->parent_id);
            if ($parent && $parent->ticketTypes->isNotEmpty()) {
                // Match child's date+time to a performance for overrides
                $matchedPerf = null;
                if ($event->event_date) {
                    $childDate = $event->event_date->format('Y-m-d');
                    $childTime = $event->start_time ? substr($event->start_time, 0, 5) : null;
                    $performances = $parent->relationLoaded('performances')
                        ? $parent->performances
                        : \App\Models\Performance::where('event_id', $parent->id)
                            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                            ->get();
                    $matchedPerf = $performances->first(fn ($p) => $p->starts_at->format('Y-m-d') === $childDate
                            && (!$childTime || $p->starts_at->format('H:i') === $childTime));
                }
                $parentPublicTts = $parent->ticketTypes->filter(fn ($tt) => !($tt->meta['is_invitation'] ?? false));
                $parentPrices = $parentPublicTts->map(function ($tt) use ($matchedPerf) {
                    if ($matchedPerf) {
                        $override = $matchedPerf->getEffectivePrice($tt);
                        if ($override !== null) return $override;
                    }
                    return $tt->sale_price_cents ?: ($tt->price_cents ?? 0);
                })->filter(fn ($p) => $p > 0);
                $minPrice = $parentPrices->isNotEmpty() ? $parentPrices->min() / 100 : null;
            }
        }

        // For multi-day parent events, check if any performance has lower prices
        if ($minPrice !== null && $event->duration_mode === 'multi_day' && !$event->parent_id) {
            $performances = $event->performances()
                ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                ->whereNotNull('ticket_overrides')
                ->get();
            if ($performances->isNotEmpty()) {
                $publicTickets = $event->ticketTypes->filter(fn ($tt) => !($tt->meta['is_invitation'] ?? false));
                foreach ($performances as $perf) {
                    foreach ($publicTickets as $tt) {
                        $overrideCents = $perf->getEffectivePrice($tt);
                        if ($overrideCents !== null && $overrideCents > 0) {
                            $overridePrice = $overrideCents / 100;
                            if ($overridePrice < $minPrice) {
                                $minPrice = $overridePrice;
                            }
                        }
                    }
                }
            }
        }

        return [
            'id' => $event->id,
            'name' => $event->getTranslation('title', $language),
            'slug' => $event->slug,
            'short_description' => $event->getTranslation('short_description', $language),
            'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
            'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
            'hero_image_url' => $event->hero_image_url ? Storage::disk('public')->url($event->hero_image_url) : null,
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->getTranslation('name', $language),
                'slug' => $category->slug,
            ] : null,
            'starts_at' => ($event->event_date?->format('Y-m-d') ?? $event->range_start_date?->format('Y-m-d')) . 'T' . ($event->start_time ?? '00:00:00'),
            'duration_mode' => $event->duration_mode ?? 'single_day',
            'range_start_date' => $event->range_start_date?->format('Y-m-d'),
            'range_end_date' => $event->range_end_date?->format('Y-m-d'),
            'venue_name' => $venue?->getTranslation('name', $language),
            'venue_city' => $venue?->city,
            'is_featured' => $event->is_homepage_featured || $event->is_general_featured,
            'has_paid_promotion' => $this->hasActivePaidPromotion($event),
            'is_password_protected' => !empty($event->access_password),
            'is_sold_out' => (bool) ($event->is_sold_out ?? ($event->parent_id ? ($event->parent?->is_sold_out ?? false) : false)),
            'is_cancelled' => (bool) ($event->is_cancelled ?? false),
            'is_postponed' => (bool) ($event->is_postponed ?? false),
            'postponed_date' => $event->is_postponed && $event->postponed_date ? $event->postponed_date->format('Y-m-d') : null,
            'price_from' => $minPrice,
            'ticket_types_count' => $event->relationLoaded('ticketTypes') && $event->ticketTypes->isNotEmpty()
                ? $event->ticketTypes->filter(fn ($tt) => !($tt->meta['is_invitation'] ?? false))->count()
                : ($event->parent_id ? ($event->parent?->ticketTypes()->where('status', 'active')->count() ?? 0) : null),
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
     * Get all event types (global taxonomy, not marketplace-specific)
     */
    public function eventTypes(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $types = \App\Models\EventType::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get()
            ->map(function ($type) use ($language) {
                $item = [
                    'id' => $type->id,
                    'name' => $type->getTranslation('name', $language),
                    'slug' => $type->slug,
                ];
                if ($type->children->isNotEmpty()) {
                    $item['children'] = $type->children->map(fn ($child) => [
                        'id' => $child->id,
                        'name' => $child->getTranslation('name', $language),
                        'slug' => $child->slug,
                    ])->values()->all();
                }
                return $item;
            });

        return $this->success([
            'event_types' => $types,
        ]);
    }

    /**
     * Get event genres with event counts
     * Supports ?event_type_ids=1,2,3 to filter genres by allowed event types
     */
    public function genres(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = \App\Models\EventGenre::query();

        // Filter by event type IDs if provided (uses event_type_event_genre pivot)
        $eventTypeIds = $request->get('event_type_ids');
        if ($eventTypeIds) {
            $ids = is_array($eventTypeIds) ? $eventTypeIds : explode(',', $eventTypeIds);
            $ids = array_filter(array_map('intval', $ids));
            if (!empty($ids)) {
                $query->whereHas('allowedEventTypes', function ($q) use ($ids) {
                    $q->whereIn('event_types.id', $ids);
                });
            }
        }

        // Filter by category slug — only show genres that have events in this category
        $categorySlug = $request->get('category');
        $categoryId = null;
        if ($categorySlug) {
            $category = \App\Models\MarketplaceEventCategory::where('marketplace_client_id', $client->id)
                ->where('slug', $categorySlug)
                ->first();
            $categoryId = $category?->id;
        }

        // Build event filter closure (reused for whereHas and withCount)
        $eventFilter = function ($q) use ($client, $categoryId) {
            $q->where('marketplace_client_id', $client->id)
                ->where(function ($q2) {
                    $q2->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                });
            if ($categoryId) {
                $q->where('marketplace_event_category_id', $categoryId);
            }
            $this->applyUpcomingFilter($q);
        };

        // Get all event genres that have events in this marketplace
        $genres = $query
            ->whereHas('events', $eventFilter)
            ->withCount(['events' => $eventFilter])
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
                $rowData = [
                    'id' => $row->id,
                    'label' => $row->label,
                    'seats' => $seats,
                ];

                // Include table metadata if this row is a table
                $rowMeta = $row->metadata ?? [];
                if (!empty($rowMeta['is_table'])) {
                    $rowData['is_table'] = true;
                    $rowData['table_type'] = $rowMeta['table_type'] ?? 'round';
                    $rowData['center_x'] = (float) ($rowMeta['center_x'] ?? 0);
                    $rowData['center_y'] = (float) ($rowMeta['center_y'] ?? 0);
                    if (($rowMeta['table_type'] ?? 'round') === 'round') {
                        $rowData['radius'] = (float) ($rowMeta['radius'] ?? 30);
                    } else {
                        $rowData['table_width'] = (float) ($rowMeta['width'] ?? 80);
                        $rowData['table_height'] = (float) ($rowMeta['height'] ?? 30);
                    }
                }

                $rows[] = $rowData;
            }
            $sectionData = [
                'id' => $section->id,
                'name' => $section->name,
                'section_type' => $section->section_type ?? 'standard',
                'color' => $section->color_hex,
                'color_hex' => $section->color_hex,
                'seat_color' => $section->seat_color,
                'background_color' => $section->background_color,
                'corner_radius' => $section->corner_radius,
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
     * Resolve a category from slug, partial slug, or case-insensitive name.
     * Categories table is small, so loading all for a marketplace is fine.
     */
    private function resolveCategory(int $marketplaceClientId, string $param): ?MarketplaceEventCategory
    {
        $categories = MarketplaceEventCategory::where('marketplace_client_id', $marketplaceClientId)->get();
        $paramLower = mb_strtolower($param);

        // 1. Exact slug match
        return $categories->first(fn ($c) => $c->slug === $param)
            // 2. Partial slug match (e.g. "concerte" matches "bilete-concerte")
            ?? $categories->first(fn ($c) => str_contains($c->slug, $paramLower))
            // 3. Case-insensitive name match (ro or en)
            ?? $categories->first(function ($c) use ($paramLower) {
                return mb_strtolower($c->getTranslation('name', 'ro')) === $paramLower
                    || mb_strtolower($c->getTranslation('name', 'en')) === $paramLower;
            });
    }

    /**
     * Apply upcoming event filter - excludes events that have already started/ended
     * based on their duration mode and respective date+time fields.
     */
    /**
     * Filter to only past events (inverse of applyUpcomingFilter)
     */
    private function applyPastFilter($query): void
    {
        $now = now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        $query->where(function ($q) use ($today, $currentTime) {
            // Range events: ended when range_end_date + range_end_time has passed
            $q->where(function ($q2) use ($today, $currentTime) {
                $q2->where('duration_mode', 'range')
                    ->where(function ($q3) use ($today, $currentTime) {
                        $q3->where('range_end_date', '<', $today)
                            ->orWhere(function ($q4) use ($today, $currentTime) {
                                $q4->where('range_end_date', $today)
                                    ->where(function ($q5) use ($currentTime) {
                                        $q5->whereNotNull('range_end_time')
                                            ->where('range_end_time', '<=', $currentTime);
                                    });
                            });
                    });
            })
            // Single day events: past when event_date < today, or event_date = today and start_time passed
            ->orWhere(function ($q2) use ($today, $currentTime) {
                $q2->where(function ($q3) {
                        $q3->where('duration_mode', 'single_day')
                            ->orWhereNull('duration_mode');
                    })
                    ->where(function ($q3) use ($today, $currentTime) {
                        $q3->where('event_date', '<', $today)
                            ->orWhere(function ($q4) use ($today, $currentTime) {
                                $q4->where('event_date', $today)
                                    ->whereNotNull('start_time')
                                    ->where('start_time', '<=', $currentTime);
                            });
                    });
            })
            // Multi-day: past when event_date < today
            ->orWhere(function ($q2) use ($today) {
                $q2->where('duration_mode', 'multi_day')
                    ->where('event_date', '<', $today);
            });
        });
    }

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
                                            ->orWhere(function ($q6) use ($currentTime) {
                                                // No end time: use start_time as cutoff
                                                $q6->whereNull('range_end_time')
                                                    ->where(function ($q7) use ($currentTime) {
                                                        $q7->where('start_time', '>', $currentTime)
                                                            ->orWhereNull('start_time');
                                                    });
                                            });
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

    /**
     * Check if event has an active paid promotion (ServiceOrder featuring)
     */
    protected function hasActivePaidPromotion(Event $event): bool
    {
        $today = now()->toDateString();

        // Manual promotion via admin toggle
        if ($event->is_promoted) {
            if (!$event->promoted_until || $event->promoted_until->format('Y-m-d') >= $today) {
                return true;
            }
        }

        // Paid promotion via ServiceOrder
        return ServiceOrder::where('marketplace_event_id', $event->id)
            ->where('service_type', ServiceOrder::TYPE_FEATURING)
            ->where('status', ServiceOrder::STATUS_ACTIVE)
            ->where('service_start_date', '<=', $today)
            ->where('service_end_date', '>=', $today)
            ->exists();
    }

    /**
     * Get list of organizers with their event counts
     */
    public function organizers(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('status', 'active')
            ->withCount(['events' => function ($query) use ($client) {
                $query->where('marketplace_client_id', $client->id)
                    ->where(function ($q) {
                        $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                    });
            }]);

        // Filter: only organizers with events
        if ($request->boolean('with_events')) {
            $query->has('events');
        }

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Verified only
        if ($request->boolean('verified')) {
            $query->whereNotNull('verified_at');
        }

        // Sorting
        $sort = $request->get('sort', 'name');
        switch ($sort) {
            case 'events':
                $query->orderByDesc('events_count');
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'name':
            default:
                $query->orderBy('name');
                break;
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $organizers = $query->paginate($perPage);

        return $this->paginated($organizers, function ($organizer) {
            return [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'logo' => $organizer->logo ? Storage::disk('public')->url($organizer->logo) : null,
                'verified' => $organizer->verified_at !== null,
                'event_count' => $organizer->events_count ?? 0,
                'city' => $organizer->city,
            ];
        });
    }

    /**
     * Get single organizer public profile by slug or ID
     */
    public function organizer(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('status', 'active');

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $organizer = $query->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        // Get upcoming events
        $upcomingEventsQuery = Event::where('marketplace_client_id', $client->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->with([
                'venue:id,name,city',
                'marketplaceEventCategory',
                'ticketTypes' => function ($query) {
                    $query->select('id', 'event_id', 'price_cents', 'sale_price_cents', 'is_entry_ticket')
                        ->where('status', 'active')
                        ->where(function ($q) {
                            $q->where('is_entry_ticket', false)->orWhereNull('is_entry_ticket');
                        });
                },
            ]);

        $this->applyUpcomingFilter($upcomingEventsQuery);

        $upcomingEvents = $upcomingEventsQuery
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(50)
            ->get();

        // Get past events
        $pastEventsQuery = Event::where('marketplace_client_id', $client->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->with(['venue:id,name,city'])
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->where('event_date', '<', $today)
                    ->orWhere('range_end_date', '<', $today);
            })
            ->orderByDesc('event_date')
            ->limit(50)
            ->get();

        // Calculate stats — exclude cancelled events
        $totalEvents = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->count();

        $totalTicketsSold = $organizer->total_tickets_sold ?? 0;
        $followers = $organizer->followers_count ?? 0;
        $rating = $organizer->average_rating ?? 0;

        // Format upcoming events
        $formattedUpcoming = $upcomingEvents->map(function ($event) use ($language) {
            $venue = $event->venue;
            $category = $event->marketplaceEventCategory;
            $eventDate = $event->event_date ?? $event->range_start_date;

            // Get minimum price (with performance override support)
            $minPrice = null;
            $tts = $event->ticketTypes;
            if ($tts->isEmpty() && $event->parent_id) {
                $tts = \App\Models\TicketType::where('event_id', $event->parent_id)->where('status', 'active')->get();
            }
            if ($tts->isNotEmpty()) {
                // Find matching performance for child events
                $perf = null;
                if ($event->parent_id && $event->event_date) {
                    $cd = $event->event_date->format('Y-m-d');
                    $ct = $event->start_time ? substr($event->start_time, 0, 5) : null;
                    $perf = \App\Models\Performance::where('event_id', $event->parent_id)
                        ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                        ->get()
                        ->first(fn ($p) => $p->starts_at->format('Y-m-d') === $cd && (!$ct || $p->starts_at->format('H:i') === $ct));
                }
                $minPriceCents = $tts->map(function ($ticket) use ($perf) {
                    if ($perf) {
                        $override = $perf->getEffectivePrice($ticket);
                        if ($override !== null) return $override;
                    }
                    if ($ticket->sale_price_cents !== null && $ticket->sale_price_cents > 0) {
                        return $ticket->sale_price_cents;
                    }
                    return $ticket->price_cents;
                })->min();
                $minPrice = $minPriceCents ? $minPriceCents / 100 : null;
            }

            // Determine status
            $status = 'available';
            if ($event->is_sold_out) {
                $status = 'soldout';
            } elseif ($eventDate && $eventDate->diffInDays(now()) <= 7) {
                $status = 'soon';
            }

            $venueName = '';
            if ($venue) {
                $venueName = is_array($venue->name)
                    ? ($venue->name[$language] ?? $venue->name['ro'] ?? $venue->name['en'] ?? reset($venue->name) ?: '')
                    : ($venue->name ?? '');
            }

            return [
                'id' => $event->id,
                'title' => $event->getTranslation('title', $language),
                'slug' => $event->slug,
                'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'event_date' => $eventDate?->toIso8601String(),
                'start_time' => $event->start_time ? substr($event->start_time, 0, 5) : null,
                'duration_mode' => $event->duration_mode,
                'range_start_date' => $event->range_start_date?->toDateString(),
                'range_end_date' => $event->range_end_date?->toDateString(),
                'category' => $category?->getTranslation('name', $language),
                'venue_name' => $venueName,
                'venue_city' => $venue?->city ?? '',
                'price' => $minPrice,
                'is_sold_out' => (bool) $event->is_sold_out,
                'is_cancelled' => (bool) $event->is_cancelled,
                'status' => $status,
            ];
        });

        // Format past events
        $formattedPast = $pastEventsQuery->map(function ($event) use ($language) {
            $venue = $event->venue;
            $eventDate = $event->event_date ?? $event->range_start_date;
            $venueName = '';
            if ($venue) {
                $venueName = is_array($venue->name)
                    ? ($venue->name[$language] ?? $venue->name['ro'] ?? $venue->name['en'] ?? reset($venue->name) ?: '')
                    : ($venue->name ?? '');
            }

            return [
                'id' => $event->id,
                'title' => $event->getTranslation('title', $language),
                'slug' => $event->slug,
                'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'image' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'event_date' => $eventDate?->toIso8601String(),
                'venue_name' => $venueName,
                'venue_city' => $venue?->city ?? '',
                'date' => $eventDate?->translatedFormat('d F Y'),
                'participants' => $event->tickets_sold ?? 0,
            ];
        });

        // Parse social links
        $socialLinks = $organizer->social_links ?? [];
        $social = [
            'facebook' => $socialLinks['facebook'] ?? null,
            'instagram' => $socialLinks['instagram'] ?? null,
            'website' => $organizer->website ?? null,
        ];

        // Build quick facts
        $facts = [
            [
                'icon' => 'calendar',
                'label' => 'Activ din',
                'value' => $organizer->created_at?->format('Y'),
            ],
            [
                'icon' => 'location',
                'label' => 'Locație',
                'value' => $organizer->city ?? 'România',
            ],
        ];

        if ($rating > 0) {
            $facts[] = [
                'icon' => 'star',
                'label' => 'Rating mediu',
                'value' => number_format($rating, 1) . ' / 5',
            ];
        }

        if ($organizer->verified_at) {
            $facts[] = [
                'icon' => 'shield',
                'label' => 'Status',
                'value' => 'Verificat',
            ];
        }

        return $this->success([
            'avatar' => $organizer->logo ? Storage::disk('public')->url($organizer->logo) : null,
            'cover_image' => $organizer->cover_image ? Storage::disk('public')->url($organizer->cover_image) : null,
            'name' => $organizer->name,
            'slug' => $organizer->slug,
            'verified' => $organizer->verified_at !== null,
            'pro' => $organizer->is_pro ?? false,
            'tagline' => $organizer->tagline ?? $organizer->description ?? '',
            'location' => $organizer->city ?? 'România',
            'stats' => [
                'events' => $totalEvents,
                'tickets' => $this->formatNumber($totalTicketsSold),
                'followers' => $this->formatNumber($followers),
                'rating' => $rating > 0 ? number_format($rating, 1) : '-',
            ],
            'social' => $social,
            'upcomingEvents' => $formattedUpcoming,
            'pastEvents' => $formattedPast,
            'about' => $organizer->description ?? '',
            'facts' => $facts,
        ]);
    }

    /**
     * Submit a contact message for an organizer
     */
    public function organizerContact(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:5000',
        ]);

        $query = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('status', 'active');

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $organizer = $query->first();
        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        $msg = \App\Models\MarketplaceContactMessage::create([
            'marketplace_client_id' => $client->id,
            'marketplace_organizer_id' => $organizer->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'],
        ]);

        // Send email to organizer
        $organizerEmail = $organizer->email;
        if ($organizerEmail && $client->hasMailConfigured()) {
            try {
                $senderName = trim($validated['first_name'] . ' ' . $validated['last_name']);
                $subject = "Mesaj nou de la {$senderName}";
                $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937;">'
                    . '<h2 style="color:#2563eb;">Mesaj nou primit pe profilul tău</h2>'
                    . '<table cellpadding="6" style="border-collapse:collapse;">'
                    . '<tr><td style="color:#6b7280;">Nume</td><td><strong>' . e($senderName) . '</strong></td></tr>'
                    . '<tr><td style="color:#6b7280;">Email</td><td>' . e($validated['email']) . '</td></tr>'
                    . ($validated['phone'] ? '<tr><td style="color:#6b7280;">Telefon</td><td>' . e($validated['phone']) . '</td></tr>' : '')
                    . '</table>'
                    . '<div style="margin-top:16px;padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">'
                    . '<p style="white-space:pre-wrap;">' . e($validated['message']) . '</p>'
                    . '</div>'
                    . '</div>';

                $emailService = new \App\Services\MarketplaceEmailService($client);
                $emailService->sendCustomEmail($organizerEmail, $subject, $html, $organizer->name);
            } catch (\Throwable $e) {
                \Log::warning('Organizer contact email failed: ' . $e->getMessage());
            }
        }

        return $this->success(['id' => $msg->id], 'Mesajul a fost trimis cu succes.');
    }

    /**
     * Format large numbers for display (e.g., 1500 -> 1.5K)
     */
    protected function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        }
        if ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K';
        }
        return (string) $number;
    }
}
