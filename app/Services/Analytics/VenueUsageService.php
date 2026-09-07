<?php

namespace App\Services\Analytics;

use App\Models\Event;
use Carbon\Carbon;

/**
 * Extracted from app/Filament/Tenant/Pages/VenueUsage.php:getViewData().
 *
 * The Filament page is deliberately left untouched during the Faza 0
 * rollout so the tenant panel behavior stays byte-identical while the
 * new ambilet.ro /venue/* web shell comes online. Both consumers can
 * be unified onto this service after the web shell is stable
 * (short-term duplication accepted for zero live impact).
 *
 * Everything the controller needs is derived from a venue-id list and
 * two filter strings — no auth/session coupling — so instantiation is
 * safe from any controller or job.
 */
class VenueUsageService
{
    /**
     * Build the venue-usage payload (events + aggregate stats).
     *
     * @param  array  $venueIds  Venues the caller is authorized to see. Empty → empty payload.
     * @param  string $venueFilter 'all' or a venue id as string.
     * @param  string $statusFilter 'all' | 'live' | 'ended' | 'cancelled' | 'unknown' | 'postponed'.
     * @return array{events: \Illuminate\Support\Collection, stats: array<string,int|float>}
     */
    public function build(array $venueIds, string $venueFilter = 'all', string $statusFilter = 'all'): array
    {
        if (empty($venueIds)) {
            return [
                'events' => collect(),
                'stats'  => $this->emptyStats(),
            ];
        }

        $filteredVenueIds = $venueFilter !== 'all'
            ? [(int) $venueFilter]
            : $venueIds;

        // Only allow the filter through if it's actually a venue the caller
        // owns — otherwise the string 'all' path already covered the safe case.
        $filteredVenueIds = array_values(array_intersect($filteredVenueIds, $venueIds));
        if (empty($filteredVenueIds)) {
            return [
                'events' => collect(),
                'stats'  => $this->emptyStats(),
            ];
        }

        $allEvents = Event::with([
            'venue',
            'tenant',
            'tenant.domains',
            'marketplaceClient',
            'marketplaceOrganizer',
            'ticketTypes',
            'artists',
        ])
            ->whereIn('venue_id', $filteredVenueIds)
            ->get()
            ->map(fn ($event) => $this->decorateEvent($event));

        $events = $statusFilter !== 'all'
            ? $allEvents->where('computed_status', $statusFilter)
            : $allEvents;

        // Sort: upcoming first (closest date), then past (most recent first),
        // unknown last. String-encoded so a single sortBy handles it.
        $events = $events->sortBy(function ($event) {
            $date = $event->event_date;
            if (!$date) return '2_9999-12-31';
            $now = now()->toDateString();
            if ($date >= $now) return '0_' . $date;
            return '1_' . (9999 - (int) substr($date, 0, 4)) . substr($date, 4);
        })->values();

        $stats = [
            'total'         => $allEvents->count(),
            'upcoming'      => $allEvents->where('computed_status', 'live')->count(),
            'ended'         => $allEvents->where('computed_status', 'ended')->count(),
            'cancelled'     => $allEvents->where('computed_status', 'cancelled')->count(),
            'unknown'       => $allEvents->where('computed_status', 'unknown')->count(),
            'total_sold'    => (int) $allEvents->sum('ticket_stats.sold'),
            'total_revenue' => round((float) $allEvents->sum('ticket_stats.revenue'), 2),
        ];

        return compact('events', 'stats');
    }

    /**
     * Attach computed fields on the Event model: ticket_stats, computed_status,
     * days_until, public_url, artist_names. Mirrors the Filament page so both
     * consumers see identical numbers.
     */
    private function decorateEvent(Event $event): Event
    {
        $sold     = (int) $event->ticketTypes->sum('quota_sold');
        $capacity = (int) $event->ticketTypes->sum('quota_total');
        $revenue  = $event->ticketTypes
            ->sum(fn ($tt) => ($tt->quota_sold ?? 0) * ($tt->price_cents ?? 0) / 100);

        $event->ticket_stats = [
            'sold'      => $sold,
            'capacity'  => $capacity,
            'revenue'   => round((float) $revenue, 2),
            'fill_rate' => $capacity > 0 ? round($sold / $capacity * 100) : 0,
        ];

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
            $event->days_until = max(0, (int) now()->diffInDays(Carbon::parse($event->event_date), false));
        }

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

        $event->artist_names = $event->artists->pluck('name')->join(', ');

        return $event;
    }

    private function emptyStats(): array
    {
        return [
            'total'         => 0,
            'upcoming'      => 0,
            'ended'         => 0,
            'cancelled'     => 0,
            'unknown'       => 0,
            'total_sold'    => 0,
            'total_revenue' => 0.0,
        ];
    }
}
