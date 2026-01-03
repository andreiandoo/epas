# AI-Powered Event Recommendations Implementation Plan

## Scope & Problem Statement

### What This Feature Solves
Users struggle to discover relevant events:
1. **Information overload**: Too many events to browse manually
2. **Missed events**: Users don't see events they'd enjoy
3. **Low engagement**: Generic event lists don't drive conversions
4. **Cold start**: New users have no personalized suggestions

### What This Feature Does
- Personalized event recommendations based on purchase history
- Collaborative filtering ("Users who bought X also bought Y")
- Content-based filtering (similar event attributes)
- Trending and popular events
- Location-aware recommendations
- Email digests with personalized picks

---

## Technical Implementation

### 1. Database Migrations

```php
// 2026_01_03_000090_create_recommendation_tables.php
Schema::create('customer_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->json('categories')->nullable(); // Preferred categories
    $table->json('venues')->nullable(); // Favorite venues
    $table->json('price_range')->nullable(); // Min/max price preference
    $table->json('time_preferences')->nullable(); // Weekday/weekend, time of day
    $table->decimal('max_distance_km', 8, 2)->nullable();
    $table->boolean('email_recommendations')->default(true);
    $table->timestamps();

    $table->unique(['tenant_id', 'customer_id']);
});

Schema::create('customer_event_interactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->enum('interaction_type', ['view', 'wishlist', 'cart', 'purchase', 'attend']);
    $table->float('weight')->default(1.0); // Interaction weight for scoring
    $table->timestamps();

    $table->index(['customer_id', 'event_id', 'interaction_type']);
    $table->index(['tenant_id', 'event_id']);
});

Schema::create('event_similarities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->foreignId('similar_event_id')->constrained('events');
    $table->float('similarity_score');
    $table->json('similarity_factors')->nullable();
    $table->timestamps();

    $table->unique(['event_id', 'similar_event_id']);
    $table->index(['tenant_id', 'event_id', 'similarity_score']);
});

Schema::create('recommendation_cache', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->string('recommendation_type'); // personalized, trending, similar
    $table->json('event_ids');
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->unique(['customer_id', 'recommendation_type']);
});

Schema::create('trending_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('event_id')->constrained();
    $table->integer('view_count')->default(0);
    $table->integer('purchase_count')->default(0);
    $table->integer('wishlist_count')->default(0);
    $table->float('trending_score')->default(0);
    $table->date('date');
    $table->timestamps();

    $table->unique(['tenant_id', 'event_id', 'date']);
    $table->index(['tenant_id', 'date', 'trending_score']);
});
```

### 2. Models

```php
// app/Models/CustomerPreference.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPreference extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'categories', 'venues',
        'price_range', 'time_preferences', 'max_distance_km',
        'email_recommendations',
    ];

    protected $casts = [
        'categories' => 'array',
        'venues' => 'array',
        'price_range' => 'array',
        'time_preferences' => 'array',
        'max_distance_km' => 'decimal:2',
        'email_recommendations' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

// app/Models/CustomerEventInteraction.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEventInteraction extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'event_id',
        'interaction_type', 'weight',
    ];

    protected $casts = [
        'weight' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // Interaction weights
    public static function getWeight(string $type): float
    {
        return match ($type) {
            'view' => 1.0,
            'wishlist' => 3.0,
            'cart' => 4.0,
            'purchase' => 5.0,
            'attend' => 6.0,
            default => 1.0,
        };
    }
}

// app/Models/EventSimilarity.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSimilarity extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'similar_event_id',
        'similarity_score', 'similarity_factors',
    ];

    protected $casts = [
        'similarity_score' => 'float',
        'similarity_factors' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function similarEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'similar_event_id');
    }
}
```

### 3. Recommendation Service

