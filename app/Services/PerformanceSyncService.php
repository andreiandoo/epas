<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Performance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PerformanceSyncService
{
    /**
     * Sync Performance records from Event's multi_slots JSON.
     * Creates/updates/deletes performances to match the slots.
     * PRESERVES ticket_overrides on existing performances.
     */
    public function syncFromMultiSlots(Event $event): void
    {
        if ($event->duration_mode !== 'multi_day') {
            return;
        }

        $slots = $event->multi_slots ?? [];
        if (empty($slots)) {
            return;
        }

        $existing = $event->performances()->get()->keyBy(
            fn (Performance $p) => $p->starts_at->format('Y-m-d H:i')
        );

        $processedKeys = [];

        foreach ($slots as $slot) {
            $date = $slot['date'] ?? null;
            if (!$date) continue;

            $startTime = $slot['start_time'] ?? '00:00';
            $key = $date . ' ' . $startTime;
            $startsAt = Carbon::parse($key);
            $endsAt = !empty($slot['end_time']) ? Carbon::parse($date . ' ' . $slot['end_time']) : null;
            $doorTime = $slot['door_time'] ?? null;

            if ($existing->has($key)) {
                // Update existing — KEEP ticket_overrides intact
                $existing[$key]->update([
                    'ends_at' => $endsAt,
                    'door_time' => $doorTime,
                ]);
            } else {
                // Create new performance
                Performance::create([
                    'event_id' => $event->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'door_time' => $doorTime,
                    'status' => 'active',
                    'label' => $startsAt->format('D, d M Y · H:i'),
                ]);
            }

            $processedKeys[] = $key;
        }

        // Delete performances that no longer have a matching slot
        // (only those without sold tickets)
        $event->performances()
            ->get()
            ->filter(fn (Performance $p) => !in_array($p->starts_at->format('Y-m-d H:i'), $processedKeys))
            ->each(function (Performance $p) {
                // Only delete if no tickets sold for this performance
                if ($p->tickets()->where('status', '!=', 'cancelled')->count() === 0) {
                    $p->delete();
                }
            });
    }
}
