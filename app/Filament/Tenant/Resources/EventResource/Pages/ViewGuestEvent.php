<?php

namespace App\Filament\Tenant\Resources\EventResource\Pages;

use App\Filament\Tenant\Resources\EventResource;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ViewGuestEvent extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Event Details';

    protected string $view = 'filament.tenant.resources.event-resource.pages.view-guest-event';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Verify this is a guest event (happening at venue owned by current tenant)
        $tenant = auth()->user()?->tenant;
        $ownedVenueIds = $tenant?->venues()->pluck('id')->toArray() ?? [];

        // Must be at an owned venue AND not owned by current tenant
        if (!in_array($this->record->venue_id, $ownedVenueIds) || $this->record->tenant_id === $tenant?->id) {
            // If it's their own event, redirect to edit
            if ($this->record->tenant_id === $tenant?->id) {
                redirect(EventResource::getUrl('edit', ['record' => $this->record]));
                return;
            }
            abort(403, 'Unauthorized access to this event');
        }
    }

    public function getBreadcrumb(): string
    {
        return 'View Details';
    }

    public function getTitle(): string
    {
        return $this->record->getTranslation('title', app()->getLocale());
    }

    public function getSubheading(): string
    {
        return 'Hosted event by ' . ($this->record->tenant?->public_name ?? $this->record->tenant?->name ?? 'Unknown');
    }

    /**
     * Get event data for display
     */
    public function getEventData(): array
    {
        $event = $this->record;
        $locale = app()->getLocale();

        return [
            'title' => $event->getTranslation('title', $locale),
            'subtitle' => $event->getTranslation('subtitle', $locale),
            'short_description' => $event->getTranslation('short_description', $locale),
            'description' => $event->getTranslation('description', $locale),
            'ticket_terms' => $event->getTranslation('ticket_terms', $locale),
            'slug' => $event->slug,
            'duration_mode' => $event->duration_mode,
            'event_date' => $event->event_date,
            'start_time' => $event->start_time,
            'door_time' => $event->door_time,
            'end_time' => $event->end_time,
            'range_start_date' => $event->range_start_date,
            'range_end_date' => $event->range_end_date,
            'range_start_time' => $event->range_start_time,
            'range_end_time' => $event->range_end_time,
            'multi_slots' => $event->multi_slots,
            'is_sold_out' => $event->is_sold_out,
            'is_cancelled' => $event->is_cancelled,
            'cancel_reason' => $event->cancel_reason,
            'is_postponed' => $event->is_postponed,
            'postponed_date' => $event->postponed_date,
            'postponed_reason' => $event->postponed_reason,
            'is_promoted' => $event->is_promoted,
            'door_sales_only' => $event->door_sales_only,
            'address' => $event->address,
            'website_url' => $event->website_url,
            'facebook_url' => $event->facebook_url,
            'event_website_url' => $event->event_website_url,
            'poster_url' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
            'hero_image_url' => $event->hero_image_url ? Storage::disk('public')->url($event->hero_image_url) : null,
        ];
    }

    /**
     * Get organizer (tenant) info
     */
    public function getOrganizerData(): array
    {
        $tenant = $this->record->tenant;

        return [
            'name' => $tenant?->public_name ?? $tenant?->name ?? 'Unknown',
            'company_name' => $tenant?->company_name,
            'website' => $tenant?->website,
            'contact_email' => $tenant?->contact_email,
            'contact_phone' => $tenant?->contact_phone,
        ];
    }

    /**
     * Get venue info
     */
    public function getVenueData(): array
    {
        $venue = $this->record->venue;
        $locale = app()->getLocale();

        return [
            'name' => $venue?->getTranslation('name', $locale),
            'address' => $venue?->address,
            'city' => $venue?->city,
            'country' => $venue?->country,
            'capacity' => $venue?->capacity,
            'image_url' => $venue?->image_url ? Storage::disk('public')->url($venue->image_url) : null,
        ];
    }

    /**
     * Get ticket types data
     */
    public function getTicketTypesData(): array
    {
        return $this->record->ticketTypes()
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'price' => $type->price_max,
                'sale_price' => $type->price,
                'currency' => $type->currency ?? 'RON',
                'capacity' => $type->capacity ?? $type->quota_total ?? 0,
                'status' => $type->status,
                'sales_start_at' => $type->sales_start_at,
                'sales_end_at' => $type->sales_end_at,
            ])
            ->toArray();
    }

    /**
     * Get sales statistics
     */
    public function getSalesStats(): array
    {
        $ticketTypeIds = $this->record->ticketTypes()->pluck('id');

        // Tickets sold
        $ticketsSold = Ticket::whereIn('ticket_type_id', $ticketTypeIds)
            ->whereHas('order', function ($query) {
                $query->whereIn('status', ['paid', 'confirmed']);
            })
            ->count();

        // Total revenue
        $revenue = Order::whereIn('status', ['paid', 'confirmed'])
            ->whereHas('tickets', function ($query) use ($ticketTypeIds) {
                $query->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->sum('total_cents') / 100;

        // Unique customers
        $uniqueCustomers = Order::whereIn('status', ['paid', 'confirmed'])
            ->whereHas('tickets', function ($query) use ($ticketTypeIds) {
                $query->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->distinct('customer_id')
            ->count('customer_id');

        // Total capacity
        $totalCapacity = $this->record->ticketTypes()->sum('capacity') ?: $this->record->ticketTypes()->sum('quota_total') ?: 0;

        // Occupancy
        $occupancy = $totalCapacity > 0 ? round(($ticketsSold / $totalCapacity) * 100, 1) : 0;

        // Orders by status
        $ordersByStatus = Order::whereHas('tickets', function ($query) use ($ticketTypeIds) {
                $query->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'tickets_sold' => $ticketsSold,
            'revenue' => $revenue,
            'unique_customers' => $uniqueCustomers,
            'total_capacity' => $totalCapacity,
            'occupancy' => $occupancy,
            'orders' => [
                'total' => array_sum($ordersByStatus),
                'paid' => ($ordersByStatus['paid'] ?? 0) + ($ordersByStatus['confirmed'] ?? 0),
                'pending' => $ordersByStatus['pending'] ?? 0,
                'cancelled' => $ordersByStatus['cancelled'] ?? 0,
                'refunded' => $ordersByStatus['refunded'] ?? 0,
            ],
        ];
    }

    /**
     * Get recent orders
     */
    public function getRecentOrders(): array
    {
        $ticketTypeIds = $this->record->ticketTypes()->pluck('id');

        return Order::with(['customer', 'tickets'])
            ->whereHas('tickets', function ($query) use ($ticketTypeIds) {
                $query->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->whereIn('status', ['paid', 'confirmed'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'customer_name' => $order->customer?->name ?? $order->customer_email ?? 'Guest',
                'customer_email' => $order->customer?->email ?? $order->customer_email,
                'total' => $order->total_cents / 100,
                'tickets_count' => $order->tickets->count(),
                'status' => $order->status,
                'created_at' => $order->created_at,
            ])
            ->toArray();
    }

    /**
     * Get artists
     */
    public function getArtists(): array
    {
        return $this->record->artists()
            ->get()
            ->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'image' => $artist->main_image ? Storage::disk('public')->url($artist->main_image) : null,
            ])
            ->toArray();
    }

    /**
     * Get event types and genres
     */
    public function getTaxonomies(): array
    {
        $locale = app()->getLocale();

        return [
            'event_types' => $this->record->eventTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->getTranslation('name', $locale),
            ])->toArray(),
            'event_genres' => $this->record->eventGenres->map(fn ($genre) => [
                'id' => $genre->id,
                'name' => $genre->getTranslation('name', $locale),
            ])->toArray(),
            'tags' => $this->record->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->toArray(),
        ];
    }

    /**
     * Get header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to Events')
                ->icon('heroicon-o-arrow-left')
                ->url(EventResource::getUrl('index')),
            \Filament\Actions\Action::make('statistics')
                ->label('Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(EventResource::getUrl('statistics', ['record' => $this->record])),
        ];
    }
}