```php
// app/Services/Recommendations/RecommendationService.php
<?php

namespace App\Services\Recommendations;

use App\Models\Customer;
use App\Models\Event;
use App\Models\CustomerEventInteraction;
use App\Models\EventSimilarity;
use App\Models\RecommendationCache;
use App\Models\TrendingEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    protected int $cacheMinutes = 60;

    /**
     * Get personalized recommendations for customer
     */
    public function getPersonalized(Customer $customer, int $limit = 10): Collection
    {
        // Check cache
        $cached = $this->getCached($customer, 'personalized');
        if ($cached) {
            return Event::whereIn('id', $cached)->get()->sortBy(function ($event) use ($cached) {
                return array_search($event->id, $cached);
            })->values();
        }

        $eventIds = collect();

        // 1. Content-based: Similar to purchased/viewed events
        $contentBased = $this->getContentBasedRecommendations($customer, $limit);
        $eventIds = $eventIds->merge($contentBased);

        // 2. Collaborative: What similar users purchased
        $collaborative = $this->getCollaborativeRecommendations($customer, $limit);
        $eventIds = $eventIds->merge($collaborative);

        // 3. Based on preferences
        $preferenceBased = $this->getPreferenceBasedRecommendations($customer, $limit);
        $eventIds = $eventIds->merge($preferenceBased);

        // Deduplicate and filter purchased
        $purchasedIds = $this->getPurchasedEventIds($customer);
        $eventIds = $eventIds->unique()->diff($purchasedIds)->take($limit);

        // Cache results
        $this->cacheRecommendations($customer, 'personalized', $eventIds->toArray());

        return Event::whereIn('id', $eventIds)
            ->where('start_date', '>', now())
            ->where('status', 'published')
            ->get();
    }

    /**
     * Get similar events
     */
    public function getSimilar(Event $event, int $limit = 6): Collection
    {
        // Check pre-computed similarities
        $similarIds = EventSimilarity::where('event_id', $event->id)
            ->orderByDesc('similarity_score')
            ->limit($limit)
            ->pluck('similar_event_id');

        if ($similarIds->isEmpty()) {
            // Compute on-the-fly
            $similarIds = $this->computeSimilarEvents($event, $limit);
        }

        return Event::whereIn('id', $similarIds)
            ->where('id', '!=', $event->id)
            ->where('start_date', '>', now())
            ->where('status', 'published')
            ->get();
    }

    /**
     * Get trending events
     */
    public function getTrending(int $tenantId, int $limit = 10): Collection
    {
        $today = now()->toDateString();

        $eventIds = TrendingEvent::where('tenant_id', $tenantId)
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->select('event_id', DB::raw('SUM(trending_score) as total_score'))
            ->groupBy('event_id')
            ->orderByDesc('total_score')
            ->limit($limit)
            ->pluck('event_id');

        if ($eventIds->isEmpty()) {
            // Fallback to recent popular events
            $eventIds = Event::where('tenant_id', $tenantId)
                ->where('start_date', '>', now())
                ->where('status', 'published')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->pluck('id');
        }

        return Event::whereIn('id', $eventIds)
            ->where('start_date', '>', now())
            ->get();
    }

    /**
     * Get recommendations based on location
     */
    public function getNearby(Customer $customer, int $limit = 10, ?float $maxDistanceKm = null): Collection
    {
        $preferences = $customer->preferences;
        $maxDistance = $maxDistanceKm ?? $preferences?->max_distance_km ?? 50;

        if (!$customer->latitude || !$customer->longitude) {
            return collect();
        }

        return Event::where('tenant_id', $customer->tenant_id)
            ->where('start_date', '>', now())
            ->where('status', 'published')
            ->whereHas('venue', function ($q) use ($customer, $maxDistance) {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereRaw("
                        (6371 * acos(
                            cos(radians(?)) *
                            cos(radians(latitude)) *
                            cos(radians(longitude) - radians(?)) +
                            sin(radians(?)) *
                            sin(radians(latitude))
                        )) <= ?
                    ", [$customer->latitude, $customer->longitude, $customer->latitude, $maxDistance]);
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Track customer interaction
     */
    public function trackInteraction(Customer $customer, Event $event, string $type): void
    {
        CustomerEventInteraction::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'event_id' => $event->id,
                'interaction_type' => $type,
            ],
            [
                'tenant_id' => $customer->tenant_id,
                'weight' => CustomerEventInteraction::getWeight($type),
            ]
        );

        // Update trending
        $this->updateTrending($event, $type);

        // Invalidate cache
        RecommendationCache::where('customer_id', $customer->id)->delete();
    }

    /**
     * Content-based recommendations
     */
    protected function getContentBasedRecommendations(Customer $customer, int $limit): Collection
    {
        // Get customer's interacted events
        $interactedEventIds = CustomerEventInteraction::where('customer_id', $customer->id)
            ->orderByDesc('weight')
            ->limit(10)
            ->pluck('event_id');

        if ($interactedEventIds->isEmpty()) {
            return collect();
        }

        // Get similar events
        return EventSimilarity::whereIn('event_id', $interactedEventIds)
            ->orderByDesc('similarity_score')
            ->limit($limit)
            ->pluck('similar_event_id');
    }

    /**
     * Collaborative filtering recommendations
     */
    protected function getCollaborativeRecommendations(Customer $customer, int $limit): Collection
    {
        // Find customers with similar purchase history
        $customerEvents = CustomerEventInteraction::where('customer_id', $customer->id)
            ->where('interaction_type', 'purchase')
            ->pluck('event_id');

        if ($customerEvents->isEmpty()) {
            return collect();
        }

        // Find similar customers
        $similarCustomers = CustomerEventInteraction::whereIn('event_id', $customerEvents)
            ->where('customer_id', '!=', $customer->id)
            ->where('interaction_type', 'purchase')
            ->select('customer_id', DB::raw('COUNT(*) as overlap'))
            ->groupBy('customer_id')
            ->orderByDesc('overlap')
            ->limit(20)
            ->pluck('customer_id');

        // Get events those customers purchased
        return CustomerEventInteraction::whereIn('customer_id', $similarCustomers)
            ->where('interaction_type', 'purchase')
            ->whereNotIn('event_id', $customerEvents)
            ->select('event_id', DB::raw('COUNT(*) as purchase_count'))
            ->groupBy('event_id')
            ->orderByDesc('purchase_count')
            ->limit($limit)
            ->pluck('event_id');
    }

    /**
     * Preference-based recommendations
     */
    protected function getPreferenceBasedRecommendations(Customer $customer, int $limit): Collection
    {
        $preferences = $customer->preferences;

        if (!$preferences) {
            return collect();
        }

        $query = Event::where('tenant_id', $customer->tenant_id)
            ->where('start_date', '>', now())
            ->where('status', 'published');

        if ($preferences->categories) {
            $query->whereHas('categories', function ($q) use ($preferences) {
                $q->whereIn('id', $preferences->categories);
            });
        }

        if ($preferences->venues) {
            $query->whereIn('venue_id', $preferences->venues);
        }

        if ($preferences->price_range) {
            $min = $preferences->price_range['min'] ?? 0;
            $max = $preferences->price_range['max'] ?? null;

            $query->whereHas('ticketTypes', function ($q) use ($min, $max) {
                $q->where('price', '>=', $min);
                if ($max) {
                    $q->where('price', '<=', $max);
                }
            });
        }

        return $query->limit($limit)->pluck('id');
    }

    /**
     * Compute similar events based on attributes
     */
    protected function computeSimilarEvents(Event $event, int $limit): Collection
    {
        // Same category
        $categoryIds = $event->categories->pluck('id');

        return Event::where('tenant_id', $event->tenant_id)
            ->where('id', '!=', $event->id)
            ->where('start_date', '>', now())
            ->where('status', 'published')
            ->where(function ($q) use ($event, $categoryIds) {
                // Same categories
                $q->whereHas('categories', fn($q2) => $q2->whereIn('id', $categoryIds))
                    // Same venue
                    ->orWhere('venue_id', $event->venue_id)
                    // Same organizer
                    ->orWhere('organizer_id', $event->organizer_id);
            })
            ->limit($limit)
            ->pluck('id');
    }

    protected function getPurchasedEventIds(Customer $customer): Collection
    {
        return CustomerEventInteraction::where('customer_id', $customer->id)
            ->where('interaction_type', 'purchase')
            ->pluck('event_id');
    }

    protected function getCached(Customer $customer, string $type): ?array
    {
        $cache = RecommendationCache::where('customer_id', $customer->id)
            ->where('recommendation_type', $type)
            ->where('expires_at', '>', now())
            ->first();

        return $cache?->event_ids;
    }

    protected function cacheRecommendations(Customer $customer, string $type, array $eventIds): void
    {
        RecommendationCache::updateOrCreate(
            ['customer_id' => $customer->id, 'recommendation_type' => $type],
            [
                'tenant_id' => $customer->tenant_id,
                'event_ids' => $eventIds,
                'expires_at' => now()->addMinutes($this->cacheMinutes),
            ]
        );
    }

    protected function updateTrending(Event $event, string $type): void
    {
        $date = now()->toDateString();

        $trending = TrendingEvent::firstOrCreate(
            ['tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'date' => $date],
            ['view_count' => 0, 'purchase_count' => 0, 'wishlist_count' => 0, 'trending_score' => 0]
        );

        match ($type) {
            'view' => $trending->increment('view_count'),
            'purchase' => $trending->increment('purchase_count'),
            'wishlist' => $trending->increment('wishlist_count'),
            default => null,
        };

        // Recalculate trending score
        $trending->update([
            'trending_score' => ($trending->view_count * 1) +
                               ($trending->wishlist_count * 3) +
                               ($trending->purchase_count * 10),
        ]);
    }
}
```

