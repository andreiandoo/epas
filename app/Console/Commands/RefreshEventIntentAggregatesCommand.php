<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes the cached aggregates that intent landing pages filter on:
 *   - cheapest_price_cents (smallest active ticket_types.price_cents)
 *   - next_session_at (earliest upcoming session datetime)
 *   - has_session_today / _tomorrow / _this_weekend booleans
 *
 * Designed to run frequently (default: hourly via scheduler). Idempotent —
 * safe to run on demand. Targets only events that are published and not
 * past-dated, so the working set stays small even on busy marketplaces.
 *
 * Usage:
 *   php artisan events:refresh-intent-aggregates
 *   php artisan events:refresh-intent-aggregates --marketplace=3
 *   php artisan events:refresh-intent-aggregates --event=42
 */
class RefreshEventIntentAggregatesCommand extends Command
{
    protected $signature = 'events:refresh-intent-aggregates
        {--marketplace= : Scope to a single marketplace_client_id}
        {--event= : Refresh a single event by id}';

    protected $description = 'Recompute cheapest_price_cents, next_session_at and has_session_* flags on events for intent landing pages.';

    public function handle(): int
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        // Weekend = the nearest upcoming Saturday + Sunday. If today is already
        // Saturday/Sunday, use the current weekend pair.
        $saturday = $today->copy()->next(Carbon::SATURDAY);
        if ($today->isWeekend()) {
            $saturday = $today->copy()->startOfWeek(Carbon::SATURDAY);
        }
        $sunday = $saturday->copy()->addDay();

        $query = Event::query()
            ->where('is_published', true)
            ->where(function ($q) {
                $q->whereDate('event_date', '>=', now()->toDateString())
                    ->orWhereNull('event_date');
            });

        if ($marketplaceId = $this->option('marketplace')) {
            $query->where('marketplace_client_id', $marketplaceId);
        }
        if ($eventId = $this->option('event')) {
            $query->where('id', $eventId);
        }

        $updated = 0;
        $query->with(['ticketTypes', 'performances'])->chunkById(200, function ($events) use (&$updated, $today, $tomorrow, $saturday, $sunday) {
            foreach ($events as $event) {
                $values = $this->computeFor($event, $today, $tomorrow, $saturday, $sunday);

                // Skip the global model event handlers — these are pure
                // denormalised aggregates and shouldn't trigger observers.
                DB::table('events')->where('id', $event->id)->update($values);
                $updated++;
            }
        });

        $this->info("Refreshed aggregates on {$updated} events.");
        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>  Column-keyed values to UPDATE on the event row.
     */
    protected function computeFor(Event $event, Carbon $today, Carbon $tomorrow, Carbon $saturday, Carbon $sunday): array
    {
        // Cheapest active ticket price in cents
        $cheapest = null;
        if ($event->relationLoaded('ticketTypes')) {
            $prices = $event->ticketTypes
                ->where('status', 'active')
                ->pluck('price_cents')
                ->filter(fn ($p) => $p !== null)
                ->all();
            if (!empty($prices)) {
                $cheapest = min($prices);
            }
        }

        // Collect every candidate session datetime
        $sessions = collect();
        if ($event->relationLoaded('performances')) {
            foreach ($event->performances as $perf) {
                if (in_array($perf->status, ['cancelled', 'archived'], true)) continue;
                if ($perf->starts_at) {
                    $sessions->push(Carbon::parse($perf->starts_at));
                }
            }
        }
        if (is_array($event->multi_slots) && !empty($event->multi_slots)) {
            foreach ($event->multi_slots as $slot) {
                if (empty($slot['date'])) continue;
                $time = $slot['start_time'] ?? '00:00';
                try {
                    $sessions->push(Carbon::parse($slot['date'] . ' ' . $time));
                } catch (\Throwable $e) {
                    // skip malformed slot
                }
            }
        }
        if ($event->event_date && $sessions->isEmpty()) {
            $time = $event->start_time ?: '00:00:00';
            try {
                $sessions->push(Carbon::parse($event->event_date->toDateString() . ' ' . $time));
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $now = now();
        $upcoming = $sessions->filter(fn ($d) => $d->gte($now))->sort();
        $next = $upcoming->first();

        $hasToday = $upcoming->contains(fn ($d) => $d->isSameDay($today));
        $hasTomorrow = $upcoming->contains(fn ($d) => $d->isSameDay($tomorrow));
        $hasWeekend = $upcoming->contains(fn ($d) => $d->isSameDay($saturday) || $d->isSameDay($sunday));

        return [
            'cheapest_price_cents' => $cheapest,
            'next_session_at' => $next?->toDateTimeString(),
            'has_session_today' => $hasToday,
            'has_session_tomorrow' => $hasTomorrow,
            'has_session_this_weekend' => $hasWeekend,
            'updated_at' => $now->toDateTimeString(),
        ];
    }
}
