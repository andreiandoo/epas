<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceEventCategory;
use App\Models\Artist;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\CustomerBadge;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StatsController extends BaseController
{
    /**
     * Get customer dashboard stats
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        // Orders stats
        $totalOrders = Order::where('marketplace_customer_id', $customer->id)->count();
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        $completedOrders = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->count();

        // Tickets stats
        $totalTickets = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');

        // Upcoming events
        $upcomingEvents = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->count();

        // Past events
        $pastEvents = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '<', now());
            })
            ->count();

        // Active tickets (for upcoming events)
        $activeTickets = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');

        // Points/Rewards (if gamification is enabled)
        $points = 0;
        $level = 1;
        $levelName = 'Bronze';
        $xp = 0;
        $xpToNextLevel = 100;

        try {
            $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
            if ($customerPoints) {
                $points = $customerPoints->balance ?? 0;
            }

            $customerExperience = CustomerExperience::where('marketplace_customer_id', $customer->id)->first();
            if ($customerExperience) {
                $level = $customerExperience->current_level ?? 1;
                $levelName = $customerExperience->current_level_group ?? 'Bronze';
                $xp = $customerExperience->total_xp ?? 0;
                $xpToNextLevel = $customerExperience->xp_to_next_level ?? 100;
            }
        } catch (\Exception $e) {
            // Gamification tables might not exist yet
        }

        // Reviews count
        $reviewsCount = 0;
        try {
            $reviewsCount = \DB::table('marketplace_customer_reviews')
                ->where('marketplace_customer_id', $customer->id)
                ->count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Watchlist count
        $watchlistCount = 0;
        try {
            $watchlistCount = \DB::table('marketplace_customer_watchlist')
                ->where('marketplace_customer_id', $customer->id)
                ->count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Total spent (stored in cents)
        $totalSpentCents = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->sum('total_cents');
        $totalSpent = $totalSpentCents / 100;

        // Credit balance (if applicable)
        $creditBalance = $customer->credit_balance ?? 0;

        return $this->success([
            'orders' => [
                'total' => $totalOrders,
                'completed' => $completedOrders,
            ],
            'tickets' => [
                'total' => $totalTickets,
                'active' => $activeTickets,
            ],
            'events' => [
                'upcoming' => $upcomingEvents,
                'past' => $pastEvents,
            ],
            'rewards' => [
                'points' => $points,
                'level' => $level,
                'level_name' => $levelName,
                'xp' => $xp,
                'xp_to_next_level' => $xpToNextLevel,
            ],
            'reviews_count' => $reviewsCount,
            'watchlist_count' => $watchlistCount,
            'total_spent' => (float) $totalSpent,
            'credit_balance' => (float) $creditBalance,
            'currency' => 'RON',
        ]);
    }

    /**
     * Get upcoming events for dashboard.
     *
     * Finds upcoming events via two paths:
     * 1. Orders with marketplace_event_id set directly
     * 2. Orders without marketplace_event_id — resolved through tickets
     */
    public function upcomingEvents(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $limit = min((int) $request->input('limit', 5), 20);

        // Get orders with future events — either via direct relation or via tickets
        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where(function ($q) {
                // Path 1: order has marketplace_event_id set
                $q->whereHas('marketplaceEvent', function ($eq) {
                    $eq->where('starts_at', '>=', now());
                });
                // Path 2: order has no marketplace_event_id but tickets link to future events
                $q->orWhere(function ($q2) {
                    $q2->whereNull('marketplace_event_id')
                        ->whereHas('tickets', function ($tq) {
                            $tq->whereHas('marketplaceEvent', function ($eq) {
                                $eq->where('starts_at', '>=', now());
                            });
                        });
                });
            })
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,ends_at,venue_name,venue_city,image',
                'tickets.marketplaceEvent:id,name,slug,starts_at,ends_at,venue_name,venue_city,image',
            ])
            ->withCount('tickets')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $events = $orders->map(function ($order) {
            // Resolve event: direct relation first, fallback to first ticket's event
            $event = $order->marketplaceEvent;
            if (!$event) {
                $event = $order->tickets->first()?->marketplaceEvent;
            }
            if (!$event || !$event->starts_at || $event->starts_at->isPast()) {
                return null;
            }

            $daysUntil = now()->diffInDays($event->starts_at, false);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tickets_count' => $order->tickets_count,
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'date' => $event->starts_at->toIso8601String(),
                    'date_formatted' => $event->starts_at->format('d M Y'),
                    'time' => $event->starts_at->format('H:i'),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                    'image' => $event->image_url,
                    'days_until' => max(0, $daysUntil),
                ],
            ];
        })->filter()->sortBy(function ($item) {
            return $item['event']['date'];
        })->values();

        return $this->success([
            'upcoming_events' => $events,
        ]);
    }

    /**
     * Get rich profile data computed from order history
     */
    public function profileData(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $clientId = $customer->marketplace_client_id;
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Broader filter: include orders where payment_status is 'paid' even if order status wasn't updated
        $validOrderQuery = function () use ($customer, $paidStatuses) {
            return Order::where('marketplace_customer_id', $customer->id)
                ->where(function ($q) use ($paidStatuses) {
                    $q->whereIn('status', $paidStatuses)
                      ->orWhere('payment_status', 'paid');
                });
        };

        $validOrderIds = $validOrderQuery()->pluck('id');

        // Get events from TICKETS (tickets always have marketplace_event_id set correctly,
        // unlike orders which may have null marketplace_event_id for multi-event orders)
        $tickets = Ticket::whereIn('order_id', $validOrderIds)
            ->whereNotNull('marketplace_event_id')
            ->with('marketplaceEvent:id,name,slug,starts_at,ends_at,venue_name,venue_city,marketplace_event_category_id,artist_ids,image')
            ->get();

        // Deduplicate: group by event to get unique events
        $uniqueEvents = $tickets->groupBy('marketplace_event_id')->map(function ($group) {
            return $group->first()->marketplaceEvent;
        })->filter();

        // === Taste Profile ===
        $categoryCount = [];
        $cityCount = [];
        $artistIdCount = [];
        $monthlyActivity = array_fill(0, 12, 0);

        foreach ($uniqueEvents as $event) {
            // Category counting
            if ($event->marketplace_event_category_id) {
                $catId = $event->marketplace_event_category_id;
                $categoryCount[$catId] = ($categoryCount[$catId] ?? 0) + 1;
            }

            // City counting
            $city = $event->venue_city;
            if ($city) {
                $cityCount[$city] = ($cityCount[$city] ?? 0) + 1;
            }

            // Artist counting
            $artistIds = $event->artist_ids ?? [];
            if (is_array($artistIds)) {
                foreach ($artistIds as $aid) {
                    $artistIdCount[$aid] = ($artistIdCount[$aid] ?? 0) + 1;
                }
            }

            // Monthly activity (current year)
            if ($event->starts_at && $event->starts_at->year === now()->year) {
                $monthlyActivity[$event->starts_at->month - 1]++;
            }
        }

        // Build taste profile from categories
        $totalEvents = array_sum($categoryCount);
        $tasteProfile = [];
        if ($totalEvents > 0) {
            $categories = MarketplaceEventCategory::whereIn('id', array_keys($categoryCount))->get();
            $gradients = [
                ['#A51C30', '#E8336D'], ['#4F46E5', '#818CF8'], ['#059669', '#34D399'],
                ['#D97706', '#FCD34D'], ['#7C3AED', '#C084FC'], ['#DC2626', '#FB923C'],
            ];
            $i = 0;
            arsort($categoryCount);
            foreach ($categoryCount as $catId => $count) {
                $cat = $categories->firstWhere('id', $catId);
                if (!$cat) continue;
                $gradient = $gradients[$i % count($gradients)];
                $tasteProfile[] = [
                    'name' => $cat->getLocalizedName('ro') ?? $cat->name,
                    'emoji' => $cat->icon_emoji ?? '🎵',
                    'percentage' => round(($count / $totalEvents) * 100),
                    'count' => $count,
                    'gradient' => $gradient,
                ];
                $i++;
            }
        }

        // === Top Artists (merged: order history + favorites) ===
        // Add favorite artists with a weight of 1
        try {
            $favArtistIds = $customer->favoriteArtists()->pluck('artists.id')->toArray();
            foreach ($favArtistIds as $faid) {
                $artistIdCount[$faid] = ($artistIdCount[$faid] ?? 0) + 1;
            }
        } catch (\Exception $e) {}

        $topArtists = [];
        if (!empty($artistIdCount)) {
            arsort($artistIdCount);
            $topIds = array_slice(array_keys($artistIdCount), 0, 6, true);
            $artists = Artist::whereIn('id', $topIds)->with('artistGenres')->get();
            foreach ($topIds as $aid) {
                $artist = $artists->firstWhere('id', $aid);
                if (!$artist) continue;
                $isFavorite = in_array($aid, $favArtistIds ?? []);
                $topArtists[] = [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'image' => $artist->main_image_full_url,
                    'events_count' => $artistIdCount[$aid] - ($isFavorite ? 1 : 0), // subtract the +1 fav weight for display
                    'is_favorite' => $isFavorite,
                ];
            }
        }

        // === Preferred Genres (from artists in orders + favorites) ===
        $genreCount = [];
        if (!empty($artistIdCount)) {
            $allArtistIds = array_keys($artistIdCount);
            $genreRows = \DB::table('artist_genres as ag')
                ->join('artist_artist_genre as aag', 'aag.artist_genre_id', '=', 'ag.id')
                ->whereIn('aag.artist_id', $allArtistIds)
                ->select(
                    'ag.id',
                    \DB::raw(
                        \DB::getDriverName() === 'pgsql'
                            ? "COALESCE(ag.name->>'ro', ag.name->>'en', ag.name::text) as label"
                            : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ag.name, '$.ro')), JSON_UNQUOTE(JSON_EXTRACT(ag.name, '$.en')), ag.name) as label"
                    )
                )
                ->get();
            foreach ($genreRows as $gr) {
                $genreCount[$gr->label] = ($genreCount[$gr->label] ?? 0) + 1;
            }
        }
        arsort($genreCount);
        $totalGenres = array_sum($genreCount);
        $preferredGenres = [];
        foreach ($genreCount as $label => $count) {
            $preferredGenres[] = [
                'name' => $label,
                'count' => $count,
                'percentage' => $totalGenres > 0 ? round(($count / $totalGenres) * 100) : 0,
            ];
        }

        // === Cities Visited ===
        $totalCityEvents = array_sum($cityCount);
        arsort($cityCount);
        $citiesVisited = [];
        foreach ($cityCount as $city => $count) {
            $citiesVisited[] = [
                'name' => $city,
                'count' => $count,
                'percentage' => $totalCityEvents > 0 ? round(($count / $totalCityEvents) * 100) : 0,
            ];
        }

        // === Activity Data (12 months) ===
        $monthNames = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $activityData = [];
        for ($m = 0; $m < 12; $m++) {
            $activityData[] = [
                'month' => $monthNames[$m],
                'count' => $monthlyActivity[$m],
            ];
        }

        // === Insights ===
        $insights = [];

        // Most active month
        $maxMonth = array_keys($monthlyActivity, max($monthlyActivity));
        if ($monthlyActivity[$maxMonth[0]] > 0) {
            $insights[] = [
                'icon' => '📅',
                'label' => 'Luna cea mai activă',
                'value' => $monthNames[$maxMonth[0]],
            ];
        }

        // Favorite city
        if (!empty($citiesVisited)) {
            $insights[] = [
                'icon' => '📍',
                'label' => 'Orașul preferat',
                'value' => $citiesVisited[0]['name'],
            ];
        }

        // Average spend (use broader filter, try both total_cents and total columns)
        $totalSpentCents = $validOrderQuery()->sum('total_cents');
        $totalSpentDecimal = (float) $validOrderQuery()->sum('total');
        $totalSpent = $totalSpentCents > 0 ? ($totalSpentCents / 100) : $totalSpentDecimal;
        $paidOrdersCount = $validOrderQuery()->count();
        if ($paidOrdersCount > 0) {
            $avgSpend = $totalSpent / $paidOrdersCount;
            $insights[] = [
                'icon' => '💰',
                'label' => 'Cheltuiala medie',
                'value' => number_format($avgSpend, 0, ',', '.') . ' RON',
            ];
        }

        // Total events
        $insights[] = [
            'icon' => '🎫',
            'label' => 'Total evenimente',
            'value' => (string) $totalEvents,
        ];

        // Total cities
        if (count($citiesVisited) > 1) {
            $insights[] = [
                'icon' => '🌍',
                'label' => 'Orașe vizitate',
                'value' => (string) count($citiesVisited),
            ];
        }

        // === Badges ===
        $badges = [];
        try {
            $customerBadges = CustomerBadge::where('marketplace_customer_id', $customer->id)
                ->with('badge')
                ->orderBy('earned_at', 'desc')
                ->get();
            foreach ($customerBadges as $cb) {
                if (!$cb->badge) continue;
                $badges[] = [
                    'name' => $cb->badge->getLocalizedName('ro') ?? ($cb->badge->name['ro'] ?? $cb->badge->name['en'] ?? ''),
                    'description' => $cb->badge->getLocalizedDescription('ro') ?? '',
                    'icon' => $cb->badge->icon_url,
                    'color' => $cb->badge->color,
                    'rarity' => $cb->badge->rarity_name,
                    'earned_at' => $cb->earned_at?->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            // Badge tables might not exist
        }

        // === Customer Type ===
        $customerType = 'Explorator';
        $customerTypeEmoji = '🌟';
        if (!empty($tasteProfile)) {
            $dominant = $tasteProfile[0]['name'] ?? '';
            $dominantLower = mb_strtolower($dominant);
            if (str_contains($dominantLower, 'concert') || str_contains($dominantLower, 'muzic')) {
                $customerType = 'Meloman';
                $customerTypeEmoji = '🎵';
            } elseif (str_contains($dominantLower, 'sport')) {
                $customerType = 'Sportiv';
                $customerTypeEmoji = '⚽';
            } elseif (str_contains($dominantLower, 'teatru') || str_contains($dominantLower, 'cultur')) {
                $customerType = 'Cultural';
                $customerTypeEmoji = '🎭';
            } elseif (str_contains($dominantLower, 'festival')) {
                $customerType = 'Festivalier';
                $customerTypeEmoji = '🎪';
            } elseif (str_contains($dominantLower, 'stand') || str_contains($dominantLower, 'comedie')) {
                $customerType = 'Comedian Fan';
                $customerTypeEmoji = '😂';
            }
        }

        // === Favorites ===
        $favoriteArtists = [];
        try {
            $favArtists = $customer->favoriteArtists()->limit(6)->get();
            foreach ($favArtists as $fa) {
                $favoriteArtists[] = [
                    'id' => $fa->id,
                    'name' => $fa->name,
                    'image' => $fa->main_image_full_url,
                ];
            }
        } catch (\Exception $e) {}

        $favoriteVenues = [];
        try {
            $favVenues = $customer->favoriteVenues()->limit(6)->get();
            foreach ($favVenues as $fv) {
                $favoriteVenues[] = [
                    'id' => $fv->id,
                    'name' => $fv->name,
                    'city' => $fv->city ?? null,
                ];
            }
        } catch (\Exception $e) {}

        return $this->success([
            'customer_type' => $customerType,
            'customer_type_emoji' => $customerTypeEmoji,
            'taste_profile' => $tasteProfile,
            'top_artists' => $topArtists,
            'preferred_genres' => $preferredGenres,
            'cities_visited' => $citiesVisited,
            'activity_data' => $activityData,
            'insights' => $insights,
            'badges' => $badges,
            'favorites' => [
                'artists' => $favoriteArtists,
                'venues' => $favoriteVenues,
            ],
            'stats' => [
                'total_events' => $totalEvents,
                'total_spent' => $totalSpent,
                'total_cities' => count($citiesVisited),
                'total_artists' => count($artistIdCount),
            ],
        ]);
    }

    /**
     * Smart suggestions for progressive profiling
     * Returns suggested cities, venues based on order history and favorites
     */
    public function smartSuggestions(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // === Suggested Cities (from orders + favorites) ===
        $cityScores = [];

        // From orders (weight: 70%)
        $orderCities = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->whereNotNull('marketplace_event_id')
            ->with('marketplaceEvent:id,venue_city')
            ->get()
            ->pluck('marketplaceEvent.venue_city')
            ->filter();

        foreach ($orderCities as $city) {
            $cityScores[$city] = ($cityScores[$city] ?? 0) + 2; // orders weighted more
        }

        // From favorite venues
        try {
            $favVenues = $customer->favoriteVenues()->get(['city']);
            foreach ($favVenues as $fv) {
                if ($fv->city) {
                    $cityScores[$fv->city] = ($cityScores[$fv->city] ?? 0) + 1;
                }
            }
        } catch (\Exception $e) {}

        // From watchlist events
        try {
            $watchlistEvents = $customer->watchlistEvents()->with('venue:id,city')->get();
            foreach ($watchlistEvents as $we) {
                $city = $we->venue?->city;
                if ($city) {
                    $cityScores[$city] = ($cityScores[$city] ?? 0) + 1;
                }
            }
        } catch (\Exception $e) {}

        // From customer profile
        if ($customer->city) {
            $cityScores[$customer->city] = ($cityScores[$customer->city] ?? 0) + 3; // profile city is strongest
        }

        arsort($cityScores);
        $suggestedCities = [];
        foreach ($cityScores as $city => $score) {
            $suggestedCities[] = ['name' => $city, 'score' => $score];
        }

        // === Suggested Venues (from orders + favorites, grouped by city) ===
        $venueScores = [];

        // From orders
        $orderVenueIds = Order::where('marketplace_customer_id', $customer->id)
            ->whereIn('status', $paidStatuses)
            ->whereNotNull('marketplace_event_id')
            ->with('marketplaceEvent:id,venue_id')
            ->get()
            ->pluck('marketplaceEvent.venue_id')
            ->filter();

        foreach ($orderVenueIds as $vid) {
            $venueScores[$vid] = ($venueScores[$vid] ?? 0) + 2;
        }

        // From favorites
        try {
            $favVenueIds = $customer->favoriteVenues()->pluck('venues.id');
            foreach ($favVenueIds as $vid) {
                $venueScores[$vid] = ($venueScores[$vid] ?? 0) + 1;
            }
        } catch (\Exception $e) {}

        arsort($venueScores);
        $topVenueIds = array_slice(array_keys($venueScores), 0, 20, true);

        $suggestedVenues = [];
        if (!empty($topVenueIds)) {
            $venues = \App\Models\Venue::whereIn('id', $topVenueIds)->get();
            foreach ($topVenueIds as $vid) {
                $venue = $venues->firstWhere('id', $vid);
                if (!$venue) continue;
                $suggestedVenues[] = [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', 'ro'),
                    'city' => $venue->city,
                    'score' => $venueScores[$vid],
                ];
            }
        }

        // === Existing interests from settings ===
        $settings = $customer->settings ?? [];
        $existingInterests = $settings['interests'] ?? [];

        return $this->success([
            'suggested_cities' => $suggestedCities,
            'suggested_venues' => $suggestedVenues,
            'existing_interests' => $existingInterests,
            'profile' => [
                'city' => $customer->city,
                'state' => $customer->state,
            ],
            'profiling_state' => $settings['profiling'] ?? null,
        ]);
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