### 4. Similarity Computation Job

```php
// app/Jobs/ComputeEventSimilarities.php
<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventSimilarity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeEventSimilarities implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $tenantId) {}

    public function handle(): void
    {
        $events = Event::where('tenant_id', $this->tenantId)
            ->where('start_date', '>', now())
            ->where('status', 'published')
            ->with(['categories', 'venue', 'tags'])
            ->get();

        foreach ($events as $event) {
            $this->computeForEvent($event, $events);
        }
    }

    protected function computeForEvent(Event $event, $allEvents): void
    {
        $similarities = [];

        foreach ($allEvents as $other) {
            if ($event->id === $other->id) continue;

            $score = $this->calculateSimilarity($event, $other);

            if ($score > 0.1) { // Threshold
                $similarities[] = [
                    'tenant_id' => $event->tenant_id,
                    'event_id' => $event->id,
                    'similar_event_id' => $other->id,
                    'similarity_score' => $score,
                    'similarity_factors' => $this->getFactors($event, $other),
                ];
            }
        }

        // Sort by score and keep top 10
        usort($similarities, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);
        $similarities = array_slice($similarities, 0, 10);

        // Upsert
        foreach ($similarities as $sim) {
            EventSimilarity::updateOrCreate(
                ['event_id' => $sim['event_id'], 'similar_event_id' => $sim['similar_event_id']],
                $sim
            );
        }
    }

    protected function calculateSimilarity(Event $a, Event $b): float
    {
        $score = 0;
        $factors = 0;

        // Category overlap (Jaccard similarity)
        $aCats = $a->categories->pluck('id')->toArray();
        $bCats = $b->categories->pluck('id')->toArray();
        if (!empty($aCats) && !empty($bCats)) {
            $intersection = count(array_intersect($aCats, $bCats));
            $union = count(array_unique(array_merge($aCats, $bCats)));
            $score += ($intersection / $union) * 0.4;
            $factors++;
        }

        // Same venue
        if ($a->venue_id && $a->venue_id === $b->venue_id) {
            $score += 0.3;
            $factors++;
        }

        // Same organizer
        if ($a->organizer_id && $a->organizer_id === $b->organizer_id) {
            $score += 0.2;
            $factors++;
        }

        // Similar date (within 30 days)
        if ($a->start_date && $b->start_date) {
            $daysDiff = abs($a->start_date->diffInDays($b->start_date));
            if ($daysDiff <= 30) {
                $score += (1 - $daysDiff / 30) * 0.1;
                $factors++;
            }
        }

        return $factors > 0 ? $score : 0;
    }

    protected function getFactors(Event $a, Event $b): array
    {
        return [
            'same_venue' => $a->venue_id === $b->venue_id,
            'same_organizer' => $a->organizer_id === $b->organizer_id,
            'shared_categories' => array_intersect(
                $a->categories->pluck('id')->toArray(),
                $b->categories->pluck('id')->toArray()
            ),
        ];
    }
}
```

