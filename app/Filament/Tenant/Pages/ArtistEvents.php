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
    protected static ?string $navigationLabel = 'Events Listed';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.tenant.pages.artist-events';

    public string $statusFilter = 'live';

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
        return 'Events Listed';
    }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;
        $artist = $tenant?->artist;

        if (!$artist) {
            return ['events' => collect(), 'marketplaceEvents' => collect(), 'artist' => null, 'stats' => [], 'statusFilter' => $this->statusFilter];
        }

        $allEvents = Event::whereHas('artists', function ($q) use ($artist) {
                $q->where('artists.id', $artist->id);
            })
            ->with(['venue', 'tenant.domains', 'marketplaceClient', 'marketplaceOrganizer', 'ticketTypes'])
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

                $event->public_url = null;
                if ($slug) {
                    if ($event->marketplace_client_id && $event->marketplaceClient?->domain) {
                        $mpDomain = preg_replace('#^https?://#', '', rtrim($event->marketplaceClient->domain, '/'));
                        $event->public_url = 'https://' . $mpDomain . '/bilete/' . $slug;
                    } elseif ($event->tenant_id) {
                        $domain = $event->tenant?->domains?->where('is_primary', true)->first()?->domain
                            ?? $event->tenant?->domains?->where('is_active', true)->first()?->domain
                            ?? $event->tenant?->domain;
                        if ($domain) {
                            $event->public_url = 'https://' . preg_replace('#^https?://#', '', $domain) . '/events/' . $slug;
                        }
                    }
                }

                // Compute status
                $now = now()->toDateString();
                $event->days_until = null;
                if ($event->is_cancelled) {
                    $event->computed_status = 'cancelled';
                } elseif ($event->is_postponed) {
                    $event->computed_status = 'postponed';
                } elseif (!$event->event_date) {
                    $event->computed_status = 'unknown';
                } elseif ($event->event_date < $now) {
                    $event->computed_status = 'ended';
                } else {
                    $event->computed_status = 'live';
                    $event->days_until = max(0, (int) now()->diffInDays(\Carbon\Carbon::parse($event->event_date), false));
                }

                return $event;
            });

        // Apply status filter
        $events = $this->statusFilter !== 'all'
            ? $allEvents->where('computed_status', $this->statusFilter)
            : $allEvents;

        // Sort: upcoming first (closest date first), TBD last
        $events = $events->sortBy(function ($event) {
            $date = $event->event_date;
            if (!$date) return '2_9999-12-31'; // TBD always last
            $now = now()->toDateString();
            if ($date >= $now) {
                return '0_' . $date; // Upcoming: ascending
            }
            // Past: reverse (most recent first)
            return '1_' . (9999 - (int) substr($date, 0, 4)) . substr($date, 4);
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
        $unknownCount = $allEvents->where('computed_status', 'unknown')->count();
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
                'unknown' => $unknownCount,
                'total_sold' => $totalSold,
                'total_revenue' => $totalRevenue,
            ],
        ];
    }
}
