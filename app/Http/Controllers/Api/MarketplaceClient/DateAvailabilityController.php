<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceEvent;
use App\Models\MarketplaceEventDateCapacity;
use App\Models\MarketplaceTicketType;
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

        $event = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where(fn ($q) => $q->where('slug', $identifier)->orWhere('id', $identifier))
            ->where('status', 'published')
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
    private function singleDateAvailability(MarketplaceEvent $event, string $date): JsonResponse
    {
        $dateStr = $date;

        // Validate date is within event range
        if ($event->starts_at && Carbon::parse($dateStr)->lt($event->starts_at->startOfDay())) {
            return response()->json(['date' => $dateStr, 'is_open' => false, 'reason' => 'before_season']);
        }
        if ($event->ends_at && Carbon::parse($dateStr)->gt($event->ends_at->endOfDay())) {
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

        $ticketTypes = $event->ticketTypes()
            ->where('status', 'on_sale')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        $ticketData = [];

        foreach ($ticketTypes as $tt) {
            $dateCapacity = null;
            $available = null;
            $effectivePrice = (float) $tt->price;

            if ($tt->daily_capacity) {
                // Get or create date capacity row
                $dateCapacity = MarketplaceEventDateCapacity::getOrCreate(
                    $event->id,
                    $tt->id,
                    $dateStr,
                    $tt->daily_capacity
                );

                if ($dateCapacity->is_closed) {
                    continue; // Skip closed ticket types for this date
                }

                $available = $dateCapacity->available;
                $effectivePrice = $event->getEffectivePrice($tt, $dateStr, $dateCapacity->price_override ? (float) $dateCapacity->price_override : null);
            } else {
                // No daily capacity — use global stock
                $available = $tt->available_quantity; // null = unlimited
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
                'base_price' => (float) $tt->price,
                'effective_price' => $effectivePrice,
                'currency' => $tt->currency ?? 'RON',
                'available' => $available,
                'capacity' => $dateCapacity?->capacity ?? $tt->quantity,
                'min_per_order' => $tt->min_per_order ?? 1,
                'max_per_order' => $tt->max_per_order ?? 10,
                'is_parking' => (bool) $tt->is_parking,
                'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                'is_refundable' => (bool) $tt->is_refundable,
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
    private function monthAvailability(MarketplaceEvent $event, string $month): JsonResponse
    {
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Clamp to event range
        if ($event->starts_at && $start->lt($event->starts_at->startOfDay())) {
            $start = $event->starts_at->copy()->startOfDay();
        }
        if ($event->ends_at && $end->gt($event->ends_at->endOfDay())) {
            $end = $event->ends_at->copy()->endOfDay();
        }

        // Get existing capacity rows for this month
        $existingCapacities = MarketplaceEventDateCapacity::where('marketplace_event_id', $event->id)
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($row) => $row->visit_date->format('Y-m-d'));

        // Get ticket types with daily capacity for defaults
        $ticketTypes = $event->ticketTypes()
            ->where('status', 'on_sale')
            ->where('is_visible', true)
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
