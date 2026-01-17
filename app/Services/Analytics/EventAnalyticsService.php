<?php

namespace App\Services\Analytics;

use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\EventMilestone;
use App\Models\EventAnalyticsDaily;
use App\Models\EventAnalyticsHourly;
use App\Models\EventAnalyticsWeekly;
use App\Models\EventAnalyticsMonthly;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class EventAnalyticsService
{
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * Check if this is a marketplace event
     */
    protected function isMarketplaceEvent(Event|MarketplaceEvent $event): bool
    {
        return $event instanceof MarketplaceEvent;
    }

    /**
     * Get the order query column name based on event type
     * Event model uses event_id, MarketplaceEvent uses marketplace_event_id
     */
    protected function getOrderColumn(Event|MarketplaceEvent $event): string
    {
        return $this->isMarketplaceEvent($event) ? 'marketplace_event_id' : 'event_id';
    }

    /**
     * Get base order query for the event
     */
    protected function getOrdersQuery(Event|MarketplaceEvent $event)
    {
        $column = $this->getOrderColumn($event);
        return Order::where($column, $event->id);
    }

    /**
     * Get base tracking query for CoreCustomerEvent
     * For marketplace events, checks BOTH event_id and marketplace_event_id for backwards compatibility
     * (Old tracking data was saved to event_id, new data uses marketplace_event_id)
     */
    protected function getTrackingQuery(Event|MarketplaceEvent $event)
    {
        if ($this->isMarketplaceEvent($event)) {
            // Check both columns for backwards compatibility
            return CoreCustomerEvent::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)
                  ->orWhere('marketplace_event_id', $event->id);
            });
        }
        return CoreCustomerEvent::where('event_id', $event->id);
    }

    /**
     * Get complete dashboard data for an event
     */
    public function getDashboardData(Event|MarketplaceEvent $event, string $period = '30d'): array
    {
        $prefix = $this->isMarketplaceEvent($event) ? 'mp_' : '';
        $cacheKey = "{$prefix}event_analytics_{$event->id}_{$period}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($event, $period) {
            $isMarketplace = $this->isMarketplaceEvent($event);
            $dateRange = $this->getDateRange(
                $period,
                $isMarketplace ? null : $event,
                $isMarketplace ? $event : null
            );

            return [
                'overview' => $this->getOverviewStats($event, $dateRange),
                'chart_data' => $this->getChartData($event, $dateRange),
                'ticket_performance' => $this->getTicketPerformance($event, $dateRange),
                'traffic_sources' => $this->getTrafficSources($event, $dateRange),
                'top_locations' => $this->getTopLocations($event, $dateRange),
                'milestones' => $this->getMilestonesWithMetrics($event),
                'funnel' => $this->getFunnelMetrics($event, $dateRange),
            ];
        });
    }

    /**
     * Get overview stats for the stat cards
     */
    public function getOverviewStats(Event|MarketplaceEvent $event, array $dateRange): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);
        $orderColumn = $this->getOrderColumn($event);

        // Orders query - use correct column based on event type
        $orders = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed']);

        $totalRevenue = (clone $orders)->sum('total');

        // Tickets sold - for marketplace events, query by marketplace_event_id directly
        if ($isMarketplace) {
            $ticketsSold = Ticket::where('marketplace_event_id', $event->id)
                ->whereIn('status', ['valid', 'checked_in'])
                ->count();

            $ticketsToday = Ticket::where('marketplace_event_id', $event->id)
                ->whereIn('status', ['valid', 'checked_in'])
                ->whereDate('created_at', today())
                ->count();
        } else {
            $ticketsSold = Ticket::whereHas('ticketType', fn($q) => $q->where('event_id', $event->id))
                ->whereIn('status', ['valid', 'checked_in'])
                ->count();

            $ticketsToday = Ticket::whereHas('ticketType', fn($q) => $q->where('event_id', $event->id))
                ->whereIn('status', ['valid', 'checked_in'])
                ->whereDate('created_at', today())
                ->count();
        }

        // Get visits from tracking - use helper for backwards compatibility
        // Include page_view events OR any records without event_type (for legacy data)
        $visits = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at'); // Include records with null created_at
            })
            ->count();

        $uniqueVisitors = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Conversion rate
        $purchases = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->count();

        $conversionRate = $uniqueVisitors > 0 ? round(($purchases / $uniqueVisitors) * 100, 2) : 0;

        // Revenue change (compare to previous period)
        $periodDays = $dateRange['start']->diffInDays($dateRange['end']);
        $previousStart = $dateRange['start']->copy()->subDays($periodDays);
        $previousEnd = $dateRange['start']->copy()->subDay();

        $previousRevenue = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('total');

        $revenueChange = $previousRevenue > 0
            ? round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : 0;

        // Capacity - MarketplaceEvent uses capacity directly
        $capacity = $isMarketplace ? ($event->capacity ?? 0) : ($event->total_capacity ?? 0);
        $revenueTarget = $event->revenue_target ?? ($capacity * 100); // Default estimate

        // Event info - different field names for MarketplaceEvent
        $startDate = $isMarketplace ? $event->starts_at : $event->start_date;
        $daysUntil = $startDate ? (int) round(now()->diffInDays($startDate, false)) : 0;
        $statusLabel = $isMarketplace ? $event->status : ($event->status_label ?? $event->status);

        return [
            'revenue' => [
                'total' => $totalRevenue,
                'target' => $revenueTarget,
                'change' => $revenueChange,
                'progress' => $revenueTarget > 0 ? round(($totalRevenue / $revenueTarget) * 100, 1) : 0,
            ],
            'tickets' => [
                'sold' => $ticketsSold,
                'capacity' => $capacity,
                'today' => $ticketsToday,
                'progress' => $capacity > 0 ? round(($ticketsSold / $capacity) * 100, 1) : 0,
            ],
            'visits' => [
                'total' => $visits,
                'unique' => $uniqueVisitors,
            ],
            'conversion' => [
                'rate' => $conversionRate,
                'purchases' => $purchases,
            ],
            'event' => [
                'days_until' => $daysUntil,
                'status' => $statusLabel,
                'date' => $startDate?->format('d M Y'),
            ],
        ];
    }

    /**
     * Get chart data for performance overview
     */
    public function getChartData(Event|MarketplaceEvent $event, array $dateRange): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);

        $days = [];
        $current = $dateRange['start']->copy();

        while ($current->lte($dateRange['end'])) {
            $days[] = $current->format('Y-m-d');
            $current->addDay();
        }

        // Get daily analytics if available (only for tenant events)
        $dailyData = collect();
        if (!$isMarketplace) {
            $dailyData = EventAnalyticsDaily::forEvent($event->id)
                ->inDateRange($dateRange['start'], $dateRange['end'])
                ->get()
                ->keyBy(fn($d) => $d->date->format('Y-m-d'));
        }

        // Fallback: calculate from raw data
        $revenueByDay = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // Get tickets by day
        if ($isMarketplace) {
            $ticketsByDay = Ticket::where('marketplace_event_id', $event->id)
                ->whereIn('status', ['valid', 'checked_in'])
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as tickets')
                ->groupBy('date')
                ->get()
                ->keyBy('date');
        } else {
            $ticketsByDay = Ticket::whereHas('ticketType', fn($q) => $q->where('event_id', $event->id))
                ->whereIn('status', ['valid', 'checked_in'])
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as tickets')
                ->groupBy('date')
                ->get()
                ->keyBy('date');
        }

        // Use helper for backwards compatibility with tracking data
        // Include page_view events OR any records without event_type (for legacy data)
        $visitsByDay = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->whereNotNull('created_at')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as visits')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $chartData = [];
        foreach ($days as $day) {
            $dailyRecord = $dailyData->get($day);

            $chartData[] = [
                'date' => Carbon::parse($day)->format('d M'),
                'full_date' => Carbon::parse($day)->format('l, F d'),
                'raw_date' => $day,
                'revenue' => $dailyRecord?->revenue ?? ($revenueByDay->get($day)?->revenue ?? 0),
                'tickets' => $dailyRecord?->tickets_sold ?? ($ticketsByDay->get($day)?->tickets ?? 0),
                'visits' => $dailyRecord?->page_views ?? ($visitsByDay->get($day)?->visits ?? 0),
            ];
        }

        return $chartData;
    }

    /**
     * Get ticket performance breakdown
     */
    public function getTicketPerformance(Event|MarketplaceEvent $event, array $dateRange): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);

        // Define colors for ticket types
        $colors = ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899', '#06b6d4', '#ef4444'];

        // Get ticket types based on event type
        if ($isMarketplace) {
            $ticketTypes = \App\Models\MarketplaceTicketType::where('marketplace_event_id', $event->id)->get();
            $ticketColumn = 'marketplace_ticket_type_id';
        } else {
            // For Event model, try ticket types first
            $ticketTypes = TicketType::where('event_id', $event->id)->get();
            $ticketColumn = 'ticket_type_id';

            // If no ticket types found, query tickets by event_id directly and group
            if ($ticketTypes->isEmpty()) {
                $tickets = Ticket::where('event_id', $event->id)
                    ->whereIn('status', ['valid', 'checked_in'])
                    ->get();

                if ($tickets->isNotEmpty()) {
                    $grouped = $tickets->groupBy('ticket_type_id');
                    $performance = [];
                    $index = 0;

                    foreach ($grouped as $typeId => $typeTickets) {
                        $sold = $typeTickets->count();
                        $revenue = $typeTickets->sum('price');
                        $avgPrice = $sold > 0 ? round($revenue / $sold, 2) : 0;
                        $ticketType = $typeId ? $typeTickets->first()->ticketType : null;
                        $name = $ticketType?->name ?? 'General';

                        $performance[] = [
                            'id' => $typeId ?? 0,
                            'name' => $name,
                            'price' => $avgPrice,
                            'sold' => $sold,
                            'revenue' => $revenue,
                            'conversion_rate' => 0,
                            'trend' => 0,
                            'color' => $colors[$index % count($colors)],
                        ];
                        $index++;
                    }

                    usort($performance, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
                    return $performance;
                }
            }
        }

        $performance = [];

        foreach ($ticketTypes as $index => $ticketType) {
            // Query tickets - use sum of actual ticket prices for accurate revenue
            if ($isMarketplace) {
                $ticketsQuery = Ticket::where('marketplace_ticket_type_id', $ticketType->id)
                    ->whereIn('status', ['valid', 'checked_in']);
            } else {
                $ticketsQuery = Ticket::where('ticket_type_id', $ticketType->id)
                    ->whereIn('status', ['valid', 'checked_in']);
            }

            $sold = (clone $ticketsQuery)->count();
            // Sum actual ticket prices for accurate revenue
            $revenue = (clone $ticketsQuery)->sum('price');
            $price = $ticketType->price ?? ($sold > 0 ? round($revenue / $sold, 2) : 0);

            // Calculate conversion rate for this ticket type - use helper for backwards compatibility
            $addToCartCount = (clone $this->getTrackingQuery($event))
                ->where('event_type', CoreCustomerEvent::TYPE_ADD_TO_CART)
                ->where('content_id', $ticketType->id)
                ->where(function ($q) use ($dateRange) {
                    $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                      ->orWhereNull('created_at');
                })
                ->count();

            $purchaseCount = (clone $this->getTrackingQuery($event))
                ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
                ->where('content_id', $ticketType->id)
                ->where(function ($q) use ($dateRange) {
                    $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                      ->orWhereNull('created_at');
                })
                ->count();

            $conversionRate = $addToCartCount > 0 ? round(($purchaseCount / $addToCartCount) * 100, 1) : 0;

            // Calculate trend (compare to previous period)
            $periodDays = $dateRange['start']->diffInDays($dateRange['end']);
            $previousSold = Ticket::where($ticketColumn, $ticketType->id)
                ->whereIn('status', ['valid', 'checked_in'])
                ->whereBetween('created_at', [
                    $dateRange['start']->copy()->subDays($periodDays),
                    $dateRange['start']->copy()->subDay()
                ])
                ->count();

            $trend = $previousSold > 0 ? round((($sold - $previousSold) / $previousSold) * 100, 0) : 0;

            $performance[] = [
                'id' => $ticketType->id,
                'name' => $ticketType->name ?? 'General',
                'price' => $price,
                'sold' => $sold,
                'revenue' => $revenue,
                'conversion_rate' => $conversionRate,
                'trend' => $trend,
                'color' => $colors[$index % count($colors)],
            ];
        }

        // Sort by revenue descending
        usort($performance, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $performance;
    }

    /**
     * Get traffic sources breakdown
     */
    public function getTrafficSources(Event|MarketplaceEvent $event, array $dateRange): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);

        $sourceCase = "
            CASE
                WHEN fbclid IS NOT NULL OR utm_source = 'facebook' THEN 'Facebook'
                WHEN gclid IS NOT NULL OR utm_source = 'google' THEN 'Google'
                WHEN utm_source = 'instagram' OR referrer LIKE '%instagram%' THEN 'Instagram'
                WHEN ttclid IS NOT NULL OR utm_source = 'tiktok' THEN 'TikTok'
                WHEN utm_medium = 'email' THEN 'Email'
                WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                ELSE 'Organic'
            END
        ";

        // Use helper for backwards compatibility
        $sources = (clone $this->getTrackingQuery($event))
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->selectRaw("
                {$sourceCase} as source,
                COUNT(DISTINCT visitor_id) as visitors,
                COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) as conversions,
                SUM(CASE WHEN event_type = 'purchase' THEN event_value ELSE 0 END) as revenue
            ")
            ->groupByRaw($sourceCase)
            ->orderByDesc('visitors')
            ->get();

        $totalVisitors = $sources->sum('visitors');

        $sourceConfig = [
            'Facebook' => ['icon' => '📘', 'color' => '#1877f2'],
            'Google' => ['icon' => '🔍', 'color' => '#ea4335'],
            'Instagram' => ['icon' => '📸', 'color' => '#e4405f'],
            'TikTok' => ['icon' => '🎵', 'color' => '#000000'],
            'Email' => ['icon' => '📧', 'color' => '#f59e0b'],
            'Direct' => ['icon' => '🔗', 'color' => '#6b7280'],
            'Organic' => ['icon' => '🌱', 'color' => '#22c55e'],
        ];

        return $sources->map(function ($source) use ($totalVisitors, $sourceConfig) {
            $config = $sourceConfig[$source->source] ?? ['icon' => '🔗', 'color' => '#6b7280'];

            return [
                'name' => $source->source,
                'icon' => $config['icon'],
                'color' => $config['color'],
                'visitors' => $source->visitors,
                'percent' => $totalVisitors > 0 ? round(($source->visitors / $totalVisitors) * 100, 0) : 0,
                'conversions' => $source->conversions,
                'revenue' => $source->revenue ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get top locations
     */
    public function getTopLocations(Event|MarketplaceEvent $event, array $dateRange, int $limit = 10): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);

        // Country flags mapping
        $flags = [
            'Romania' => '🇷🇴', 'RO' => '🇷🇴',
            'Hungary' => '🇭🇺', 'HU' => '🇭🇺',
            'Austria' => '🇦🇹', 'AT' => '🇦🇹',
            'Germany' => '🇩🇪', 'DE' => '🇩🇪',
            'United Kingdom' => '🇬🇧', 'GB' => '🇬🇧',
            'France' => '🇫🇷', 'FR' => '🇫🇷',
            'Italy' => '🇮🇹', 'IT' => '🇮🇹',
            'Spain' => '🇪🇸', 'ES' => '🇪🇸',
            'Netherlands' => '🇳🇱', 'NL' => '🇳🇱',
            'Belgium' => '🇧🇪', 'BE' => '🇧🇪',
            'Poland' => '🇵🇱', 'PL' => '🇵🇱',
            'Czech Republic' => '🇨🇿', 'CZ' => '🇨🇿',
            'Bulgaria' => '🇧🇬', 'BG' => '🇧🇬',
            'Moldova' => '🇲🇩', 'MD' => '🇲🇩',
            'Ukraine' => '🇺🇦', 'UA' => '🇺🇦',
            'Serbia' => '🇷🇸', 'RS' => '🇷🇸',
            'United States' => '🇺🇸', 'US' => '🇺🇸',
        ];

        // Use helper for backwards compatibility
        $locations = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->whereNotNull('city')
            ->selectRaw("
                city,
                country_code,
                COUNT(*) as tickets,
                SUM(event_value) as revenue
            ")
            ->groupBy('city', 'country_code')
            ->orderByDesc('tickets')
            ->limit($limit)
            ->get();

        // Get country names
        $countryNames = [
            'RO' => 'Romania', 'HU' => 'Hungary', 'AT' => 'Austria',
            'DE' => 'Germany', 'GB' => 'United Kingdom', 'FR' => 'France',
            'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands',
            'BE' => 'Belgium', 'PL' => 'Poland', 'CZ' => 'Czech Republic',
            'BG' => 'Bulgaria', 'MD' => 'Moldova', 'UA' => 'Ukraine',
            'RS' => 'Serbia', 'US' => 'United States',
        ];

        return $locations->map(function ($loc) use ($flags, $countryNames) {
            $countryCode = $loc->country_code ?? 'RO';
            $country = $countryNames[$countryCode] ?? $countryCode;

            return [
                'city' => $loc->city,
                'country' => $country,
                'country_code' => $countryCode,
                'flag' => $flags[$countryCode] ?? $flags[$country] ?? '🌍',
                'tickets' => $loc->tickets,
                'revenue' => $loc->revenue ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get milestones with calculated metrics
     */
    public function getMilestonesWithMetrics(Event|MarketplaceEvent $event): array
    {
        // For marketplace events, query by event_id (which stores the marketplace event id)
        $milestones = EventMilestone::where('event_id', $event->id)
            ->orderBy('start_date', 'desc')
            ->get();

        return $milestones->map(function ($milestone) {
            return [
                'id' => $milestone->id,
                'type' => $milestone->type,
                'title' => $milestone->title,
                'description' => $milestone->description,
                'start_date' => $milestone->start_date?->format('Y-m-d'), // ISO format for JS
                'start_date_formatted' => $milestone->start_date?->format('M d, Y'),
                'end_date' => $milestone->end_date?->format('Y-m-d'),
                'end_date_formatted' => $milestone->end_date?->format('M d, Y'),
                'budget' => $milestone->budget,
                'currency' => $milestone->currency,
                'targeting' => $milestone->targeting,
                'attributed_revenue' => $milestone->attributed_revenue,
                'conversions' => $milestone->conversions,
                'cac' => $milestone->cac,
                'roi' => $milestone->roi,
                'impact' => $milestone->impact_metric,
                'is_active' => $milestone->is_active,
                'icon' => $milestone->getTypeIcon(),
                'color' => $milestone->getTypeColor(),
                'label' => $milestone->getTypeLabel(),
            ];
        })->toArray();
    }

    /**
     * Get funnel metrics
     */
    public function getFunnelMetrics(Event|MarketplaceEvent $event, array $dateRange): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);

        // Use helper for backwards compatibility
        // Include page_view events OR any records without event_type (for legacy data)
        $pageViews = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->count();

        $uniqueVisitors = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->distinct('visitor_id')
            ->count('visitor_id');

        $addToCart = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_ADD_TO_CART)
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->distinct('session_id')
            ->count('session_id');

        $checkoutStarted = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_BEGIN_CHECKOUT)
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->distinct('session_id')
            ->count('session_id');

        $purchases = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->where(function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                  ->orWhereNull('created_at');
            })
            ->count();

        return [
            'page_views' => $pageViews,
            'unique_visitors' => $uniqueVisitors,
            'add_to_cart' => $addToCart,
            'checkout_started' => $checkoutStarted,
            'purchases' => $purchases,
            'view_to_cart_rate' => $uniqueVisitors > 0 ? round(($addToCart / $uniqueVisitors) * 100, 2) : 0,
            'cart_to_checkout_rate' => $addToCart > 0 ? round(($checkoutStarted / $addToCart) * 100, 2) : 0,
            'checkout_to_purchase_rate' => $checkoutStarted > 0 ? round(($purchases / $checkoutStarted) * 100, 2) : 0,
            'overall_conversion_rate' => $uniqueVisitors > 0 ? round(($purchases / $uniqueVisitors) * 100, 2) : 0,
        ];
    }

    /**
     * Get recent sales for the event
     */
    public function getRecentSales(Event|MarketplaceEvent $event, int $limit = 20): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);

        $orders = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->with(['marketplaceCustomer', 'tickets.ticketType', 'tickets.marketplaceTicketType'])
            ->orderBy('paid_at', 'desc')
            ->limit($limit)
            ->get();

        return $orders->map(function ($order) use ($isMarketplace) {
            $customer = $order->marketplaceCustomer;
            $name = $customer?->name ?? $order->customer_name ?? 'Anonymous';
            $nameParts = explode(' ', $name);
            $initials = count($nameParts) >= 2
                ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
                : strtoupper(substr($name, 0, 2));

            // Mask email
            $email = $order->customer_email ?? '';
            $maskedEmail = $email ? substr($email, 0, 1) . '***@' . explode('@', $email)[1] ?? '' : '';

            // Get ticket info - for marketplace events, use marketplaceTicketType
            $tickets = $order->tickets;
            if ($isMarketplace) {
                $ticketType = $tickets->first()?->marketplaceTicketType?->name ?? 'General';
            } else {
                $ticketType = $tickets->first()?->ticketType?->name ?? 'General';
            }
            $quantity = $tickets->count();

            // Determine source from metadata or tracking
            $source = $order->metadata['source'] ?? $order->source ?? 'Direct';

            // Get payment info
            $paymentMethod = $order->payment_processor ?? 'Card';
            $paymentIcon = match (strtolower($paymentMethod)) {
                'stripe', 'card' => '💳',
                'apple_pay' => '🍎',
                'google_pay' => '🔵',
                'paypal' => '💙',
                'bank_transfer' => '🏦',
                default => '💳',
            };

            // Check if returning customer
            $isReturning = $customer ? Order::where('marketplace_customer_id', $customer->id)
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->where('id', '!=', $order->id)
                ->exists() : false;

            return [
                'id' => $order->id,
                'name' => $this->maskName($name),
                'initials' => $initials,
                'email' => $maskedEmail,
                'date' => $order->paid_at?->format('M d, Y H:i') ?? $order->created_at->format('M d, Y H:i'),
                'quantity' => $quantity,
                'ticket_type' => $ticketType,
                'source' => $this->formatSource($source),
                'amount' => $order->total,
                'payment_method' => $paymentMethod,
                'payment_icon' => $paymentIcon,
                'is_returning' => $isReturning,
                'customer_id' => $customer?->id,
            ];
        })->toArray();
    }

    /**
     * Get live visitors count
     */
    public function getLiveVisitors(Event|MarketplaceEvent $event): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);
        $fiveMinutesAgo = now()->subMinutes(5);

        // Use helper for backwards compatibility
        // Include page_view events OR any records without event_type (for legacy data)
        $visitors = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($fiveMinutesAgo) {
                $q->where('created_at', '>=', $fiveMinutesAgo)
                  ->orWhereNull('created_at');
            })
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Get visitor locations
        // Include page_view events OR any records without event_type (for legacy data)
        $locations = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($fiveMinutesAgo) {
                $q->where('created_at', '>=', $fiveMinutesAgo)
                  ->orWhereNull('created_at');
            })
            ->whereNotNull('city')
            ->selectRaw('city, country_code, COUNT(DISTINCT visitor_id) as count')
            ->groupBy('city', 'country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Get recent activity
        $recentActivity = (clone $this->getTrackingQuery($event))
            ->where(function ($q) use ($fiveMinutesAgo) {
                $q->where('created_at', '>=', $fiveMinutesAgo)
                  ->orWhereNull('created_at');
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($evt) {
                return [
                    'action' => $this->formatEventAction($evt),
                    'city' => $evt->city ?? 'Unknown',
                    'country' => $evt->country_code ?? 'Unknown',
                    'time' => $evt->created_at ? $evt->created_at->diffForHumans(short: true) : 'recently',
                ];
            });

        return [
            'count' => $visitors,
            'locations' => $locations->map(fn($loc) => [
                'city' => $loc->city,
                'country_code' => $loc->country_code,
                'count' => $loc->count,
            ])->toArray(),
            'activity' => $recentActivity->toArray(),
        ];
    }

    /**
     * Get live visitors for globe modal with detailed location data
     */
    public function getLiveVisitorsForGlobe(Event|MarketplaceEvent $event): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);
        $fiveMinutesAgo = now()->subMinutes(5);

        // Get recent sessions with location data - use helper for backwards compatibility
        // Include page_view events OR any records without event_type (for legacy data)
        $visitors = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($fiveMinutesAgo) {
                $q->where('created_at', '>=', $fiveMinutesAgo)
                  ->orWhereNull('created_at');
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('
                visitor_id,
                city,
                country_code,
                latitude,
                longitude,
                device_type,
                browser,
                page_path,
                MAX(created_at) as last_seen
            ')
            ->groupBy('visitor_id', 'city', 'country_code', 'latitude', 'longitude', 'device_type', 'browser', 'page_path')
            ->orderByDesc('last_seen')
            ->limit(50)
            ->get();

        $flags = [
            'RO' => '🇷🇴', 'HU' => '🇭🇺', 'AT' => '🇦🇹', 'DE' => '🇩🇪',
            'GB' => '🇬🇧', 'FR' => '🇫🇷', 'IT' => '🇮🇹', 'ES' => '🇪🇸',
            'NL' => '🇳🇱', 'BE' => '🇧🇪', 'PL' => '🇵🇱', 'CZ' => '🇨🇿',
        ];

        return $visitors->map(function ($v, $index) use ($flags) {
            // Generate avatar URL based on visitor_id hash
            $avatarSeed = abs(crc32($v->visitor_id ?? $index));

            return [
                'lng' => $v->longitude,
                'lat' => $v->latitude,
                'city' => $v->city ?? 'Unknown',
                'country' => $v->country_code ?? 'Unknown',
                'flag' => $flags[$v->country_code] ?? '🌍',
                'visitors' => 1,
                'type' => 'live',
                'name' => $this->generateAnonymousName($v->visitor_id),
                'avatar' => "https://api.dicebear.com/7.x/avataaars/svg?seed={$avatarSeed}",
                'device' => ucfirst($v->device_type ?? 'Desktop'),
                'browser' => $v->browser ?? 'Chrome',
                'page' => $v->page_path ?? '/',
                'session_time' => $v->last_seen ? now()->diffForHumans($v->last_seen, true) : '0 sec',
            ];
        })->toArray();
    }

    /**
     * Aggregate daily analytics for an event (tenant events only)
     */
    public function aggregateDailyAnalytics(Event|MarketplaceEvent $event, Carbon $date): ?EventAnalyticsDaily
    {
        // Aggregated analytics tables are only for tenant events
        if ($this->isMarketplaceEvent($event)) {
            return null;
        }

        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Page views
        $pageViews = CoreCustomerEvent::where('event_id', $event->id)
            ->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();

        // Unique visitors
        $uniqueVisitors = CoreCustomerEvent::where('event_id', $event->id)
            ->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Sessions
        $sessions = CoreCustomerEvent::where('event_id', $event->id)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->distinct('session_id')
            ->count('session_id');

        // Funnel metrics
        $addToCart = CoreCustomerEvent::where('event_id', $event->id)
            ->where('event_type', CoreCustomerEvent::TYPE_ADD_TO_CART)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->distinct('session_id')
            ->count('session_id');

        $checkoutStarted = CoreCustomerEvent::where('event_id', $event->id)
            ->where('event_type', CoreCustomerEvent::TYPE_BEGIN_CHECKOUT)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->distinct('session_id')
            ->count('session_id');

        $purchases = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereDate('paid_at', $date)
            ->count();

        // Revenue
        $revenue = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereDate('paid_at', $date)
            ->sum('total');

        // Tickets sold
        $ticketsSold = Ticket::whereHas('ticketType', fn($q) => $q->where('event_id', $event->id))
            ->whereIn('status', ['valid', 'checked_in'])
            ->whereDate('created_at', $date)
            ->count();

        // Conversion rate
        $conversionRate = $uniqueVisitors > 0 ? round(($purchases / $uniqueVisitors) * 100, 2) : 0;

        // Average order value
        $avgOrderValue = $purchases > 0 ? round($revenue / $purchases, 2) : 0;

        // Traffic sources
        $trafficSources = $this->getTrafficSources($event, ['start' => $startOfDay, 'end' => $endOfDay]);

        // Top locations
        $topLocations = $this->getTopLocations($event, ['start' => $startOfDay, 'end' => $endOfDay], 5);

        // Ticket breakdown
        $ticketBreakdown = TicketType::where('event_id', $event->id)
            ->get()
            ->map(function ($type) use ($date) {
                $sold = Ticket::where('ticket_type_id', $type->id)
                    ->whereIn('status', ['valid', 'checked_in'])
                    ->whereDate('created_at', $date)
                    ->count();

                return [
                    'ticket_type_id' => $type->id,
                    'name' => $type->name,
                    'sold' => $sold,
                    'revenue' => $sold * ($type->price ?? 0),
                ];
            })
            ->filter(fn($t) => $t['sold'] > 0)
            ->values()
            ->toArray();

        return EventAnalyticsDaily::updateOrCreate(
            ['event_id' => $event->id, 'date' => $date->toDateString()],
            [
                'tenant_id' => $event->tenant_id,
                'page_views' => $pageViews,
                'unique_visitors' => $uniqueVisitors,
                'sessions' => $sessions,
                'add_to_cart_count' => $addToCart,
                'checkout_started_count' => $checkoutStarted,
                'purchases_count' => $purchases,
                'conversion_rate' => $conversionRate,
                'revenue' => $revenue,
                'tickets_sold' => $ticketsSold,
                'avg_order_value' => $avgOrderValue,
                'traffic_sources' => $trafficSources,
                'top_locations' => $topLocations,
                'ticket_breakdown' => $ticketBreakdown,
            ]
        );
    }

    /**
     * Invalidate cache for event
     */
    public function invalidateCache(int $eventId): void
    {
        Cache::forget("event_analytics_{$eventId}_7d");
        Cache::forget("event_analytics_{$eventId}_30d");
        Cache::forget("event_analytics_{$eventId}_all");
        Cache::forget("event_analytics_realtime_{$eventId}");
        Cache::forget("event_analytics_overview_{$eventId}");
    }

    /**
     * Get real-time dashboard data (uses aggregated hourly data for performance)
     */
    public function getRealTimeDashboardData(Event|MarketplaceEvent $event): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);
        $prefix = $isMarketplace ? 'mp_' : '';
        $cacheKey = "{$prefix}event_analytics_realtime_{$event->id}";

        return Cache::remember($cacheKey, 60, function () use ($event, $isMarketplace) {
            $realTimeService = app(RealTimeAnalyticsService::class);

            return [
                'realtime' => $realTimeService->getRealtimeStats($event->id, $isMarketplace),
                'live_visitors' => $realTimeService->getLiveVisitorsCount($event->id, $isMarketplace),
                'hourly_chart' => $this->getTodayHourlyChart($event),
            ];
        });
    }

    /**
     * Get today's hourly chart data from aggregated hourly table
     */
    public function getTodayHourlyChart(Event|MarketplaceEvent $event): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);
        $today = now()->toDateString();
        $currentHour = now()->hour;

        // For marketplace events, compute hourly data from raw tracking
        if ($isMarketplace) {
            return $this->computeHourlyChartFromRaw($event, $today, $currentHour);
        }

        $hourlyData = EventAnalyticsHourly::where('event_id', $event->id)
            ->where('date', $today)
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $chart = [];
        for ($hour = 0; $hour <= $currentHour; $hour++) {
            $data = $hourlyData->get($hour);
            $chart[] = [
                'hour' => sprintf('%02d:00', $hour),
                'page_views' => $data?->page_views ?? 0,
                'unique_visitors' => $data?->unique_visitors ?? 0,
                'purchases' => $data?->purchases ?? 0,
                'revenue' => ($data?->revenue_cents ?? 0) / 100,
                'tickets_sold' => $data?->tickets_sold ?? 0,
            ];
        }

        return $chart;
    }

    /**
     * Compute hourly chart data from raw tracking (for marketplace events)
     */
    protected function computeHourlyChartFromRaw(MarketplaceEvent $event, string $date, int $upToHour): array
    {
        $chart = [];
        $startOfDay = Carbon::parse($date)->startOfDay();

        for ($hour = 0; $hour <= $upToHour; $hour++) {
            $hourStart = $startOfDay->copy()->addHours($hour);
            $hourEnd = $hourStart->copy()->addHour();

            // Use helper for backwards compatibility
            // Include page_view events OR any records without event_type (for legacy data)
            $pageViews = (clone $this->getTrackingQuery($event))
                ->where(function ($q) {
                    $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                      ->orWhereNull('event_type')
                      ->orWhere('event_type', '');
                })
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->count();

            $uniqueVisitors = (clone $this->getTrackingQuery($event))
                ->where(function ($q) {
                    $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                      ->orWhereNull('event_type')
                      ->orWhere('event_type', '');
                })
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->distinct('visitor_id')
                ->count('visitor_id');

            $purchases = (clone $this->getTrackingQuery($event))
                ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->count();

            $revenue = $this->getOrdersQuery($event)
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->whereBetween('paid_at', [$hourStart, $hourEnd])
                ->sum('total');

            $ticketsSold = Ticket::where('marketplace_event_id', $event->id)
                ->whereIn('status', ['valid', 'checked_in'])
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->count();

            $chart[] = [
                'hour' => sprintf('%02d:00', $hour),
                'page_views' => $pageViews,
                'unique_visitors' => $uniqueVisitors,
                'purchases' => $purchases,
                'revenue' => $revenue,
                'tickets_sold' => $ticketsSold,
            ];
        }

        return $chart;
    }

    /**
     * Get chart data optimized with aggregated data (falls back to raw if not available)
     */
    public function getOptimizedChartData(Event|MarketplaceEvent $event, array $dateRange, string $granularity = 'daily'): array
    {
        // For marketplace events, use raw chart data calculation
        if ($this->isMarketplaceEvent($event)) {
            return $this->getChartData($event, $dateRange);
        }

        return match ($granularity) {
            'hourly' => $this->getHourlyChartData($event, $dateRange),
            'weekly' => $this->getWeeklyChartData($event, $dateRange),
            'monthly' => $this->getMonthlyChartData($event, $dateRange),
            default => $this->getDailyChartData($event, $dateRange),
        };
    }

    /**
     * Get hourly chart data for a specific date range (tenant events only)
     */
    protected function getHourlyChartData(Event|MarketplaceEvent $event, array $dateRange): array
    {
        return EventAnalyticsHourly::where('event_id', $event->id)
            ->whereBetween('date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()])
            ->orderBy('date')
            ->orderBy('hour')
            ->get()
            ->map(fn ($h) => [
                'datetime' => Carbon::parse($h->date)->setHour($h->hour)->format('Y-m-d H:i'),
                'label' => Carbon::parse($h->date)->format('d M') . ' ' . sprintf('%02d:00', $h->hour),
                'page_views' => $h->page_views,
                'unique_visitors' => $h->unique_visitors,
                'purchases' => $h->purchases,
                'revenue' => $h->revenue_cents / 100,
                'tickets_sold' => $h->tickets_sold,
            ])
            ->toArray();
    }

    /**
     * Get daily chart data from aggregated table (tenant events only)
     */
    protected function getDailyChartData(Event|MarketplaceEvent $event, array $dateRange): array
    {
        $dailyData = EventAnalyticsDaily::where('event_id', $event->id)
            ->whereBetween('date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($d) => $d->date->format('Y-m-d'));

        // Fill in any missing days
        $days = [];
        $current = $dateRange['start']->copy();

        while ($current->lte($dateRange['end'])) {
            $dateKey = $current->format('Y-m-d');
            $data = $dailyData->get($dateKey);

            $days[] = [
                'date' => $current->format('d M'),
                'full_date' => $current->format('l, F d'),
                'raw_date' => $dateKey,
                'page_views' => $data?->page_views ?? 0,
                'unique_visitors' => $data?->unique_visitors ?? 0,
                'purchases' => $data?->purchases_count ?? $data?->purchases ?? 0,
                'revenue' => $data?->revenue ?? ($data?->revenue_cents ?? 0) / 100,
                'tickets_sold' => $data?->tickets_sold ?? 0,
                'conversion_rate' => $data?->conversion_rate ?? 0,
            ];

            $current->addDay();
        }

        return $days;
    }

    /**
     * Get weekly chart data from aggregated table (tenant events only)
     */
    protected function getWeeklyChartData(Event|MarketplaceEvent $event, array $dateRange): array
    {
        return EventAnalyticsWeekly::where('event_id', $event->id)
            ->whereBetween('week_start', [$dateRange['start']->startOfWeek()->toDateString(), $dateRange['end']->toDateString()])
            ->orderBy('week_start')
            ->get()
            ->map(fn ($w) => [
                'week' => $w->week_range,
                'week_start' => $w->week_start->format('Y-m-d'),
                'page_views' => $w->page_views,
                'unique_visitors' => $w->unique_visitors,
                'purchases' => $w->purchases,
                'revenue' => $w->revenue_cents / 100,
                'tickets_sold' => $w->tickets_sold,
                'conversion_rate' => $w->conversion_rate,
                'revenue_change' => $w->revenue_change_pct,
            ])
            ->toArray();
    }

    /**
     * Get monthly chart data from aggregated table (tenant events only)
     */
    protected function getMonthlyChartData(Event|MarketplaceEvent $event, array $dateRange): array
    {
        return EventAnalyticsMonthly::where('event_id', $event->id)
            ->whereBetween('month_start', [$dateRange['start']->startOfMonth()->toDateString(), $dateRange['end']->toDateString()])
            ->orderBy('month_start')
            ->get()
            ->map(fn ($m) => [
                'month' => $m->month_name,
                'month_start' => $m->month_start->format('Y-m-d'),
                'page_views' => $m->page_views,
                'unique_visitors' => $m->unique_visitors,
                'purchases' => $m->purchases,
                'revenue' => $m->revenue_cents / 100,
                'tickets_sold' => $m->tickets_sold,
                'conversion_rate' => $m->conversion_rate,
                'revenue_change' => $m->revenue_change_pct,
            ])
            ->toArray();
    }

    /**
     * Get aggregated traffic sources from daily data
     */
    public function getAggregatedTrafficSources(Event|MarketplaceEvent $event, array $dateRange): array
    {
        // For marketplace events, use raw calculation
        if ($this->isMarketplaceEvent($event)) {
            return $this->getTrafficSources($event, $dateRange);
        }

        $dailyData = EventAnalyticsDaily::where('event_id', $event->id)
            ->whereBetween('date', [$dateRange['start']->toDateString(), $dateRange['end']->toDateString()])
            ->get();

        if ($dailyData->isEmpty()) {
            // Fall back to raw calculation
            return $this->getTrafficSources($event, $dateRange);
        }

        // Merge traffic sources from all daily records
        $merged = [];
        foreach ($dailyData as $day) {
            $sources = $day->traffic_sources ?? [];
            foreach ($sources as $source) {
                $name = $source['name'] ?? 'Unknown';
                if (!isset($merged[$name])) {
                    $merged[$name] = [
                        'name' => $name,
                        'icon' => $source['icon'] ?? '🔗',
                        'color' => $source['color'] ?? '#6b7280',
                        'visitors' => 0,
                        'conversions' => 0,
                        'revenue' => 0,
                    ];
                }
                $merged[$name]['visitors'] += $source['visitors'] ?? 0;
                $merged[$name]['conversions'] += $source['conversions'] ?? 0;
                $merged[$name]['revenue'] += $source['revenue'] ?? 0;
            }
        }

        // Calculate percentages
        $totalVisitors = array_sum(array_column($merged, 'visitors'));
        foreach ($merged as &$source) {
            $source['percent'] = $totalVisitors > 0 ? round(($source['visitors'] / $totalVisitors) * 100, 0) : 0;
        }

        // Sort by visitors
        usort($merged, fn ($a, $b) => $b['visitors'] <=> $a['visitors']);

        return array_values($merged);
    }

    /**
     * Get period comparison stats (useful for trend analysis)
     */
    public function getPeriodComparison(Event|MarketplaceEvent $event, string $period = '7d'): array
    {
        $isMarketplace = $this->isMarketplaceEvent($event);
        $currentRange = $this->getDateRange(
            $period,
            $isMarketplace ? null : $event,
            $isMarketplace ? $event : null
        );
        $periodDays = $currentRange['start']->diffInDays($currentRange['end']);

        $previousRange = [
            'start' => $currentRange['start']->copy()->subDays($periodDays),
            'end' => $currentRange['start']->copy()->subDay(),
        ];

        // For marketplace events, compute from raw data
        if ($isMarketplace) {
            return $this->computePeriodComparisonFromRaw($event, $currentRange, $previousRange);
        }

        // Get aggregated data for both periods (tenant events only)
        $currentDaily = EventAnalyticsDaily::where('event_id', $event->id)
            ->whereBetween('date', [$currentRange['start']->toDateString(), $currentRange['end']->toDateString()])
            ->get();

        $previousDaily = EventAnalyticsDaily::where('event_id', $event->id)
            ->whereBetween('date', [$previousRange['start']->toDateString(), $previousRange['end']->toDateString()])
            ->get();

        $currentRevenue = $currentDaily->sum('revenue') ?: $currentDaily->sum(fn($d) => ($d->revenue_cents ?? 0) / 100);
        $previousRevenue = $previousDaily->sum('revenue') ?: $previousDaily->sum(fn($d) => ($d->revenue_cents ?? 0) / 100);

        $currentVisitors = $currentDaily->sum('unique_visitors');
        $previousVisitors = $previousDaily->sum('unique_visitors');

        $currentPurchases = $currentDaily->sum('purchases') ?: $currentDaily->sum('purchases_count');
        $previousPurchases = $previousDaily->sum('purchases') ?: $previousDaily->sum('purchases_count');

        return [
            'current' => [
                'revenue' => $currentRevenue,
                'visitors' => $currentVisitors,
                'purchases' => $currentPurchases,
                'conversion_rate' => $currentVisitors > 0 ? round(($currentPurchases / $currentVisitors) * 100, 2) : 0,
            ],
            'previous' => [
                'revenue' => $previousRevenue,
                'visitors' => $previousVisitors,
                'purchases' => $previousPurchases,
                'conversion_rate' => $previousVisitors > 0 ? round(($previousPurchases / $previousVisitors) * 100, 2) : 0,
            ],
            'changes' => [
                'revenue' => $previousRevenue > 0 ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0,
                'visitors' => $previousVisitors > 0 ? round((($currentVisitors - $previousVisitors) / $previousVisitors) * 100, 1) : 0,
                'purchases' => $previousPurchases > 0 ? round((($currentPurchases - $previousPurchases) / $previousPurchases) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Compute period comparison from raw data (for marketplace events)
     */
    protected function computePeriodComparisonFromRaw(Event|MarketplaceEvent $event, array $currentRange, array $previousRange): array
    {
        // Current period
        $currentRevenue = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$currentRange['start'], $currentRange['end']])
            ->sum('total');

        // Use helper for backwards compatibility
        // Include page_view events OR any records without event_type (for legacy data)
        $currentVisitors = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->where(function ($q) use ($currentRange) {
                $q->whereBetween('created_at', [$currentRange['start'], $currentRange['end']])
                  ->orWhereNull('created_at');
            })
            ->distinct('visitor_id')
            ->count('visitor_id');

        $currentPurchases = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->where(function ($q) use ($currentRange) {
                $q->whereBetween('created_at', [$currentRange['start'], $currentRange['end']])
                  ->orWhereNull('created_at');
            })
            ->count();

        // Previous period
        $previousRevenue = $this->getOrdersQuery($event)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereBetween('created_at', [$previousRange['start'], $previousRange['end']])
            ->sum('total');

        // Include page_view events OR any records without event_type (for legacy data)
        $previousVisitors = (clone $this->getTrackingQuery($event))
            ->where(function ($q) {
                $q->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                  ->orWhereNull('event_type')
                  ->orWhere('event_type', '');
            })
            ->whereBetween('created_at', [$previousRange['start'], $previousRange['end']])
            ->distinct('visitor_id')
            ->count('visitor_id');

        $previousPurchases = (clone $this->getTrackingQuery($event))
            ->where('event_type', CoreCustomerEvent::TYPE_PURCHASE)
            ->whereBetween('created_at', [$previousRange['start'], $previousRange['end']])
            ->count();

        return [
            'current' => [
                'revenue' => $currentRevenue,
                'visitors' => $currentVisitors,
                'purchases' => $currentPurchases,
                'conversion_rate' => $currentVisitors > 0 ? round(($currentPurchases / $currentVisitors) * 100, 2) : 0,
            ],
            'previous' => [
                'revenue' => $previousRevenue,
                'visitors' => $previousVisitors,
                'purchases' => $previousPurchases,
                'conversion_rate' => $previousVisitors > 0 ? round(($previousPurchases / $previousVisitors) * 100, 2) : 0,
            ],
            'changes' => [
                'revenue' => $previousRevenue > 0 ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0,
                'visitors' => $previousVisitors > 0 ? round((($currentVisitors - $previousVisitors) / $previousVisitors) * 100, 1) : 0,
                'purchases' => $previousPurchases > 0 ? round((($currentPurchases - $previousPurchases) / $previousPurchases) * 100, 1) : 0,
            ],
        ];
    }

    /* Helper methods */

    public function getDateRange(string $period, ?Event $event = null, ?MarketplaceEvent $marketplaceEvent = null): array
    {
        // Get the event creation date as the minimum start date
        $eventCreatedAt = $event?->created_at ?? $marketplaceEvent?->created_at ?? now()->subYear();

        $ranges = match ($period) {
            '7d' => ['start' => now()->subDays(7)->startOfDay(), 'end' => now()->endOfDay()],
            '30d' => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
            '90d' => ['start' => now()->subDays(90)->startOfDay(), 'end' => now()->endOfDay()],
            'all' => ['start' => $eventCreatedAt->copy()->startOfDay(), 'end' => now()->endOfDay()],
            default => ['start' => now()->subDays(30)->startOfDay(), 'end' => now()->endOfDay()],
        };

        // Ensure start date is not before event creation
        if ($ranges['start']->lt($eventCreatedAt)) {
            $ranges['start'] = $eventCreatedAt->copy()->startOfDay();
        }

        return $ranges;
    }

    protected function maskName(string $name): string
    {
        $parts = explode(' ', $name);
        if (count($parts) >= 2) {
            return $parts[0] . ' ' . substr($parts[1], 0, 1) . '.';
        }
        return substr($name, 0, 3) . '***';
    }

    protected function formatSource(string $source): string
    {
        return match (strtolower($source)) {
            'facebook', 'fb' => 'Facebook',
            'google', 'gads' => 'Google',
            'instagram', 'ig' => 'Instagram',
            'tiktok', 'tt' => 'TikTok',
            'email', 'newsletter' => 'Email',
            'direct', '' => 'Direct',
            default => ucfirst($source),
        };
    }

    protected function formatEventAction(CoreCustomerEvent $event): string
    {
        return match ($event->event_type) {
            CoreCustomerEvent::TYPE_PAGE_VIEW => 'Viewing ' . ($event->page_title ?? 'event page'),
            CoreCustomerEvent::TYPE_ADD_TO_CART => 'Added ' . ($event->quantity ?? 1) . 'x ' . ($event->content_name ?? 'tickets') . ' to cart',
            CoreCustomerEvent::TYPE_BEGIN_CHECKOUT => 'Viewing checkout page',
            CoreCustomerEvent::TYPE_PURCHASE => 'Completed purchase',
            CoreCustomerEvent::TYPE_VIEW_ITEM => 'Viewing pricing',
            default => ucfirst(str_replace('_', ' ', $event->event_type)),
        };
    }

    protected function generateAnonymousName(?string $visitorId): string
    {
        if (!$visitorId) {
            return 'VIS***';
        }

        // Generate consistent anonymous name from visitor ID
        $hash = substr(strtoupper(md5($visitorId)), 0, 3);
        return "{$hash}*** " . substr($hash, 0, 1) . '.';
    }
}
