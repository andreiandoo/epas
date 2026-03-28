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

    public string $statusFilter = 'all';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant) return false;
        return in_array($tenant->tenant_type, [TenantType::TenantArtist, TenantType::Artist])
            && $tenant->artist_id !== null;
    }

    public function updatedStatusFilter(): void
    {
        // Livewire re-renders automatically
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
            return ['events' => collect(), 'marketplaceEvents' => collect(), 'artist' => null, 'stats' => [], 'statusFilter' => $this->statusFilter];
        }

        // Core events where this artist appears (via event_artist pivot)
        // Eager load tenant.domains for building URLs
        $allEvents = Event::whereHas('artists', function ($q) use ($artist) {
                $q->where('artists.id', $artist->id);
            })
            ->with(['venue', 'tenant.domains', 'ticketTypes'])
            ->get()
            ->map(function ($event) {
                $ticketStats = DB::table('tickets')
                    ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                    ->where('ticket_types.event_id', $event->id)
                    ->select([
                        DB::raw("COUNT(CASE WHEN tickets.status = 'active' OR tickets.status = 'used' THEN 1 END) as sold"),
                        DB::raw('COALESCE(SUM(tickets.price), 0) as revenue'),
                    ])
                    ->first();

                $capacity = $event->ticketTypes->sum('capacity');
                $sold = (int) ($ticketStats->sold ?? 0);

                $event->ticket_stats = [
                    'sold' => $sold,
                    'capacity' => $capacity,
                    'revenue' => (float) ($ticketStats->revenue ?? 0),
                    'fill_rate' => $capacity > 0 ? round($sold / $capacity * 100) : 0,
                ];

                // Build public URL
                $slug = is_array($event->slug)
                    ? ($event->slug[app()->getLocale()] ?? $event->slug['en'] ?? $event->slug['ro'] ?? '')
                    : ($event->slug ?? '');
                $domain = $event->tenant?->domains?->where('is_primary', true)->first()?->domain
                    ?? $event->tenant?->domains?->where('is_active', true)->first()?->domain;
                $event->public_url = ($domain && $slug) ? 'https://' . $domain . '/events/' . $slug : null;

                // Compute status
                $now = now()->toDateString();
                if ($event->is_cancelled) {
                    $event->computed_status = 'cancelled';
                } elseif ($event->is_postponed) {
                    $event->computed_status = 'postponed';
                } elseif ($event->event_date && $event->event_date < $now) {
                    $event->computed_status = 'ended';
                } else {
                    $event->computed_status = 'live';
                }

                return $event;
            });

        // Apply status filter
        $events = $allEvents;
        if ($this->statusFilter !== 'all') {
            $events = $allEvents->where('computed_status', $this->statusFilter);
        }

        // Sort: upcoming first (closest date first), then past (most recent first)
        $events = $events->sortBy(function ($event) {
            $now = now()->toDateString();
            $date = $event->event_date ?? '9999-12-31';
            if ($date >= $now) {
                // Upcoming: sort ascending (closest first) — prefix with 0
                return '0_' . $date;
            }
            // Past: sort descending (most recent first) — prefix with 1, invert date
            return '1_' . str_replace($date, (9999 - (int) substr($date, 0, 4)) . substr($date, 4), $date);
        })->values();

        // Marketplace events
        $marketplaceEvents = collect();
        try {
            $marketplaceEvents = MarketplaceEvent::where(function ($q) use ($artist) {
                    $q->whereJsonContains('artist_ids', $artist->id)
                      ->orWhereJsonContains('artist_ids', (string) $artist->id);
                })
                ->orderByDesc('starts_at')
                ->get();
        } catch (\Exception $e) {}

        // Summary stats (from ALL events, unfiltered)
        $upcomingCount = $allEvents->where('computed_status', 'live')->count();
        $pastCount = $allEvents->where('computed_status', 'ended')->count();
        $cancelledCount = $allEvents->where('computed_status', 'cancelled')->count();
        $postponedCount = $allEvents->where('computed_status', 'postponed')->count();
        $totalSold = $allEvents->sum('ticket_stats.sold');
        $totalRevenue = $allEvents->sum('ticket_stats.revenue');

        return [
            'events' => $events,
            'marketplaceEvents' => $marketplaceEvents,
            'artist' => $artist,
            'statusFilter' => $this->statusFilter,
            'stats' => [
                'total' => $allEvents->count() + $marketplaceEvents->count(),
                'upcoming' => $upcomingCount,
                'past' => $pastCount,
                'cancelled' => $cancelledCount,
                'postponed' => $postponedCount,
                'total_sold' => $totalSold,
                'total_revenue' => $totalRevenue,
            ],
        ];
    }
}