### 5. Controller

```php
// app/Http/Controllers/Api/TenantClient/RecommendationController.php
<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Services\Recommendations\RecommendationService;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function __construct(protected RecommendationService $recommendationService) {}

    /**
     * Get personalized recommendations
     */
    public function personalized(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $limit = min($request->input('limit', 10), 20);

        $events = $this->recommendationService->getPersonalized($customer, $limit);

        return response()->json(['events' => $events]);
    }

    /**
     * Get similar events
     */
    public function similar(Request $request, Event $event): JsonResponse
    {
        $limit = min($request->input('limit', 6), 12);

        $events = $this->recommendationService->getSimilar($event, $limit);

        return response()->json(['events' => $events]);
    }

    /**
     * Get trending events
     */
    public function trending(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $limit = min($request->input('limit', 10), 20);

        $events = $this->recommendationService->getTrending($tenantId, $limit);

        return response()->json(['events' => $events]);
    }

    /**
     * Get nearby events
     */
    public function nearby(Request $request): JsonResponse
    {
        $customer = $request->user('customer');
        $limit = min($request->input('limit', 10), 20);
        $distance = $request->input('distance_km');

        $events = $this->recommendationService->getNearby($customer, $limit, $distance);

        return response()->json(['events' => $events]);
    }

    /**
     * Update preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'categories' => 'nullable|array',
            'venues' => 'nullable|array',
            'price_range' => 'nullable|array',
            'max_distance_km' => 'nullable|numeric|min:1|max:500',
            'email_recommendations' => 'nullable|boolean',
        ]);

        $customer = $request->user('customer');

        $customer->preferences()->updateOrCreate(
            ['customer_id' => $customer->id],
            array_merge(['tenant_id' => $customer->tenant_id], $request->only([
                'categories', 'venues', 'price_range',
                'max_distance_km', 'email_recommendations',
            ]))
        );

        return response()->json(['message' => 'Preferences updated']);
    }

    /**
     * Track event view
     */
    public function trackView(Request $request, Event $event): JsonResponse
    {
        $customer = $request->user('customer');

        $this->recommendationService->trackInteraction($customer, $event, 'view');

        return response()->json(['tracked' => true]);
    }
}
```

