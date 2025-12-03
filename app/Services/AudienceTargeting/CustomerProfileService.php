<?php

namespace App\Services\AudienceTargeting;

use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\AnalyticsEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerProfileService
{
    /**
     * Build or update a customer profile
     */
    public function buildProfile(Customer $customer, Tenant $tenant): CustomerProfile
    {
        $profile = CustomerProfile::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
            ],
            $this->calculateProfileData($customer, $tenant)
        );

        return $profile;
    }

    /**
     * Rebuild all profiles for a tenant
     */
    public function rebuildAllProfiles(Tenant $tenant, ?callable $progressCallback = null): int
    {
        $customers = $tenant->customers()->get();
        $count = 0;

        foreach ($customers as $customer) {
            $this->buildProfile($customer, $tenant);
            $count++;

            if ($progressCallback) {
                $progressCallback($count, $customers->count());
            }
        }

        return $count;
    }

    /**
     * Rebuild profiles for customers with recent activity
     */
    public function rebuildRecentlyActiveProfiles(Tenant $tenant, int $days = 30): int
    {
        $customerIds = Order::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->distinct()
            ->pluck('customer_id');

        $analyticsCustomerIds = AnalyticsEvent::where('tenant_id', $tenant->id)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->whereNotNull('session_id')
            ->distinct()
            ->pluck('session_id');

        // Combine both sources
        $customers = Customer::whereIn('id', $customerIds)
            ->orWhereIn('id', function ($query) use ($tenant, $analyticsCustomerIds) {
                // Map session IDs to customer IDs if possible
                $query->select('id')
                    ->from('customers')
                    ->whereIn('id', $analyticsCustomerIds);
            })
            ->get();

        $count = 0;
        foreach ($customers as $customer) {
            $this->buildProfile($customer, $tenant);
            $count++;
        }

        return $count;
    }

    /**
     * Calculate all profile data for a customer
     */
    protected function calculateProfileData(Customer $customer, Tenant $tenant): array
    {
        $purchaseMetrics = $this->calculatePurchaseMetrics($customer, $tenant);
        $preferences = $this->calculatePreferences($customer, $tenant);
        $engagement = $this->calculateEngagementMetrics($customer, $tenant);
        $locationData = $this->extractLocationData($customer);

        return [
            // Purchase metrics
            'purchase_count' => $purchaseMetrics['count'],
            'total_spent_cents' => $purchaseMetrics['total_cents'],
            'avg_order_cents' => $purchaseMetrics['avg_cents'],
            'first_purchase_at' => $purchaseMetrics['first_at'],
            'last_purchase_at' => $purchaseMetrics['last_at'],

            // Preferences
            'preferred_genres' => $preferences['genres'],
            'preferred_event_types' => $preferences['event_types'],
            'preferred_price_range' => $preferences['price_range'],
            'preferred_days' => $preferences['days'],
            'attended_events' => $preferences['attended_events'],

            // Engagement
            'engagement_score' => $engagement['score'],
            'churn_risk' => $engagement['churn_risk'],
            'page_views_30d' => $engagement['page_views'],
            'cart_adds_30d' => $engagement['cart_adds'],
            'email_opens_30d' => $engagement['email_opens'],
            'email_clicks_30d' => $engagement['email_clicks'],

            // Location
            'location_data' => $locationData,

            // Metadata
            'last_calculated_at' => now(),
        ];
    }

    /**
     * Calculate purchase metrics
     */
    protected function calculatePurchaseMetrics(Customer $customer, Tenant $tenant): array
    {
        $orders = Order::where('customer_id', $customer->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->get();

        if ($orders->isEmpty()) {
            return [
                'count' => 0,
                'total_cents' => 0,
                'avg_cents' => 0,
                'first_at' => null,
                'last_at' => null,
            ];
        }

        $totalCents = $orders->sum('total_cents');
        $count = $orders->count();

        return [
            'count' => $count,
            'total_cents' => $totalCents,
            'avg_cents' => $count > 0 ? (int) round($totalCents / $count) : 0,
            'first_at' => $orders->min('created_at'),
            'last_at' => $orders->max('created_at'),
        ];
    }

    /**
     * Calculate customer preferences from purchase history
     */
    protected function calculatePreferences(Customer $customer, Tenant $tenant): array
    {
        // Get all tickets purchased by this customer for this tenant
        $ticketData = DB::table('tickets')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->where('orders.customer_id', $customer->id)
            ->where('orders.tenant_id', $tenant->id)
            ->where('orders.status', 'completed')
            ->select(
                'events.id as event_id',
                'ticket_types.price_cents',
                'orders.created_at',
                DB::raw('DAYNAME(events.event_date) as day_of_week')
            )
            ->get();

        $attendedEvents = $ticketData->pluck('event_id')->unique()->values()->toArray();
        $prices = $ticketData->pluck('price_cents')->filter();

        // Calculate genre preferences
        $genreWeights = $this->calculateGenreWeights($attendedEvents);

        // Calculate event type preferences
        $eventTypeWeights = $this->calculateEventTypeWeights($attendedEvents);

        // Calculate preferred days
        $dayWeights = $this->calculateDayWeights($ticketData);

        // Calculate price range
        $priceRange = null;
        if ($prices->isNotEmpty()) {
            $priceRange = [
                'min' => (int) max(0, $prices->min() * 0.7), // 30% below lowest
                'max' => (int) ($prices->max() * 1.3), // 30% above highest
            ];
        }

        return [
            'genres' => $genreWeights,
            'event_types' => $eventTypeWeights,
            'price_range' => $priceRange,
            'days' => $dayWeights,
            'attended_events' => $attendedEvents,
        ];
    }

    /**
     * Calculate genre weights from attended events
     */
    protected function calculateGenreWeights(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $genreCounts = DB::table('event_event_genre')
            ->join('event_genres', 'event_event_genre.event_genre_id', '=', 'event_genres.id')
            ->whereIn('event_event_genre.event_id', $eventIds)
            ->groupBy('event_genres.id', 'event_genres.slug')
            ->select('event_genres.slug', DB::raw('COUNT(*) as count'))
            ->get();

        $total = $genreCounts->sum('count');

        if ($total === 0) {
            return [];
        }

        return $genreCounts->map(fn ($g) => [
            'slug' => $g->slug,
            'weight' => round($g->count / $total, 2),
        ])->sortByDesc('weight')->values()->toArray();
    }

    /**
     * Calculate event type weights from attended events
     */
    protected function calculateEventTypeWeights(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $typeCounts = DB::table('event_event_type')
            ->join('event_types', 'event_event_type.event_type_id', '=', 'event_types.id')
            ->whereIn('event_event_type.event_id', $eventIds)
            ->groupBy('event_types.id', 'event_types.slug')
            ->select('event_types.slug', DB::raw('COUNT(*) as count'))
            ->get();

        $total = $typeCounts->sum('count');

        if ($total === 0) {
            return [];
        }

        return $typeCounts->map(fn ($t) => [
            'slug' => $t->slug,
            'weight' => round($t->count / $total, 2),
        ])->sortByDesc('weight')->values()->toArray();
    }

    /**
     * Calculate preferred days of week
     */
    protected function calculateDayWeights(Collection $ticketData): array
    {
        $days = $ticketData->pluck('day_of_week')->filter();

        if ($days->isEmpty()) {
            return [];
        }

        $counts = $days->countBy();
        $total = $days->count();

        return $counts->map(fn ($count, $day) => strtolower($day))
            ->filter(fn ($day, $count) => ($count / $total) >= 0.2) // At least 20%
            ->keys()
            ->values()
            ->toArray();
    }

    /**
     * Calculate engagement metrics
     */
    protected function calculateEngagementMetrics(Customer $customer, Tenant $tenant): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        // Get analytics events for last 30 days
        $events = AnalyticsEvent::where('tenant_id', $tenant->id)
            ->where('occurred_at', '>=', $thirtyDaysAgo)
            // Note: This is a simplification - in production you'd need better session-to-customer mapping
            ->get();

        $pageViews = $events->where('event_type', AnalyticsEvent::TYPE_PAGE_VIEW)->count();
        $cartAdds = $events->where('event_type', AnalyticsEvent::TYPE_CART_ADD)->count();

        // Calculate engagement score (0-100)
        $engagementScore = $this->calculateEngagementScore($customer, $tenant, [
            'page_views' => $pageViews,
            'cart_adds' => $cartAdds,
        ]);

        // Calculate churn risk (0-100)
        $churnRisk = $this->calculateChurnRisk($customer, $tenant);

        return [
            'score' => $engagementScore,
            'churn_risk' => $churnRisk,
            'page_views' => $pageViews,
            'cart_adds' => $cartAdds,
            'email_opens' => 0, // Would need email tracking integration
            'email_clicks' => 0, // Would need email tracking integration
        ];
    }

    /**
     * Calculate engagement score (0-100)
     */
    protected function calculateEngagementScore(Customer $customer, Tenant $tenant, array $metrics): int
    {
        $score = 0;

        // Recent purchase (max 30 points)
        $lastOrder = Order::where('customer_id', $customer->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($lastOrder) {
            $daysSinceOrder = $lastOrder->created_at->diffInDays(now());
            if ($daysSinceOrder <= 30) {
                $score += 30;
            } elseif ($daysSinceOrder <= 60) {
                $score += 20;
            } elseif ($daysSinceOrder <= 90) {
                $score += 10;
            }
        }

        // Purchase frequency (max 25 points)
        $orderCount = Order::where('customer_id', $customer->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subYear())
            ->count();

        $score += min(25, $orderCount * 5);

        // Page views (max 20 points)
        $score += min(20, (int) ($metrics['page_views'] * 2));

        // Cart activity (max 15 points)
        $score += min(15, $metrics['cart_adds'] * 5);

        // Watchlist (max 10 points)
        $watchlistCount = $customer->watchlist()
            ->where('tenant_id', $tenant->id)
            ->count();
        $score += min(10, $watchlistCount * 2);

        return min(100, $score);
    }

    /**
     * Calculate churn risk (0-100)
     */
    protected function calculateChurnRisk(Customer $customer, Tenant $tenant): int
    {
        $lastOrder = Order::where('customer_id', $customer->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        // No orders = neutral risk
        if (!$lastOrder) {
            return 50;
        }

        $daysSinceOrder = $lastOrder->created_at->diffInDays(now());

        // Calculate based on recency
        if ($daysSinceOrder <= 30) {
            return 10;
        } elseif ($daysSinceOrder <= 60) {
            return 25;
        } elseif ($daysSinceOrder <= 90) {
            return 40;
        } elseif ($daysSinceOrder <= 180) {
            return 60;
        } elseif ($daysSinceOrder <= 365) {
            return 80;
        }

        return 95; // Very high risk after 1 year
    }

    /**
     * Extract location data from customer
     */
    protected function extractLocationData(Customer $customer): ?array
    {
        if (!$customer->city && !$customer->country) {
            return null;
        }

        return [
            'city' => $customer->city,
            'country' => $customer->country,
            'lat' => null, // Could be geocoded
            'lng' => null, // Could be geocoded
        ];
    }

    /**
     * Get profiles matching specific criteria
     */
    public function getMatchingProfiles(Tenant $tenant, array $criteria): Collection
    {
        $query = CustomerProfile::where('tenant_id', $tenant->id);

        foreach ($criteria as $criterion) {
            $field = $criterion['field'] ?? null;
            $operator = $criterion['operator'] ?? '=';
            $value = $criterion['value'] ?? null;

            if (!$field) {
                continue;
            }

            $query = $this->applyCriterion($query, $field, $operator, $value);
        }

        return $query->get();
    }

    /**
     * Apply a single criterion to a query
     */
    protected function applyCriterion($query, string $field, string $operator, $value)
    {
        return match ($operator) {
            '=' => $query->where($field, $value),
            '!=' => $query->where($field, '!=', $value),
            '>' => $query->where($field, '>', $value),
            '>=' => $query->where($field, '>=', $value),
            '<' => $query->where($field, '<', $value),
            '<=' => $query->where($field, '<=', $value),
            'between' => $query->whereBetween($field, $value),
            'in' => $query->whereIn($field, $value),
            'not_in' => $query->whereNotIn($field, $value),
            'includes' => $query->whereJsonContains($field, $value),
            'within_days' => $query->where($field, '>=', now()->subDays($value)),
            default => $query,
        };
    }
}
