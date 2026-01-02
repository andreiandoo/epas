<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceEvent;
use App\Models\Venue;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class VenueUsage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Venue Usage';
    protected static \UnitEnum|string|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 50;
    protected string $view = 'filament.marketplace.pages.venue-usage';

    public ?MarketplaceClient $marketplace = null;

    #[Url]
    public string $venueFilter = 'all';

    #[Url]
    public string $statusFilter = 'upcoming';

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Disabled for now - not relevant for marketplace panel
        return false;
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
        return 'Events happening at venues on your marketplace';
    }

    public function getViewData(): array
    {
        $marketplace = $this->marketplace;

        if (!$marketplace) {
            return [
                'marketplace' => null,
                'venues' => collect(),
                'events' => collect(),
                'stats' => [],
            ];
        }

        // Get events on this marketplace
        $eventsQuery = MarketplaceEvent::with(['venue', 'organizer'])
            ->where('marketplace_client_id', $marketplace->id)
            ->where('status', 'published');

        // Apply venue filter
        if ($this->venueFilter !== 'all') {
            $eventsQuery->where('venue_id', $this->venueFilter);
        }

        // Apply status filter
        if ($this->statusFilter === 'upcoming') {
            $eventsQuery->where('start_date', '>=', Carbon::now()->startOfDay());
        } elseif ($this->statusFilter === 'past') {
            $eventsQuery->where('start_date', '<', Carbon::now()->startOfDay());
        }

        $events = $eventsQuery->orderBy('start_date', 'asc')->get();

        // Get venues used in marketplace events
        $venueIds = MarketplaceEvent::where('marketplace_client_id', $marketplace->id)
            ->whereNotNull('venue_id')
            ->pluck('venue_id')
            ->unique();

        $venues = Venue::whereIn('id', $venueIds)->orderBy('name')->get();

        // Calculate stats
        $totalEvents = MarketplaceEvent::where('marketplace_client_id', $marketplace->id)->count();
        $upcomingEvents = MarketplaceEvent::where('marketplace_client_id', $marketplace->id)
            ->where('status', 'published')
            ->where('start_date', '>=', Carbon::now()->startOfDay())
            ->count();

        return [
            'marketplace' => $marketplace,
            'venues' => $venues,
            'events' => $events,
            'stats' => [
                'total_events' => $totalEvents,
                'upcoming_events' => $upcomingEvents,
                'venues_used' => $venues->count(),
            ],
            'venueFilter' => $this->venueFilter,
            'statusFilter' => $this->statusFilter,
        ];
    }
}
