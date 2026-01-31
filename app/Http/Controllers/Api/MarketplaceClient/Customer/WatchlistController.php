<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Gamification\ExperienceAction;
use App\Services\Gamification\ExperienceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WatchlistController extends BaseController
{
    /**
     * Get customer's watchlist
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $query = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 12), 50);
        $watchlist = $query->paginate($perPage);

        // Get event details from BOTH tables
        // Events from main events table (event_id)
        $eventIds = collect($watchlist->items())
            ->pluck('event_id')
            ->filter()
            ->unique();
        $eventsFromMain = $eventIds->isNotEmpty()
            ? DB::table('events')
                ->whereIn('id', $eventIds)
                ->get()
                ->keyBy('id')
            : collect();

        // Events from marketplace_events table (marketplace_event_id)
        $marketplaceEventIds = collect($watchlist->items())
            ->pluck('marketplace_event_id')
            ->filter()
            ->unique();
        $eventsFromMarketplace = $marketplaceEventIds->isNotEmpty()
            ? DB::table('marketplace_events')
                ->whereIn('id', $marketplaceEventIds)
                ->get()
                ->keyBy('id')
            : collect();

        $formattedItems = collect($watchlist->items())->map(function ($item) use ($eventsFromMain, $eventsFromMarketplace) {
            // Try to get event from main events table first, then marketplace_events
            $event = null;
            $eventSource = null;

            if ($item->event_id && $eventsFromMain->has($item->event_id)) {
                $event = $eventsFromMain->get($item->event_id);
                $eventSource = 'events';
            } elseif ($item->marketplace_event_id && $eventsFromMarketplace->has($item->marketplace_event_id)) {
                $event = $eventsFromMarketplace->get($item->marketplace_event_id);
                $eventSource = 'marketplace_events';
            }

            if (!$event) {
                return null;
            }

            // Handle date parsing based on event source
            $category = null;
            $genre = null;

            if ($eventSource === 'events') {
                // Main events table uses event_date + start_time
                $eventDate = $event->event_date ?? null;
                $startTime = $event->start_time ?? '00:00:00';
                $startsAt = $eventDate ? \Carbon\Carbon::parse($eventDate . ' ' . $startTime) : now();
                $eventName = is_string($event->title) ? json_decode($event->title, true) : $event->title;
                $eventName = is_array($eventName) ? ($eventName['ro'] ?? $eventName['en'] ?? reset($eventName)) : ($event->title ?? 'Untitled');
                $venueName = null;
                $venueCity = null;
                // Get venue info if venue_id exists
                if ($event->venue_id) {
                    $venue = DB::table('venues')->where('id', $event->venue_id)->first(['name', 'city']);
                    if ($venue) {
                        $venueNameData = is_string($venue->name) ? json_decode($venue->name, true) : $venue->name;
                        $venueName = is_array($venueNameData) ? ($venueNameData['ro'] ?? $venueNameData['en'] ?? reset($venueNameData)) : $venue->name;
                        $venueCity = $venue->city;
                    }
                }

                // Get category/genre from event_type
                if (isset($event->event_type_id) && $event->event_type_id) {
                    $eventType = DB::table('event_types')->where('id', $event->event_type_id)->first(['name']);
                    if ($eventType) {
                        $categoryData = is_string($eventType->name) ? json_decode($eventType->name, true) : $eventType->name;
                        $category = is_array($categoryData) ? ($categoryData['ro'] ?? $categoryData['en'] ?? reset($categoryData)) : $eventType->name;
                    }
                }

                // Get genre from event_event_genre pivot
                $eventGenre = DB::table('event_event_genre')
                    ->join('event_genres', 'event_genres.id', '=', 'event_event_genre.event_genre_id')
                    ->where('event_event_genre.event_id', $event->id)
                    ->first(['event_genres.name']);
                if ($eventGenre) {
                    $genreData = is_string($eventGenre->name) ? json_decode($eventGenre->name, true) : $eventGenre->name;
                    $genre = is_array($genreData) ? ($genreData['ro'] ?? $genreData['en'] ?? reset($genreData)) : $eventGenre->name;
                }

                // Calculate min price from ticket_types
                $minPriceResult = DB::table('ticket_types')
                    ->where('event_id', $event->id)
                    ->where('status', 'active')
                    ->selectRaw('MIN(COALESCE(sale_price_cents, price_cents)) as min_price_cents')
                    ->first();
                $minPrice = $minPriceResult && $minPriceResult->min_price_cents
                    ? $minPriceResult->min_price_cents / 100
                    : null;

                $imagePath = $event->poster_url ?? $event->hero_image_url ?? null;
            } else {
                // Marketplace events table uses starts_at
                $startsAt = \Carbon\Carbon::parse($event->starts_at ?? now());
                $eventName = $event->name;
                $venueName = $event->venue_name ?? null;
                $venueCity = $event->venue_city ?? null;
                $imagePath = $event->image ?? null;
                $category = $event->category ?? null;
                $genre = $event->genre ?? null;
                $minPrice = $event->min_price ?? null;
            }

            // Format image URL properly - use APP_URL for consistent domain
            $image = null;
            if ($imagePath) {
                if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
                    $image = $imagePath;
                } else {
                    $image = rtrim(config('app.url'), '/') . '/storage/' . ltrim($imagePath, '/');
                }
            }

            $isUpcoming = $startsAt >= now();
            $daysUntil = $isUpcoming ? now()->diffInDays($startsAt, false) : null;

            return [
                'id' => $item->id,
                'added_at' => $item->created_at,
                'notify_on_sale' => (bool) ($item->notify_on_sale ?? true),
                'notify_on_price_drop' => (bool) ($item->notify_on_price_drop ?? false),
                'event' => [
                    'id' => $event->id,
                    'name' => $eventName,
                    'slug' => $event->slug,
                    'date' => $startsAt->toDateTimeString(),
                    'date_formatted' => $startsAt->format('d M Y'),
                    'time' => $startsAt->format('H:i'),
                    'venue' => $venueName,
                    'city' => $venueCity,
                    'image' => $image,
                    'category' => $category,
                    'genre' => $genre,
                    'min_price' => $minPrice,
                    'currency' => $event->currency ?? 'RON',
                    'is_upcoming' => $isUpcoming,
                    'days_until' => $daysUntil,
                    'is_sold_out' => (bool) ($event->is_sold_out ?? false),
                    'status' => $event->status ?? 'active',
                ],
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $formattedItems,
            'meta' => [
                'current_page' => $watchlist->currentPage(),
                'last_page' => $watchlist->lastPage(),
                'per_page' => $watchlist->perPage(),
                'total' => $watchlist->total(),
            ],
        ]);
    }

    /**
     * Add event to watchlist
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'event_id' => 'required|integer',
            'notify_on_sale' => 'boolean',
            'notify_on_price_drop' => 'boolean',
        ]);

        // Check if event exists in either table
        $eventId = $validated['event_id'];
        $marketplaceEventId = null;
        $mainEventId = null;

        // First check main events table
        $mainEvent = DB::table('events')
            ->where('id', $eventId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if ($mainEvent) {
            $mainEventId = $mainEvent->id;
        } else {
            // Fall back to marketplace_events table
            $marketplaceEvent = DB::table('marketplace_events')
                ->where('id', $eventId)
                ->where('marketplace_client_id', $client->id)
                ->first();

            if ($marketplaceEvent) {
                $marketplaceEventId = $marketplaceEvent->id;
            } else {
                return $this->error('Event not found', 404);
            }
        }

        // Check if already in watchlist (check both columns)
        $existing = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where(function ($q) use ($mainEventId, $marketplaceEventId, $eventId) {
                if ($mainEventId) {
                    $q->where('event_id', $mainEventId);
                }
                if ($marketplaceEventId) {
                    $q->orWhere('marketplace_event_id', $marketplaceEventId);
                }
            })
            ->first();

        if ($existing) {
            return $this->error('Event is already in your watchlist', 422);
        }

        // Add to watchlist
        $id = DB::table('marketplace_customer_watchlist')->insertGetId([
            'marketplace_client_id' => $client->id,
            'marketplace_customer_id' => $customer->id,
            'event_id' => $mainEventId,
            'marketplace_event_id' => $marketplaceEventId,
            'notify_on_sale' => $validated['notify_on_sale'] ?? true,
            'notify_on_price_drop' => $validated['notify_on_price_drop'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Award XP for adding to wishlist
        $this->awardWishlistXp($client->id, $customer->id, $id);

        return $this->success([
            'watchlist_id' => $id,
        ], 'Event added to watchlist', 201);
    }

    /**
     * Update watchlist item preferences
     */
    public function update(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'notify_on_sale' => 'boolean',
            'notify_on_price_drop' => 'boolean',
        ]);

        // Try to update by either event_id or marketplace_event_id
        $updated = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                  ->orWhere('marketplace_event_id', $eventId);
            })
            ->update([
                'notify_on_sale' => $validated['notify_on_sale'] ?? true,
                'notify_on_price_drop' => $validated['notify_on_price_drop'] ?? false,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            return $this->error('Event not found in watchlist', 404);
        }

        return $this->success(null, 'Watchlist preferences updated');
    }

    /**
     * Remove event from watchlist
     */
    public function destroy(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        // Try to delete by either event_id or marketplace_event_id
        $deleted = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                  ->orWhere('marketplace_event_id', $eventId);
            })
            ->delete();

        if (!$deleted) {
            return $this->error('Event not found in watchlist', 404);
        }

        return $this->success(null, 'Event removed from watchlist');
    }

    /**
     * Check if event is in watchlist
     */
    public function check(Request $request, int $eventId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        // Check by either event_id or marketplace_event_id
        $exists = DB::table('marketplace_customer_watchlist')
            ->where('marketplace_customer_id', $customer->id)
            ->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                  ->orWhere('marketplace_event_id', $eventId);
            })
            ->exists();

        return $this->success([
            'in_watchlist' => $exists,
        ]);
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        // Use Auth::guard('sanctum') to properly detect authenticated customers
        // This works even when the route doesn't have auth:sanctum middleware
        $customer = Auth::guard('sanctum')->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }

    /**
     * Award XP for adding to wishlist
     */
    protected function awardWishlistXp(int $marketplaceClientId, int $customerId, int $watchlistId): void
    {
        try {
            $experienceService = app(ExperienceService::class);

            $experienceService->awardActionXpForMarketplace(
                $marketplaceClientId,
                $customerId,
                ExperienceAction::ACTION_WISHLIST_ADD,
                0,
                [
                    'reference_type' => 'marketplace_customer_watchlist',
                    'reference_id' => $watchlistId,
                    'description' => [
                        'en' => 'Added event to wishlist',
                        'ro' => 'Eveniment adăugat la favorite',
                    ],
                ]
            );

            Log::channel('marketplace')->debug('XP awarded for wishlist add', [
                'marketplace_client_id' => $marketplaceClientId,
                'customer_id' => $customerId,
                'watchlist_id' => $watchlistId,
            ]);

        } catch (\Exception $e) {
            Log::channel('marketplace')->warning('Failed to award XP for wishlist add', [
                'marketplace_client_id' => $marketplaceClientId,
                'customer_id' => $customerId,
                'watchlist_id' => $watchlistId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
