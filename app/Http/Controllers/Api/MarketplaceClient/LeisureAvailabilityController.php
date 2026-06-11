<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\LeisureResourceLock;
use App\Models\OrderItem;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F3 + F5 — availability endpoints publice pentru slot-uri pe oră (Vaporașe)
 * și inventar fizic cu lock pe interval (Bărci).
 */
class LeisureAvailabilityController extends BaseController
{
    /**
     * GET /marketplace-events/{identifier}/slot-availability?ticket_type_id=X&date=YYYY-MM-DD
     *
     * Pentru un TicketType cu meta.slots_config.enabled = true, returnează lista
     * slot-urilor cu capacitatea rămasă (calculată din OrderItem.meta.slot_time pe
     * Order-uri paid/completed).
     */
    public function slotAvailability(Request $request, string $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $validated = $request->validate([
            'ticket_type_id' => 'required|integer',
            'date' => 'required|date',
        ]);

        $event = Event::query()
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier);
                if (is_numeric($identifier)) $q->orWhere('id', (int) $identifier);
            })
            ->first();
        if (!$event) return response()->json(['error' => 'Event not found'], 404);

        $tt = TicketType::query()
            ->where('id', $validated['ticket_type_id'])
            ->where('event_id', $event->id)
            ->first();
        if (!$tt) return response()->json(['error' => 'Ticket type not found'], 404);

        $config = is_array($tt->meta['slots_config'] ?? null) ? $tt->meta['slots_config'] : null;
        if (!$config || empty($config['enabled'])) {
            return response()->json(['error' => 'Slots not enabled for this ticket type'], 422);
        }

        $date = Carbon::parse($validated['date']);
        $slots = $this->generateSlots($config);
        $capacity = max(1, (int) ($config['capacity_per_slot'] ?? 1));
        $isPerSlot = ($config['unit_pricing'] ?? 'per_person') === 'per_slot';

        // Aggregate sales per slot — OrderItem.meta->>'slot_time' = '09:00' și meta->>'visit_date' = data
        $dateStr = $date->toDateString();
        $bookings = OrderItem::query()
            ->where('ticket_type_id', $tt->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'completed', 'pending']))
            ->where(function ($q) use ($dateStr) {
                $q->whereRaw("meta->>'visit_date' = ?", [$dateStr]);
            })
            ->get(['id', 'quantity', 'meta']);

        $soldPerSlot = [];
        foreach ($bookings as $b) {
            $st = $b->meta['slot_time'] ?? null;
            if (!$st) continue;
            $soldPerSlot[$st] = ($soldPerSlot[$st] ?? 0) + ($isPerSlot ? $capacity : (int) $b->quantity);
        }

        $result = [];
        foreach ($slots as $slot) {
            $sold = $soldPerSlot[$slot] ?? 0;
            $remaining = max(0, $capacity - $sold);
            $result[] = [
                'time' => $slot,
                'capacity' => $capacity,
                'remaining' => $remaining,
                'sold_out' => $remaining === 0,
            ];
        }

        return response()->json([
            'date' => $dateStr,
            'ticket_type_id' => $tt->id,
            'capacity_per_slot' => $capacity,
            'duration_minutes' => (int) ($config['duration_minutes'] ?? 30),
            'unit_pricing' => $config['unit_pricing'] ?? 'per_person',
            'slots' => $result,
        ]);
    }

    /**
     * GET /marketplace-events/{identifier}/resource-availability?ticket_type_id=X&date=Y&start_time=HH:MM&duration_minutes=N
     *
     * Pentru un TicketType cu meta.physical_inventory.enabled = true, calculează
     * câte unități fizice rămân libere în intervalul cerut (overlap query pe
     * leisure_resource_locks).
     */
    public function resourceAvailability(Request $request, string $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $validated = $request->validate([
            'ticket_type_id' => 'required|integer',
            'date' => 'required|date',
            'start_time' => 'required|string|regex:/^\d{2}:\d{2}$/',
            'duration_minutes' => 'required|integer|min:5|max:1440',
        ]);

        $event = Event::query()
            ->where('marketplace_client_id', $client->id)
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier);
                if (is_numeric($identifier)) $q->orWhere('id', (int) $identifier);
            })
            ->first();
        if (!$event) return response()->json(['error' => 'Event not found'], 404);

        $tt = TicketType::query()
            ->where('id', $validated['ticket_type_id'])
            ->where('event_id', $event->id)
            ->first();
        if (!$tt) return response()->json(['error' => 'Ticket type not found'], 404);

        $physical = is_array($tt->meta['physical_inventory'] ?? null) ? $tt->meta['physical_inventory'] : null;
        if (!$physical || empty($physical['enabled'])) {
            return response()->json(['error' => 'Physical inventory not enabled for this ticket type'], 422);
        }

        $count = max(1, (int) ($physical['count'] ?? 1));
        $start = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $end = $start->copy()->addMinutes((int) $validated['duration_minutes']);

        $available = LeisureResourceLock::availableForInterval($tt->id, $count, $start, $end);

        return response()->json([
            'ticket_type_id' => $tt->id,
            'date' => $validated['date'],
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'duration_minutes' => (int) $validated['duration_minutes'],
            'physical_count' => $count,
            'available' => $available,
            'sold_out' => $available === 0,
        ]);
    }

    /**
     * Generate slot times array from config (e.g. ['09:00', '09:30', '10:00', ...]).
     */
    protected function generateSlots(array $config): array
    {
        $first = $config['first_slot'] ?? '09:00';
        $last = $config['last_slot'] ?? '18:00';
        $interval = max(5, (int) ($config['interval_minutes'] ?? 30));

        try {
            $start = Carbon::createFromFormat('H:i', $first);
            $end = Carbon::createFromFormat('H:i', $last);
        } catch (\Throwable $e) {
            return [];
        }

        $slots = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $slots[] = $cur->format('H:i');
            $cur->addMinutes($interval);
            if (count($slots) > 200) break; // safety
        }
        return $slots;
    }
}
