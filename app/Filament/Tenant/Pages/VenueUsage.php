<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Venue;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class VenueUsage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Venue Usage';
    protected static ?string $navigationGroup = 'Venue';
    protected static ?int $navigationSort = 50;
    protected string $view = 'filament.tenant.pages.venue-usage';

    public ?Tenant $tenant = null;

    #[Url]
    public string $venueFilter = 'all';

    #[Url]
    public string $statusFilter = 'upcoming';

    public function mount(): void
    {
        $this->tenant = auth()->user()->tenant;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant?->ownsVenues() ?? false;
    }

    public function getTitle(): string
    {
        return 'Venue Usage';
    }

    public function getHeading(): string|null
    {
        return 'Venue Usage';
    }

    public function getSubheading(): string|null
    {
        return 'Events happening at your venues';
    }

    public function getViewData(): array
    {
        $tenant = $this->tenant;

        if (!$tenant) {
            return [
                'tenant' => null,
                'venues' => collect(),
                'events' => collect(),
                'stats' => [],
            ];
        }

        // Get venues owned by this tenant
        $venues = $tenant->venues()->orderBy('name')->get();
        $venueIds = $venues->pluck('id')->toArray();

        if (empty($venueIds)) {
            return [
                'tenant' => $tenant,
                'venues' => collect(),
                'events' => collect(),
                'stats' => [],
            ];
        }

        // Build query for events at owned venues
        $eventsQuery = Event::with(['venue', 'tenant', 'ticketTypes'])
            ->whereIn('venue_id', $venueIds);

        // Apply venue filter
        if ($this->venueFilter !== 'all') {
            $eventsQuery->where('venue_id', $this->venueFilter);
        }

        // Apply status filter
        if ($this->statusFilter === 'upcoming') {
            $eventsQuery->upcoming();
        } elseif ($this->statusFilter === 'past') {
            $eventsQuery->past();
        }

        $events = $eventsQuery->orderBy('event_date', 'asc')->get();

        // Calculate stats
        $stats = $this->calculateStats($venueIds, $tenant->id);

        return [
            'tenant' => $tenant,
            'venues' => $venues,
            'events' => $events,
            'stats' => $stats,
            'venueFilter' => $this->venueFilter,
            'statusFilter' => $this->statusFilter,
        ];
    }

    private function calculateStats(array $venueIds, int $tenantId): array
    {
        // All events at owned venues
        $allEventsQuery = Event::whereIn('venue_id', $venueIds);

        // Total events at venues
        $totalEvents = $allEventsQuery->count();

        // Own events (where tenant_id = current tenant)
        $ownEvents = (clone $allEventsQuery)->where('tenant_id', $tenantId)->count();

        // Hosted events (where tenant_id != current tenant)
        $hostedEvents = (clone $allEventsQuery)->where('tenant_id', '!=', $tenantId)->count();

        // Upcoming events at venues
        $upcomingEvents = (clone $allEventsQuery)->upcoming()->count();

        // Get hosted event IDs for ticket/sales stats
        $hostedEventIds = Event::whereIn('venue_id', $venueIds)
            ->where('tenant_id', '!=', $tenantId)
            ->pluck('id')
            ->toArray();

        // Tickets sold for hosted events
        $hostedTicketsSold = 0;
        $hostedRevenue = 0;

        if (!empty($hostedEventIds)) {
            $hostedTicketsSold = Ticket::whereHas('ticketType', function ($query) use ($hostedEventIds) {
                $query->whereIn('event_id', $hostedEventIds);
            })->whereHas('order', function ($query) {
                $query->whereIn('status', ['paid', 'confirmed']);
            })->count();

            // Revenue from hosted events
            $hostedRevenue = Order::whereIn('status', ['paid', 'confirmed'])
                ->whereHas('tickets.ticketType', function ($query) use ($hostedEventIds) {
                    $query->whereIn('event_id', $hostedEventIds);
                })
                ->sum('total_cents') / 100;
        }

        return [
            'total_events' => $totalEvents,
            'own_events' => $ownEvents,
            'hosted_events' => $hostedEvents,
            'upcoming_events' => $upcomingEvents,
            'hosted_tickets_sold' => $hostedTicketsSold,
            'hosted_revenue' => $hostedRevenue,
        ];
    }

    public function getEventStats(Event $event): array
    {
        $ticketsSold = Ticket::whereHas('ticketType', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })->whereHas('order', function ($query) {
            $query->whereIn('status', ['paid', 'confirmed']);
        })->count();

        $revenue = Order::whereIn('status', ['paid', 'confirmed'])
            ->whereHas('tickets.ticketType', function ($query) use ($event) {
                $query->where('event_id', $event->id);
            })
            ->sum('total_cents') / 100;

        $totalCapacity = $event->ticketTypes->sum('capacity');

        return [
            'tickets_sold' => $ticketsSold,
            'revenue' => $revenue,
            'capacity' => $totalCapacity,
            'occupancy' => $totalCapacity > 0 ? round(($ticketsSold / $totalCapacity) * 100, 1) : 0,
        ];
    }

    public function isOwnEvent(Event $event): bool
    {
        return $event->tenant_id === $this->tenant?->id;
    }
}