### 6. Routes

```php
// routes/api.php
Route::prefix('tenant-client/recommendations')->middleware(['tenant'])->group(function () {
    Route::get('/trending', [RecommendationController::class, 'trending']);
    Route::get('/events/{event}/similar', [RecommendationController::class, 'similar']);

    Route::middleware('auth:customer')->group(function () {
        Route::get('/personalized', [RecommendationController::class, 'personalized']);
        Route::get('/nearby', [RecommendationController::class, 'nearby']);
        Route::put('/preferences', [RecommendationController::class, 'updatePreferences']);
        Route::post('/events/{event}/view', [RecommendationController::class, 'trackView']);
    });
});
```

### 7. Scheduled Commands

```php
// Schedule similarity computation weekly
$schedule->job(new ComputeEventSimilarities($tenantId))
    ->weekly()
    ->sundays()
    ->at('03:00');

// Clean old trending data
$schedule->command('recommendations:cleanup')->daily();
```

---

## Testing Checklist

1. [ ] Personalized recommendations return relevant events
2. [ ] Similar events are computed correctly
3. [ ] Trending events update with interactions
4. [ ] Nearby recommendations use location correctly
5. [ ] Preferences filtering works
6. [ ] Collaborative filtering finds similar users
7. [ ] Caching improves performance
8. [ ] Already-purchased events are excluded
9. [ ] Empty state handled for new users
10. [ ] Interaction tracking works
