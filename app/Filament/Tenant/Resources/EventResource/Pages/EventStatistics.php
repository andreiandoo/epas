<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Tenant\Resources\EventResource;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\AnalyticsEvent;
use Filament\Resources\Pages\Page;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class EventStatistics extends Page
{
    protected static string $resource = EventResource::class;
    protected static string $view = 'filament.tenant.resources.event-resource.pages.event-statistics';
    protected static ?string $title = 'Event Statistics';

    public Event $record;

    public function mount(int | string $record): void
    {
        $this->record = Event::findOrFail($record);

        // Ensure tenant access
        $tenant = auth()->user()->tenant;
        if ($this->record->tenant_id !== $tenant?->id) {
            abort(403);
        }
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
        // Check if analytics table exists and has data
        if (!class_exists(AnalyticsEvent::class)) {
            return ['available' => false];
        }

        try {
            $eventId = $this->record->id;
            $tenantId = $this->record->tenant_id;

            // Total page views
            $totalViews = AnalyticsEvent::where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('event_type', 'page_view')
                ->count();

            // Unique sessions
            $uniqueSessions = AnalyticsEvent::where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('event_type', 'page_view')
                ->distinct('session_id')
                ->count('session_id');

            // Views by day (last 7 days)
            $viewsByDay = AnalyticsEvent::where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('event_type', 'page_view')
                ->where('occurred_at', '>=', now()->subDays(7))
                ->select(DB::raw('DATE(occurred_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date')
                ->toArray();

            // Top referrers
            $topReferrers = AnalyticsEvent::where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('event_type', 'page_view')
                ->whereNotNull('properties->referrer')
                ->select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.referrer')) as referrer"), DB::raw('COUNT(*) as count'))
                ->groupBy('referrer')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'referrer')
                ->toArray();

            // Top countries (from IP geolocation if stored)
            $topCountries = AnalyticsEvent::where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('event_type', 'page_view')
                ->whereNotNull('properties->country')
                ->select(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.country')) as country"), DB::raw('COUNT(*) as count'))
                ->groupBy('country')
                ->orderByDesc('count')
                ->limit(5)
                ->pluck('count', 'country')
                ->toArray();

            return [
                'available' => true,
                'total_views' => $totalViews,
                'unique_sessions' => $uniqueSessions,
                'views_by_day' => $viewsByDay,
                'top_referrers' => $topReferrers,
                'top_countries' => $topCountries,
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
        return [
            \Filament\Actions\Action::make('back_to_edit')
                ->label('Back to Edit')
                ->icon('heroicon-o-arrow-left')
                ->url(EventResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
