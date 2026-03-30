<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Event;
use App\Models\Venue;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class VenueUsage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Venue Usage';
    protected static \UnitEnum|string|null $navigationGroup = 'Venue';
    protected static ?int $navigationSort = 50;
    protected string $view = 'filament.tenant.pages.venue-usage';

    #[Url]
    public string $venueFilter = 'all';

    #[Url]
    public string $statusFilter = 'all';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant?->ownsVenues() ?? false;
    }

    public function updatedStatusFilter(): void {}
    public function updatedVenueFilter(): void {}

    public function getTitle(): string { return 'Venue Usage'; }
    public function getHeading(): ?string { return 'Venue Usage'; }
    public function getSubheading(): ?string { return 'Events happening at your venues'; }

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) {
            return ['venues' => collect(), 'venueOptions' => collect(), 'events' => collect(), 'stats' => [], 'venueFilter' => 'all', 'statusFilter' => 'all', 'showVenueFilter' => false];
        }

        $venues = $tenant->venues()->get();
        $venueIds = $venues->pluck('id')->toArray();
        $showVenueFilter = count($venueIds) > 1;

        if (empty($venueIds)) {
            return ['venues' => $venues, 'venueOptions' => collect(), 'events' => collect(), 'stats' => [], 'venueFilter' => $this->venueFilter, 'statusFilter' => $this->statusFilter, 'showVenueFilter' => false];
        }

        $filteredVenueIds = $this->venueFilter !== 'all' ? [(int) $this->venueFilter] : $venueIds;

        $allEvents = Event::with(['venue', 'tenant', 'tenant.domains', 'marketplaceClient', 'marketplaceOrganizer', 'ticketTypes', 'artists'])
            ->whereIn('venue_id', $filteredVenueIds)
            ->get()
            ->map(function ($event) {
                // Ticket stats from ticket_types directly
                $sold = $event->ticketTypes->sum('quota_sold');
                $capacity = $event->ticketTypes->sum('quota_total');
                $revenue = $event->ticketTypes->sum(fn ($tt) => ($tt->quota_sold ?? 0) * ($tt->price_cents ?? 0) / 100);

                $event->ticket_stats = [
                    'sold' => (int) $sold,
                    'capacity' => (int) $capacity,
                    'revenue' => round((float) $revenue, 2),
                    'fill_rate' => $capacity > 0 ? round($sold / $capacity * 100) : 0,
                ];

                // Status
                $now = now()->toDateString();
                $event->days_until = null;
                if ($event->is_cancelled) {
                    $event->computed_status = 'cancelled';
                } elseif ($event->is_postponed ?? false) {
                    $event->computed_status = 'postponed';
                } elseif (!$event->event_date) {
                    $event->computed_status = 'unknown';
                } elseif ($event->event_date < $now) {
                    $event->computed_status = 'ended';
                } else {
                    $event->computed_status = 'live';
                    $event->days_until = max(0, (int) now()->diffInDays(\Carbon\Carbon::parse($event->event_date), false));
                }

                // Public URL
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

                // Artists list
                $event->artist_names = $event->artists->pluck('name')->join(', ');

                return $event;
            });

        // Apply status filter
        $events = $this->statusFilter !== 'all'
            ? $allEvents->where('computed_status', $this->statusFilter)
            : $allEvents;

        // Sort: upcoming first (closest date), then past (most recent first), unknown last
        $events = $events->sortBy(function ($event) {
            $date = $event->event_date;
            if (!$date) return '2_9999-12-31';
            $now = now()->toDateString();
            if ($date >= $now) return '0_' . $date;
            return '1_' . (9999 - (int) substr($date, 0, 4)) . substr($date, 4);
        })->values();

        // Stats from ALL events (unfiltered by status, but filtered by venue)
        $stats = [
            'total' => $allEvents->count(),
            'upcoming' => $allEvents->where('computed_status', 'live')->count(),
            'ended' => $allEvents->where('computed_status', 'ended')->count(),
            'cancelled' => $allEvents->where('computed_status', 'cancelled')->count(),
            'unknown' => $allEvents->where('computed_status', 'unknown')->count(),
            'total_sold' => $allEvents->sum('ticket_stats.sold'),
            'total_revenue' => $allEvents->sum('ticket_stats.revenue'),
        ];

        // Venue name helper for dropdown
        $venueOptions = $venues->mapWithKeys(function ($v) {
            $name = $v->getTranslation('name', 'ro') ?: $v->getTranslation('name', 'en');
            if (is_array($name)) $name = $name['ro'] ?? $name['en'] ?? reset($name) ?: 'Venue';
            return [$v->id => $name . ($v->city ? ' (' . $v->city . ')' : '')];
        });

        return [
            'venues' => $venues,
            'venueOptions' => $venueOptions,
            'events' => $events,
            'stats' => $stats,
            'venueFilter' => $this->venueFilter,
            'statusFilter' => $this->statusFilter,
            'showVenueFilter' => $showVenueFilter,
        ];
    }
}
