<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\TicketType;
use App\Models\Tax\GeneralTax;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EventsController extends BaseController
{
    /**
     * Get all available events from allowed tenants
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $language = $client->language ?? 'ro';

        $query = Event::query()
            ->with(['venue:id,name,city,state,country', 'marketplaceEventCategory', 'ticketTypes' => function ($q) {
                $q->where('status', 'active')
                    ->select(['id', 'event_id', 'name', 'price_cents', 'sale_price_cents', 'quota_total', 'quota_sold', 'currency']);
            }])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('is_public', true)->orWhereNull('is_public');
            })
            // Filter upcoming: marketplace events use event_date, tenant events use starts_at
            ->where(function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->orWhere('starts_at', '>=', now());
            })
            // Exclude cancelled events
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        // Include both marketplace events AND tenant events (if allowed)
        $query->where(function ($q) use ($client) {
            // Marketplace events belonging to this client
            $q->where('marketplace_client_id', $client->id);

            // OR tenant events from allowed tenants
            $allowedTenants = $client->allowed_tenants;
            if (!is_null($allowedTenants) && count($allowedTenants) > 0) {
                $q->orWhereIn('tenant_id', $allowedTenants);
            }
        });

        // Additional filters
        if ($request->has('tenant_id')) {
            $tenantId = (int) $request->tenant_id;
            if (!$client->canSellForTenant($tenantId)) {
                return $this->error('Not authorized to sell tickets for this tenant', 403);
            }
            $query->where('tenant_id', $tenantId);
        }

        if ($request->has('category')) {
            $categorySlug = $request->category;
            // Filter by marketplace_event_category relationship (events table has no 'category' column)
            $query->whereHas('marketplaceEventCategory', function ($cq) use ($categorySlug) {
                $cq->where('slug', $categorySlug);
            });
        }

        if ($request->has('city')) {
            $query->whereHas('venue', function ($q) use ($request) {
                $q->where('city', 'like', '%' . $request->city . '%');
            });
        }

        if ($request->has('from_date')) {
            $fromDate = $request->from_date;
            $query->where(function ($q) use ($fromDate) {
                $q->where('event_date', '>=', $fromDate)
                  ->orWhere('starts_at', '>=', $fromDate);
            });
        }

        if ($request->has('to_date')) {
            $toDate = $request->to_date;
            $query->where(function ($q) use ($toDate) {
                $q->where('event_date', '<=', $toDate)
                  ->orWhere('starts_at', '<=', $toDate);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                // Tenant events: search in name column
                $q->where('name', 'like', "%{$search}%")
                    // Marketplace events: search in JSON title field (ro and en)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.ro')) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(title, '$.en')) LIKE ?", ["%{$search}%"])
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('venue', function ($vq) use ($search) {
                        $vq->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    })
                    ->orWhereHas('artists', function ($aq) use ($search) {
                        $aq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Price range filter (prices stored in cents)
        if ($request->has('min_price') || $request->has('max_price')) {
            $query->whereHas('ticketTypes', function ($q) use ($request) {
                $q->where('status', 'active');
                if ($request->has('min_price')) {
                    $minCents = (int) ($request->min_price * 100);
                    $q->where(function ($sq) use ($minCents) {
                        $sq->where('sale_price_cents', '>=', $minCents)
                            ->orWhere(fn ($q2) => $q2->whereNull('sale_price_cents')->where('price_cents', '>=', $minCents));
                    });
                }
                if ($request->has('max_price')) {
                    $maxCents = (int) ($request->max_price * 100);
                    $q->where(function ($sq) use ($maxCents) {
                        $sq->where('sale_price_cents', '<=', $maxCents)
                            ->orWhere(fn ($q2) => $q2->whereNull('sale_price_cents')->where('price_cents', '<=', $maxCents));
                    });
                }
            });
        }

        // Has available tickets (available = quota_total - quota_sold)
        if ($request->boolean('available_only', false)) {
            $query->whereHas('ticketTypes', function ($q) {
                $q->where('status', 'active')
                    ->whereRaw('(quota_total - COALESCE(quota_sold, 0)) > 0');
            });
        }

        // Sorting - use COALESCE to handle both event_date (marketplace) and starts_at (tenant)
        $sortField = $request->get('sort', 'date');
        $sortDir = strtoupper($request->get('order', 'asc')) === 'DESC' ? 'DESC' : 'ASC';

        if ($sortField === 'date' || $sortField === 'starts_at' || $sortField === 'event_date') {
            $query->orderByRaw("COALESCE(event_date, DATE(starts_at)) {$sortDir}");
        } else {
            $query->orderBy($sortField, $sortDir);
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        // Format events for response (handle both marketplace and tenant events)
        $formattedEvents = collect($paginator->items())->map(function ($event) use ($language) {
            $isMarketplaceEvent = !empty($event->marketplace_client_id);

            // Get title
            $title = $isMarketplaceEvent
                ? ($event->getTranslation('title', $language) ?? $event->name)
                : $event->name;

            // Get dates
            $startsAt = $isMarketplaceEvent
                ? ($event->event_date ? $event->event_date->format('Y-m-d') . ' ' . ($event->start_time ?? '00:00') : null)
                : $event->starts_at;

            // Get image (convert to absolute URL)
            $imageRelative = $isMarketplaceEvent
                ? ($event->poster_url ?? $event->hero_image_url ?? $event->image_url)
                : $event->image_url;
            $imageUrl = $imageRelative ? url('storage/' . ltrim($imageRelative, '/')) : null;

            // Get category
            $category = $isMarketplaceEvent
                ? ($event->marketplaceEventCategory?->getTranslation('name', $language) ?? $event->category)
                : $event->category;

            // Get venue name (handle translatable JSON field)
            $venueName = null;
            if ($event->venue) {
                $venueName = $event->venue->getTranslation('name', $language)
                    ?? (is_array($event->venue->name) ? ($event->venue->name[$language] ?? $event->venue->name['ro'] ?? $event->venue->name['en'] ?? null) : $event->venue->name);
            }

            // Calculate min price from ticket types
            $minPrice = $event->ticketTypes->map(function ($tt) {
                return ($tt->sale_price_cents ?? $tt->price_cents) / 100;
            })->min();

            // Calculate total available tickets
            $totalAvailable = $event->ticketTypes->sum(function ($tt) {
                return max(0, ($tt->quota_total ?? 0) - ($tt->quota_sold ?? 0));
            });

            return [
                'id' => $event->id,
                'name' => $title,
                'slug' => $event->slug,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'start_time' => $event->start_time,
                'starts_at' => $startsAt,
                'image_url' => $imageUrl,
                'category' => $category,
                'venue' => $venueName,
                'city' => $event->venue?->city,
                'price_from' => $minPrice,
                'has_availability' => $totalAvailable > 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedEvents,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get single event details (supports both ID and slug)
     */
    public function show(Request $request, string|int $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::with([
            'venue',
            'marketplaceEventCategory',
            'ticketTypes' => function ($q) {
                $q->where('status', 'active')
                    ->orderBy('id');
            },
            'artists',
            'eventTypes', // Load event types for tax calculation
        ]);

        // Support both ID and slug lookup
        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->where('status', 'published')
            ->where(function ($q) {
                $q->where('is_public', true)->orWhereNull('is_public');
            })
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Check authorization: either marketplace event belonging to this client, or tenant event with permission
        if ($event->marketplace_client_id) {
            // Marketplace event - check if it belongs to this marketplace client
            if ($event->marketplace_client_id !== $client->id) {
                return $this->error('Not authorized to sell tickets for this event', 403);
            }
            $commission = $event->commission_rate ?? $client->commission_rate ?? 5;
            $commissionMode = $event->commission_mode ?? $client->commission_mode ?? 'included';
        } else {
            // Tenant event - check if client can sell for this tenant
            if (!$event->tenant_id || !$client->canSellForTenant($event->tenant_id)) {
                return $this->error('Not authorized to sell tickets for this event', 403);
            }
            $commission = $client->getCommissionForTenant($event->tenant_id);
            $commissionMode = $event->commission_mode ?? $event->tenant?->commission_mode ?? 'included';
        }

        // Get language from client
        $language = $client->language ?? 'ro';

        // Handle both marketplace events (translatable fields) and tenant events (simple fields)
        $isMarketplaceEvent = !empty($event->marketplace_client_id);

        // Get title: marketplace events use translatable title, tenant events use name
        $title = $isMarketplaceEvent
            ? ($event->getTranslation('title', $language) ?? $event->name)
            : $event->name;

        // Get description: marketplace events use translatable description (JSON), tenant events use simple string
        $description = $isMarketplaceEvent
            ? ($event->getTranslation('description', $language) ?? '')
            : ($event->description ?? '');

        // Make sure description is a string
        if (is_array($description)) {
            $description = $description[$language] ?? $description['ro'] ?? $description['en'] ?? '';
        }

        // Get dates: marketplace events use event_date + start_time, tenant events use starts_at
        $startsAt = $isMarketplaceEvent
            ? ($event->event_date ? $event->event_date->format('Y-m-d') . ' ' . ($event->start_time ?? '00:00') : null)
            : $event->starts_at;

        $endsAt = $isMarketplaceEvent
            ? ($event->event_date ? $event->event_date->format('Y-m-d') . ' ' . ($event->end_time ?? '23:59') : null)
            : $event->ends_at;

        $doorsOpenAt = $isMarketplaceEvent
            ? ($event->event_date ? $event->event_date->format('Y-m-d') . ' ' . ($event->door_time ?? $event->start_time ?? '00:00') : null)
            : $event->doors_open_at;

        // Get images: marketplace events use poster_url/hero_image_url, tenant events use image_url/cover_image_url
        // Convert relative paths to absolute URLs
        $imageRelative = $isMarketplaceEvent
            ? ($event->poster_url ?? $event->hero_image_url ?? $event->image_url)
            : $event->image_url;
        $imageUrl = $imageRelative ? url('storage/' . ltrim($imageRelative, '/')) : null;

        $coverImageRelative = $isMarketplaceEvent
            ? ($event->hero_image_url ?? $event->poster_url ?? $event->cover_image_url)
            : $event->cover_image_url;
        $coverImageUrl = $coverImageRelative ? url('storage/' . ltrim($coverImageRelative, '/')) : null;

        // Get category
        $category = $isMarketplaceEvent
            ? ($event->marketplaceEventCategory?->getTranslation('name', $language) ?? $event->category)
            : $event->category;

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $title,
                'slug' => $event->slug,
                'description' => $description,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'doors_open_at' => $doorsOpenAt,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'door_time' => $event->door_time,
                'category' => $category,
                'image_url' => $imageUrl,
                'cover_image_url' => $coverImageUrl,
                'tenant_id' => $event->tenant_id,
                'marketplace_client_id' => $event->marketplace_client_id,
                'views_count' => $event->views_count ?? 0,
                'interested_count' => $event->interested_count ?? 0,
                'target_price' => $event->target_price ?? $client->target_price ?? null,
            ],
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'slug' => $event->venue->slug,
                'name' => $event->venue->getTranslation('name', $language)
                    ?? (is_array($event->venue->name) ? ($event->venue->name[$language] ?? $event->venue->name['ro'] ?? $event->venue->name['en'] ?? null) : $event->venue->name),
                'description' => $event->venue->getTranslation('description', $language)
                    ?? (is_array($event->venue->description) ? ($event->venue->description[$language] ?? $event->venue->description['ro'] ?? $event->venue->description['en'] ?? null) : $event->venue->description),
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'state' => $event->venue->state,
                'country' => $event->venue->country,
                'latitude' => $event->venue->lat,
                'longitude' => $event->venue->lng,
                'google_maps_url' => $event->venue->google_maps_url,
                'image' => $event->venue->image_url ? url('storage/' . $event->venue->image_url) : null,
                'capacity' => $event->venue->capacity,
            ] : null,
            'ticket_types' => $event->ticketTypes->map(function ($tt) use ($language) {
                $priceCents = $tt->price_cents ?? 0;
                $salePriceCents = $tt->sale_price_cents;
                $hasDiscount = $salePriceCents !== null && $salePriceCents < $priceCents;
                $displayPrice = $hasDiscount ? $salePriceCents / 100 : $priceCents / 100;
                $originalPrice = $hasDiscount ? $priceCents / 100 : null;

                $ttName = is_array($tt->name) ? ($tt->name[$language] ?? $tt->name['ro'] ?? $tt->name['en'] ?? '') : $tt->name;
                $ttDesc = is_array($tt->description) ? ($tt->description[$language] ?? $tt->description['ro'] ?? $tt->description['en'] ?? '') : ($tt->description ?? '');
                return [
                    'id' => $tt->id,
                    'name' => $ttName,
                    'description' => $ttDesc,
                    'price' => $displayPrice,
                    'original_price' => $originalPrice,
                    'discount_percent' => $hasDiscount ? round((1 - $salePriceCents / $priceCents) * 100) : null,
                    'price_formatted' => number_format($displayPrice, 2) . ' ' . ($tt->currency ?? 'RON'),
                    'available_quantity' => max(0, ($tt->quota_total ?? 0) - ($tt->quota_sold ?? 0)),
                    'max_per_order' => $tt->max_per_order ?? 10,
                    'min_per_order' => $tt->min_per_order ?? 1,
                    'sale_starts_at' => $tt->sales_start_at,
                    'sale_ends_at' => $tt->sales_end_at,
                ];
            }),
            'artists' => $event->artists->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'slug' => $artist->slug,
                    'image_url' => $artist->main_image_full_url ?? $artist->image_url,
                ];
            }),
            'commission_rate' => $commission,
            'commission_mode' => $commissionMode,
            // Get applicable taxes based on event's event types
            'taxes' => $this->getEventTaxes($event, $client),
        ]);
    }

    /**
     * Get featured events
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $language = $client->language ?? 'ro';

        $query = Event::query()
            ->with(['venue:id,name,city', 'marketplaceEventCategory', 'ticketTypes' => function ($q) {
                $q->where('status', 'active')
                    ->select(['id', 'event_id', 'name', 'price_cents', 'sale_price_cents', 'quota_total', 'quota_sold']);
            }])
            ->where('status', 'published')
            ->where(function ($q) {
                $q->where('is_public', true)->orWhereNull('is_public');
            })
            // Featured: check is_featured OR marketplace featured flags
            ->where(function ($q) {
                $q->where('is_featured', true)
                  ->orWhere('is_homepage_featured', true)
                  ->orWhere('is_general_featured', true);
            })
            // Filter upcoming: marketplace events use event_date, tenant events use starts_at
            ->where(function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                  ->orWhere('starts_at', '>=', now());
            })
            // Exclude cancelled events
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });

        // Include both marketplace events AND tenant events (if allowed)
        $query->where(function ($q) use ($client) {
            // Marketplace featured events belonging to this client
            $q->where('marketplace_client_id', $client->id);

            // OR tenant events from allowed tenants
            $allowedTenants = $client->allowed_tenants;
            if (!is_null($allowedTenants) && count($allowedTenants) > 0) {
                $q->orWhereIn('tenant_id', $allowedTenants);
            }
        });

        $limit = min((int) $request->get('limit', 10), 50);
        $events = $query->orderByRaw('COALESCE(event_date, DATE(starts_at)) ASC')->limit($limit)->get();

        return $this->success([
            'events' => $events->map(function ($event) use ($language) {
                $isMarketplaceEvent = !empty($event->marketplace_client_id);

                // Get title
                $title = $isMarketplaceEvent
                    ? ($event->getTranslation('title', $language) ?? $event->name)
                    : $event->name;

                // Get dates
                $startsAt = $isMarketplaceEvent
                    ? ($event->event_date ? $event->event_date->format('Y-m-d') . ' ' . ($event->start_time ?? '00:00') : null)
                    : $event->starts_at;

                // Get image (convert to absolute URL)
                $imageRelative = $isMarketplaceEvent
                    ? ($event->poster_url ?? $event->hero_image_url ?? $event->image_url)
                    : $event->image_url;
                $imageUrl = $imageRelative ? url('storage/' . ltrim($imageRelative, '/')) : null;

                // Calculate min price from active ticket types
                $minPrice = $event->ticketTypes->map(function ($tt) {
                    return ($tt->sale_price_cents ?? $tt->price_cents) / 100;
                })->min();

                // Calculate total available tickets
                $totalAvailable = $event->ticketTypes->sum(function ($tt) {
                    return max(0, ($tt->quota_total ?? 0) - ($tt->quota_sold ?? 0));
                });

                // Get venue name (handle translatable JSON field)
                $venueName = null;
                if ($event->venue) {
                    $venueName = $event->venue->getTranslation('name', $language)
                        ?? (is_array($event->venue->name) ? ($event->venue->name[$language] ?? $event->venue->name['ro'] ?? $event->venue->name['en'] ?? null) : $event->venue->name);
                }

                return [
                    'id' => $event->id,
                    'name' => $title,
                    'slug' => $event->slug,
                    'event_date' => $event->event_date?->format('Y-m-d'),
                    'start_time' => $event->start_time,
                    'starts_at' => $startsAt,
                    'image_url' => $imageUrl,
                    'venue' => $venueName,
                    'city' => $event->venue?->city,
                    'price_from' => $minPrice,
                    'has_availability' => $totalAvailable > 0,
                ];
            }),
        ]);
    }

    /**
     * Get available categories
     */
    public function categories(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('category');

        // Include both marketplace events AND tenant events (if allowed)
        $query->where(function ($q) use ($client) {
            $q->where('marketplace_client_id', $client->id);
            $allowedTenants = $client->allowed_tenants;
            if (!is_null($allowedTenants) && count($allowedTenants) > 0) {
                $q->orWhereIn('tenant_id', $allowedTenants);
            }
        });

        $categories = $query->selectRaw('category, COUNT(*) as event_count')
            ->groupBy('category')
            ->orderByDesc('event_count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category,
                    'slug' => \Illuminate\Support\Str::slug($item->category),
                    'event_count' => $item->event_count,
                ];
            });

        return $this->success([
            'categories' => $categories,
        ]);
    }

    /**
     * Get available cities
     */
    public function cities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::query()
            ->join('venues', 'events.venue_id', '=', 'venues.id')
            ->where('events.status', 'published')
            ->where('events.is_public', true)
            ->where('events.starts_at', '>=', now())
            ->whereNotNull('venues.city');

        // Include both marketplace events AND tenant events (if allowed)
        $query->where(function ($q) use ($client) {
            $q->where('events.marketplace_client_id', $client->id);
            $allowedTenants = $client->allowed_tenants;
            if (!is_null($allowedTenants) && count($allowedTenants) > 0) {
                $q->orWhereIn('events.tenant_id', $allowedTenants);
            }
        });

        $cities = $query->selectRaw('venues.city, venues.country, COUNT(*) as event_count')
            ->groupBy('venues.city', 'venues.country')
            ->orderByDesc('event_count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->city,
                    'country' => $item->country,
                    'event_count' => $item->event_count,
                ];
            });

        return $this->success([
            'cities' => $cities,
        ]);
    }

    /**
     * Get ticket availability for an event (supports both ID and slug)
     */
    public function availability(Request $request, string|int $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Support both ID and slug lookup
        if (is_numeric($identifier)) {
            $event = Event::find((int) $identifier);
        } else {
            $event = Event::where('slug', $identifier)->first();
        }

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Check authorization: either marketplace event belonging to this client, or tenant event with permission
        if ($event->marketplace_client_id) {
            if ($event->marketplace_client_id !== $client->id) {
                return $this->error('Not authorized', 403);
            }
        } else {
            if (!$event->tenant_id || !$client->canSellForTenant($event->tenant_id)) {
                return $this->error('Not authorized', 403);
            }
        }

        $ticketTypes = TicketType::where('event_id', $event->id)
            ->where('status', 'active')
            ->get()
            ->map(function ($tt) {
                $available = max(0, ($tt->quota_total ?? 0) - ($tt->quota_sold ?? 0));
                $displayPrice = ($tt->sale_price_cents ?? $tt->price_cents) / 100;
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => $displayPrice,
                    'available' => $available,
                    'status' => $available > 0 ? 'available' : 'sold_out',
                ];
            });

        return $this->success([
            'event_id' => $event->id,
            'ticket_types' => $ticketTypes,
            'last_updated' => now()->toIso8601String(),
        ]);
    }

    /**
     * Track a page view for an event
     */
    public function trackView(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find event by ID or slug
        $event = is_numeric($identifier)
            ? Event::find($identifier)
            : Event::where('slug', $identifier)->first();

        if (! $event) {
            return $this->error('Event not found', 404);
        }

        // Verify event belongs to this marketplace or allowed tenant
        if ($event->marketplace_client_id) {
            if ($event->marketplace_client_id !== $client->id) {
                return $this->error('Event not found', 404);
            }
        } elseif ($event->tenant_id && ! $client->canSellForTenant($event->tenant_id)) {
            return $this->error('Event not found', 404);
        }

        // Increment view count
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

        // Find event by ID or slug
        $event = is_numeric($identifier)
            ? Event::find($identifier)
            : Event::where('slug', $identifier)->first();

        if (! $event) {
            return $this->error('Event not found', 404);
        }

        // Verify event belongs to this marketplace or allowed tenant
        if ($event->marketplace_client_id) {
            if ($event->marketplace_client_id !== $client->id) {
                return $this->error('Event not found', 404);
            }
        } elseif ($event->tenant_id && ! $client->canSellForTenant($event->tenant_id)) {
            return $this->error('Event not found', 404);
        }

        // Get session ID for anonymous users
        // Use header, cookie, or generate based on IP+User-Agent
        $sessionId = $request->header('X-Session-ID')
            ?? $request->cookie('ambilet_session')
            ?? md5($request->ip() . $request->userAgent());

        // Check if already interested
        $existingInterest = \DB::table('event_interests')
            ->where('event_id', $event->id)
            ->where('session_id', $sessionId)
            ->first();

        // Check if user is authenticated
        $customer = $request->user();
        $isAuthenticated = $customer instanceof MarketplaceCustomer;

        if ($existingInterest) {
            // Remove interest
            DB::table('event_interests')
                ->where('event_id', $event->id)
                ->where('session_id', $sessionId)
                ->delete();
            $event->decrement('interested_count');
            $isInterested = false;

            // Also remove from watchlist if authenticated
            if ($isAuthenticated) {
                DB::table('marketplace_customer_watchlist')
                    ->where('marketplace_customer_id', $customer->id)
                    ->where('event_id', $event->id)
                    ->delete();
            }
        } else {
            // Add interest
            DB::table('event_interests')->insert([
                'event_id' => $event->id,
                'session_id' => $sessionId,
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $event->increment('interested_count');
            $isInterested = true;

            // Also add to watchlist if authenticated
            if ($isAuthenticated) {
                // Check if not already in watchlist
                $existingWatchlist = DB::table('marketplace_customer_watchlist')
                    ->where('marketplace_customer_id', $customer->id)
                    ->where('event_id', $event->id)
                    ->exists();

                if (!$existingWatchlist) {
                    DB::table('marketplace_customer_watchlist')->insert([
                        'marketplace_client_id' => $client->id,
                        'marketplace_customer_id' => $customer->id,
                        'event_id' => $event->id,
                        'marketplace_event_id' => null, // Not from marketplace_events table
                        'notify_on_sale' => true,
                        'notify_on_price_drop' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return $this->success([
            'is_interested' => $isInterested,
            'interested_count' => max(0, $event->refresh()->interested_count),
            'in_watchlist' => $isAuthenticated && $isInterested,
        ]);
    }

    /**
     * Check if current user is interested in an event
     */
    public function checkInterest(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find event by ID or slug
        $event = is_numeric($identifier)
            ? Event::find($identifier)
            : Event::where('slug', $identifier)->first();

        if (! $event) {
            return $this->error('Event not found', 404);
        }

        // Get session ID for anonymous users
        // Use header, cookie, or generate based on IP+User-Agent
        $sessionId = $request->header('X-Session-ID')
            ?? $request->cookie('ambilet_session')
            ?? md5($request->ip() . $request->userAgent());

        $isInterested = \DB::table('event_interests')
            ->where('event_id', $event->id)
            ->where('session_id', $sessionId)
            ->exists();

        return $this->success([
            'is_interested' => $isInterested,
            'interested_count' => $event->interested_count,
            'views_count' => $event->views_count,
        ]);
    }

    /**
     * Get applicable taxes for an event based on its event types
     */
    protected function getEventTaxes(Event $event, $client): array
    {
        // Get event type IDs
        $eventTypeIds = $event->eventTypes->pluck('id')->toArray();

        // Build base query for global taxes
        $query = GeneralTax::query()
            ->whereNull('tenant_id') // Global taxes only (not tenant-specific)
            ->where('is_active', true)
            ->where('visible_on_checkout', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            });

        // Check if event_type_id column exists before filtering by it
        try {
            if (!empty($eventTypeIds) && \Schema::hasColumn('general_taxes', 'event_type_id')) {
                $query->where(function ($q) use ($eventTypeIds) {
                    // Match taxes that:
                    // 1. Have no event_type_id (apply to all event types)
                    // 2. OR have an event_type_id that matches one of the event's types
                    $q->whereNull('event_type_id')
                      ->orWhereIn('event_type_id', $eventTypeIds);
                });
            }
        } catch (\Exception $e) {
            // Column doesn't exist, just continue without event type filter
            \Log::debug('event_type_id column not found in general_taxes, skipping filter');
        }

        $taxes = $query->orderByDesc('priority')->get();

        return $taxes->map(function ($tax) {
            return [
                'id' => $tax->id,
                'name' => $tax->name,
                'value' => (float) $tax->value,
                'value_type' => $tax->value_type, // 'percent' or 'fixed'
                'currency' => $tax->currency,
                'is_added_to_price' => (bool) $tax->is_added_to_price,
                'explanation' => strip_tags($tax->explanation ?? ''),
                'icon_svg' => $tax->icon_svg,
            ];
        })->toArray();
    }
}
