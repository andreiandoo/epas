<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\AnalyticsEvent;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class EventStatistics extends Page
{
    use InteractsWithRecord;
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Event Statistics';

    protected string $view = 'filament.marketplace.resources.event-resource.pages.event-statistics';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Verify tenant access - allow for own events OR events at owned venues
        $marketplace = static::getMarketplaceClient();
        $ownedVenueIds = $marketplace?->venues()->pluck('id')->toArray() ?? [];

        // Must be either owned by tenant OR happening at tenant's venue
        $isOwnEvent = $this->record->tenant_id === $marketplace?->id;
        $isAtOwnedVenue = in_array($this->record->venue_id, $ownedVenueIds);

        if (!$isOwnEvent && !$isAtOwnedVenue) {
            abort(403, 'Unauthorized access to this event');
        }
    }

    /**
     * Check if this is a guest event (not owned but at owned venue)
     */
    public function isGuestEvent(): bool
    {
        $marketplace = static::getMarketplaceClient();
        return $this->record->tenant_id !== $marketplace?->id;
    }

    public function getBreadcrumb(): string
    {
        return 'Statistics';
    }

    /**
     * Get ticket types with sales data
     */
    public function getTicketTypesData(): array
    {
        return $this->record->ticketTypes()
            ->select('id', 'name', 'price_cents', 'sale_price_cents', 'quota_total', 'quota_sold', 'currency', 'status')
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'price' => number_format(($type->sale_price_cents ?? $type->price_cents) / 100, 2),
                'currency' => $type->currency ?? 'RON',
                'sold' => $type->quota_sold ?? 0,
                'total' => $type->quota_total ?? 0,
                'available' => ($type->quota_total ?? 0) - ($type->quota_sold ?? 0),
                'percentage' => $type->quota_total > 0
                    ? round(($type->quota_sold / $type->quota_total) * 100, 1)
                    : 0,
                'revenue' => ($type->quota_sold ?? 0) * (($type->sale_price_cents ?? $type->price_cents) / 100),
                'status' => $type->status,
            ])
            ->toArray();
    }

    /**
     * Get total revenue for this event
     */
    public function getTotalRevenue(): float
    {
        $ticketTypeIds = $this->record->ticketTypes()->pluck('id');

        return Order::whereHas('tickets', function ($q) use ($ticketTypeIds) {
                $q->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->whereIn('status', ['paid', 'confirmed'])
            ->sum('total_cents') / 100;
    }

    /**
     * Get total tickets sold for this event
     */
    public function getTotalTicketsSold(): int
    {
        return $this->record->ticketTypes()->sum('quota_sold') ?? 0;
    }

    /**
     * Get total tickets capacity
     */
    public function getTotalCapacity(): int
    {
        return $this->record->ticketTypes()->sum('quota_total') ?? 0;
    }

    /**
     * Get order statistics
     */
    public function getOrderStats(): array
    {
        $ticketTypeIds = $this->record->ticketTypes()->pluck('id');

        $orders = Order::whereHas('tickets', function ($q) use ($ticketTypeIds) {
                $q->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => array_sum($orders),
            'pending' => $orders['pending'] ?? 0,
            'paid' => ($orders['paid'] ?? 0) + ($orders['confirmed'] ?? 0),
            'cancelled' => $orders['cancelled'] ?? 0,
            'refunded' => $orders['refunded'] ?? 0,
        ];
    }

    /**
     * Get daily sales data for chart (last 30 days)
     */
    public function getDailySalesData(): array
    {
        $ticketTypeIds = $this->record->ticketTypes()->pluck('id');
        $ticketTypes = $this->record->ticketTypes()->pluck('name', 'id');

        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        // Get sales per day per ticket type
        $sales = Ticket::whereIn('ticket_type_id', $ticketTypeIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'confirmed']))
            ->select(
                'ticket_type_id',
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('ticket_type_id', 'date')
            ->get();

        // Build labels (dates)
        $labels = [];
        $datasets = [];
        $colors = [
            'rgba(139, 92, 246, 0.8)',   // Purple
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(16, 185, 129, 0.8)',   // Green
            'rgba(245, 158, 11, 0.8)',   // Yellow
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(236, 72, 153, 0.8)',   // Pink
        ];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d M');
        }

        // Build datasets per ticket type
        $colorIndex = 0;
        foreach ($ticketTypes as $typeId => $typeName) {
            $data = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $count = $sales->where('ticket_type_id', $typeId)
                    ->where('date', $date)
                    ->first()?->count ?? 0;
                $data[] = $count;
            }

            $datasets[] = [
                'label' => $typeName,
                'data' => $data,
                'backgroundColor' => $colors[$colorIndex % count($colors)],
                'borderColor' => $colors[$colorIndex % count($colors)],
                'borderWidth' => 2,
            ];
            $colorIndex++;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * Get page view analytics if available
     */
    public function getPageAnalytics(): array
    {
        try {
            $eventSlug = $this->record->slug;
            $tenantId = $this->record->tenant_id;

            // Build URL patterns to match event pages
            // Event pages can be: /events/{slug}, /ro/events/{slug}, /#/events/{slug}, etc.
            $urlPatterns = [
                "%/events/{$eventSlug}%",
                "%/events/{$eventSlug}/%",
                "%#/events/{$eventSlug}%",
            ];

            // Query CoreCustomerEvent for page views matching this event's URL
            $baseQuery = CoreCustomerEvent::where('marketplace_client_id', $tenantId)
                ->where('event_type', CoreCustomerEvent::TYPE_PAGE_VIEW)
                ->where(function ($q) use ($urlPatterns) {
                    foreach ($urlPatterns as $pattern) {
                        $q->orWhere('page_url', 'like', $pattern);
                    }
                });

            // Total page views
            $totalViews = (clone $baseQuery)->count();

            // Unique sessions
            $uniqueSessions = (clone $baseQuery)
                ->distinct()
                ->count('session_id');

            // Views by day (last 7 days)
            $viewsByDay = (clone $baseQuery)
                ->where('created_at', '>=', now()->subDays(7))
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            // Top referrers
            $topReferrers = (clone $baseQuery)
                ->whereNotNull('referrer')
                ->where('referrer', '!=', '')
                ->select('referrer', DB::raw('COUNT(*) as count'))
                ->groupBy('referrer')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'referrer')
                ->toArray();

            // Top countries
            $topCountries = (clone $baseQuery)
                ->whereNotNull('country_code')
                ->select('country_code', DB::raw('COUNT(*) as count'))
                ->groupBy('country_code')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'country_code')
                ->toArray();

            // Top traffic sources
            $topSources = (clone $baseQuery)
                ->select(DB::raw("
                    CASE
                        WHEN gclid IS NOT NULL THEN 'Google Ads'
                        WHEN fbclid IS NOT NULL THEN 'Facebook Ads'
                        WHEN ttclid IS NOT NULL THEN 'TikTok Ads'
                        WHEN utm_source IS NOT NULL THEN utm_source
                        WHEN referrer IS NOT NULL AND referrer != '' THEN 'Referral'
                        ELSE 'Direct'
                    END as source
                "), DB::raw('COUNT(*) as count'))
                ->groupBy('source')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'source')
                ->toArray();

            // Device breakdown
            $devices = (clone $baseQuery)
                ->whereNotNull('device_type')
                ->select('device_type', DB::raw('COUNT(*) as count'))
                ->groupBy('device_type')
                ->orderByDesc('count')
                ->pluck('count', 'device_type')
                ->toArray();

            return [
                'available' => true,
                'total_views' => $totalViews,
                'unique_sessions' => $uniqueSessions,
                'views_by_day' => $viewsByDay,
                'top_referrers' => $topReferrers,
                'top_countries' => $topCountries,
                'top_sources' => $topSources,
                'devices' => $devices,
            ];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        if ($this->isGuestEvent()) {
            return [
                \Filament\Actions\Action::make('back_to_view')
                    ->label('Back to Details')
                    ->icon('heroicon-o-arrow-left')
                    ->url(EventResource::getUrl('view-guest', ['record' => $this->record])),
            ];
        }

        return [
            \Filament\Actions\Action::make('back_to_edit')
                ->label('Back to Edit')
                ->icon('heroicon-o-arrow-left')
                ->url(EventResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
