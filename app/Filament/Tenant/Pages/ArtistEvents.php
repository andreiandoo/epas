<?php

namespace App\Filament\Tenant\Pages;

use App\Enums\TenantType;
use App\Models\Event;
use App\Models\MarketplaceEvent;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ArtistEvents extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'My Events';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.tenant.pages.artist-events';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant) return false;
        return in_array($tenant->tenant_type, [TenantType::TenantArtist, TenantType::Artist])
            && $tenant->artist_id !== null;
    }

    public function getTitle(): string
    {
        return 'My Events';
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;
        $artist = $tenant?->artist;

        if (!$artist) {
            return ['events' => collect(), 'marketplaceEvents' => collect(), 'artist' => null, 'stats' => []];
        }

        // Core events where this artist appears (via event_artist pivot)
        $events = Event::whereHas('artists', function ($q) use ($artist) {
                $q->where('artists.id', $artist->id);
            })
            ->with(['venue', 'tenant', 'ticketTypes'])
            ->orderByDesc('event_date')
            ->get()
            ->map(function ($event) {
                // Calculate ticket sales for this event
                $ticketStats = DB::table('tickets')
                    ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                    ->where('ticket_types.event_id', $event->id)
                    ->select([
                        DB::raw('COUNT(*) as total_tickets'),
                        DB::raw("COUNT(CASE WHEN tickets.status = 'active' OR tickets.status = 'used' THEN 1 END) as sold"),
                        DB::raw('COALESCE(SUM(tickets.price), 0) as revenue'),
                    ])
                    ->first();

                $capacity = $event->ticketTypes->sum('capacity');

                $event->ticket_stats = [
                    'sold' => (int) ($ticketStats->sold ?? 0),
                    'capacity' => $capacity,
                    'revenue' => (float) ($ticketStats->revenue ?? 0),
                    'fill_rate' => $capacity > 0 ? round(($ticketStats->sold ?? 0) / $capacity * 100) : 0,
                ];

                return $event;
            });

        // Marketplace events where this artist appears (via JSON artist_ids)
        $marketplaceEvents = collect();
        try {
            $marketplaceEvents = MarketplaceEvent::where(function ($q) use ($artist) {
                    $q->whereJsonContains('artist_ids', $artist->id)
                      ->orWhereJsonContains('artist_ids', (string) $artist->id);
                })
                ->orderByDesc('starts_at')
                ->get()
                ->map(function ($event) {
                    $event->ticket_stats = [
                        'sold' => 0,
                        'capacity' => 0,
                        'revenue' => 0,
                        'fill_rate' => 0,
                    ];
                    return $event;
                });
        } catch (\Exception $e) {
            // MarketplaceEvent may not exist or artist_ids column may not be present
        }

        // Summary stats
        $upcomingCount = $events->where('event_date', '>=', now()->toDateString())->count();
        $pastCount = $events->where('event_date', '<', now()->toDateString())->count();
        $totalSold = $events->sum('ticket_stats.sold');
        $totalRevenue = $events->sum('ticket_stats.revenue');

        return [
            'events' => $events,
            'marketplaceEvents' => $marketplaceEvents,
            'artist' => $artist,
            'stats' => [
                'total' => $events->count() + $marketplaceEvents->count(),
                'upcoming' => $upcomingCount,
                'past' => $pastCount,
                'total_sold' => $totalSold,
                'total_revenue' => $totalRevenue,
            ],
        ];
    }
}
