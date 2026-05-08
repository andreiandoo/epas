<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceEventDateCapacity;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DateAvailabilityController extends BaseController
{
    /**
     * GET /events/{identifier}/date-availability
     *
     * Query params:
     *   ?date=2026-07-15          → single date availability with full ticket type details
     *   ?month=2026-07            → month summary for calendar view
     */
    public function __invoke(Request $request, string $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Postgres: orWhere('id', $identifier) cu un slug string crash-uieste
        // ("invalid input syntax for type bigint"). Aplica orWhere id-ul DOAR
        // cand identifier-ul e numeric.
        $event = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier);
                if (is_numeric($identifier)) {
                    $q->orWhere('id', (int) $identifier);
                }
            })
            ->where('is_published', true)
            ->first();

        if (!$event || !$event->isLeisureVenue()) {
            return response()->json(['error' => 'Event not found or not a leisure venue'], 404);
        }

        $date = $request->query('date');
        $month = $request->query('month');

        if ($date) {
            return $this->singleDateAvailability($event, $date);
        }

        if ($month) {
            return $this->monthAvailability($event, $month);
        }

        return response()->json(['error' => 'Provide either ?date=YYYY-MM-DD or ?month=YYYY-MM'], 400);
    }

    /**
     * Full ticket type details for a single date.
     */
    private function singleDateAvailability(Event $event, string $date): JsonResponse
    {
        $dateStr = $date;

        // Validate date is within event range. Event uses range_start_date / range_end_date
        // for leisure venues with seasonal/recurring schedule, instead of starts_at/ends_at.
        $rangeStart = $event->range_start_date ?? $event->event_date ?? null;
        $rangeEnd = $event->range_end_date ?? null;

        if ($rangeStart && Carbon::parse($dateStr)->lt(Carbon::parse($rangeStart)->startOfDay())) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'before_season']);
        }
        if ($rangeEnd && Carbon::parse($dateStr)->gt(Carbon::parse($rangeEnd)->endOfDay())) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'after_season']);
        }

        // Check if date is open per venue schedule
        if (!$event->isDateOpen($dateStr)) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'closed']);
        }

        // Check if past last entry time (only relevant for today)
        $pastLastEntry = $event->isPastLastEntry($dateStr);

        $operatingHours = $event->getOperatingHours($dateStr);
        $season = $event->getSeasonForDate($dateStr);

        // Event model: ticketTypes() returnează TicketType (table ticket_types).
        // Status logic: TicketType nu are status='on_sale' ca MarketplaceTicketType.
        // Folosim is_active (mutator) si filtram out is_entry_ticket / is_invitation.
        $ticketTypes = $event->ticketTypes()
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($tt) {
                if ($tt->is_entry_ticket) return false;
                if (!empty($tt->meta['is_invitation'] ?? false)) return false;
                return true;
            });

        $ticketData = [];

        foreach ($ticketTypes as $tt) {
            $available = null;
            $effectivePrice = (float) ($tt->price_max ?? $tt->price ?? 0);

            if ($tt->daily_capacity) {
                // Daily capacity tracking via MarketplaceEventDateCapacity ramane disponibil
                // doar daca event-ul are si un MarketplaceEvent corespunzator. Pentru
                // Event direct, facem static fallback la daily_capacity (fara live tracking
                // de sold per zi — va fi adaugat cu F4/F5 cand avem cart cu visit_date).
                $available = (int) $tt->daily_capacity;
                $effectivePrice = $event->getEffectivePrice($tt, $dateStr);
            } else {
                $available = $tt->available_quantity ?? null; // null = unlimited
                $effectivePrice = $event->getEffectivePrice($tt, $dateStr);
            }

            // Tour slot support: check if this ticket type has guided tour slots
            $meta = $tt->meta ?? [];
            $hasTourSlots = (bool) ($meta['has_tour_slots'] ?? false);
            $tourSlots = null;

            if ($hasTourSlots) {
                $slotTimes = $meta['slot_times'] ?? [];
                $maxPerSlot = (int) ($meta['max_per_slot'] ?? 20);
                $seasonalAvail = $meta['seasonal_availability'] ?? null;

                // Check seasonal availability (e.g. "summer" = only in summer season)
                if ($seasonalAvail && $season) {
                    $seasonName = strtolower($season['name'] ?? '');
                    if ($seasonalAvail === 'summer' && !str_contains($seasonName, 'var')) {
                        continue; // Skip this ticket type — not available in current season
                    }
                    if ($seasonalAvail === 'winter' && !str_contains($seasonName, 'iarn')) {
                        continue;
                    }
                }

                // Build slot availability (could track per-slot sales via date_capacities notes or meta)
                $tourSlots = array_map(fn ($time) => [
                    'time' => $time,
                    'available' => true, // TODO: track per-slot capacity when needed
                    'max' => $maxPerSlot,
                ], $slotTimes);
            }

            $ttData = [
                'id' => $tt->id,
                'name' => $tt->name,
                'description' => $tt->description,
                'group' => $tt->ticket_group,
                'base_price' => (float) ($tt->price_max ?? $tt->price ?? 0),
                'effective_price' => $effectivePrice,
                'currency' => $tt->currency ?? 'RON',
                'available' => $available,
                'capacity' => $tt->daily_capacity ?? $tt->quota_total ?? null,
                'min_per_order' => $tt->min_per_order ?? 1,
                'max_per_order' => $tt->max_per_order ?? 10,
                'is_parking' => (bool) $tt->is_parking,
                'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                'is_refundable' => (bool) $tt->is_refundable,
                // Leisure venue: issuer + service category fields (NULL fallback la 'primary' / 'access')
                'issuing_company' => $tt->issuing_company ?: 'primary',
                'service_category' => $tt->service_category ?: 'access',
                'service_duration_minutes' => $tt->service_duration_minutes,
                'product_description' => $tt->product_description,
                'usage_terms' => $tt->usage_terms,
                'requires_access_ticket' => (bool) ($tt->requires_access_ticket ?? false),
            ];

            if ($hasTourSlots) {
                $ttData['has_tour_slots'] = true;
                $ttData['tour_slots'] = $tourSlots;
            }

            $ticketData[] = $ttData;
        }

        return response()->json([
            'date' => $dateStr,
            'is_open' => true,
            'past_last_entry' => $pastLastEntry,
            'operating_hours' => $operatingHours,
            'season' => $season ? ['name' => $season['name'] ?? null] : null,
            'ticket_types' => $ticketData,
        ]);
    }

    /**
     * Month summary for calendar — returns status per date.
     */
    private function monthAvailability(Event $event, string $month): JsonResponse
    {
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Clamp to event range (range_start_date / range_end_date pe Event leisure_venue)
        $rangeStart = $event->range_start_date ?? $event->event_date ?? null;
        $rangeEnd = $event->range_end_date ?? null;
        if ($rangeStart && $start->lt(Carbon::parse($rangeStart)->startOfDay())) {
            $start = Carbon::parse($rangeStart)->startOfDay();
        }
        if ($rangeEnd && $end->gt(Carbon::parse($rangeEnd)->endOfDay())) {
            $end = Carbon::parse($rangeEnd)->endOfDay();
        }

        // Capacitati per zi: pentru moment lasam $existingCapacities goal — Event nu se
        // sincronizeaza in MarketplaceEventDateCapacity. F4/F5 va aduce live tracking.
        $existingCapacities = collect();

        // Ticket types cu daily_capacity (pentru pretul minim afisat in calendar)
        $ticketTypes = $event->ticketTypes()
            ->whereNotNull('daily_capacity')
            ->get();

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();

            if (!$event->isDateOpen($dateStr)) {
                $dates[$dateStr] = ['status' => 'closed'];
                $cursor->addDay();
                continue;
            }

            // Check if past
            if ($cursor->lt(now()->startOfDay())) {
                $dates[$dateStr] = ['status' => 'past'];
                $cursor->addDay();
                continue;
            }

            // Calculate availability
            $existing = $existingCapacities->get($dateStr);

            if ($existing && $existing->isNotEmpty()) {
                // Use existing capacity rows
                $totalCapacity = $existing->sum('capacity');
                $totalUsed = $existing->sum('sold') + $existing->sum('reserved');
                $allClosed = $existing->every(fn ($r) => $r->is_closed);

                if ($allClosed) {
                    $dates[$dateStr] = ['status' => 'closed'];
                } elseif ($totalCapacity > 0 && $totalUsed >= $totalCapacity) {
                    $dates[$dateStr] = ['status' => 'sold_out'];
                } elseif ($totalCapacity > 0 && (($totalCapacity - $totalUsed) / $totalCapacity) < 0.3) {
                    $dates[$dateStr] = ['status' => 'limited'];
                } else {
                    $dates[$dateStr] = ['status' => 'available'];
                }
            } else {
                // No capacity rows yet — derive from defaults
                $totalDefault = $ticketTypes->sum('daily_capacity');
                $dates[$dateStr] = ['status' => $totalDefault > 0 ? 'available' : 'available'];
            }

            // Add min price for available dates
            if (in_array($dates[$dateStr]['status'], ['available', 'limited'])) {
                $minPrice = $ticketTypes->map(fn ($tt) => $event->getEffectivePrice($tt, $dateStr))->min();
                if ($minPrice !== null) {
                    $dates[$dateStr]['min_price'] = $minPrice;
                }
            }

            $cursor->addDay();
        }

        return response()->json([
            'month' => $month,
            'event_id' => $event->id,
            'dates' => $dates,
        ]);
    }
}
