<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Computes FOMO social-proof figures for an event:
 *   - inflated 24h sold count
 *   - synthetic concurrent viewers count
 *   - "seats remaining" counter with a monotonic ratchet (never bumps up
 *     between visits, even when real inventory does — see explanation
 *     on computeRemainingDisplayed)
 *   - a small library of toast message templates ready for the front-end
 *     to rotate.
 *
 * Returns null when the per-event toggle is off — every call site can
 * short-circuit without a second config check.
 *
 * Magnitudes follow the "conservative" preset the user picked:
 *   24h sold  = max(11, real_24h * 2.5) + tiny deterministic jitter
 *   viewers   = 4..20 driven by hour-of-day, weekend boost, event proximity
 *   remaining = ratchet that starts at 50% of real and decays daily
 */
class FomoStatsService
{
    /**
     * Public entry point. Returns null when FOMO is disabled for the
     * event so callers can `?? return` without a guard.
     */
    public function getStatsForEvent(int $eventId): ?array
    {
        try {
            $event = Event::find($eventId);
            if (!$event || !$event->generate_fomo) {
                return null;
            }

            $totalCapacity = $this->totalCapacity($event);
            $totalSold = $this->totalSold($event);
            $realRemaining = max(0, $totalCapacity - $totalSold);

            $soldLast24h = $this->computeSoldLast24h($event, $totalCapacity);
            $viewersNow = $this->computeViewersNow($event);
            $remainingDisplayed = $this->computeRemainingDisplayed($event, $realRemaining);

            $inventoryPercent = $totalCapacity > 0
                ? (int) max(8, min(96, round(100 * ($totalCapacity - $remainingDisplayed) / max(1, $totalCapacity))))
                : 50;

            return [
                'enabled' => true,
                'sold_last_24h' => $soldLast24h,
                'viewers_now' => $viewersNow,
                'remaining_displayed' => $remainingDisplayed,
                'inventory_percent' => $inventoryPercent,
                'toast_messages' => $this->buildToastMessages($event, $soldLast24h),
                'city' => $this->resolveCity($event),
            ];
        } catch (\Throwable $e) {
            Log::warning('FomoStatsService failed — returning null', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ── numbers ──────────────────────────────────────────────────────────

    /**
     * Inflated 24h sold count. Real count × 2.5, floored at 11 so a quiet
     * day still reads as "active". Caps at total capacity so we never
     * promise more sold than seats exist.
     */
    protected function computeSoldLast24h(Event $event, int $totalCapacity): int
    {
        $real = Ticket::where('event_id', $event->id)
            ->whereIn('status', ['valid', 'used'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $inflated = (int) max(11, ceil($real * 2.5));

        // Deterministic per-day jitter (0..4) so the number is stable
        // within a day but not always the exact same multiple of 11.
        $jitter = abs(crc32($event->id . '_24h_' . now()->format('Y-m-d'))) % 5;

        $value = $inflated + (int) $jitter;
        return $totalCapacity > 0 ? min($value, $totalCapacity) : $value;
    }

    /**
     * Synthetic concurrent viewers. Driven by:
     *   - hour-of-day curve (peak 19:00..22:00)
     *   - weekend / Friday boost (Fri/Sat/Sun)
     *   - proximity to event date (closer → more)
     *   - deterministic minute-level jitter so successive page loads
     *     within the same minute give the same answer.
     *
     * Output clamped to [4, 20].
     */
    protected function computeViewersNow(Event $event): int
    {
        $now = Carbon::now('Europe/Bucharest');
        $hour = $now->hour;
        $dow = $now->dayOfWeek; // 0=Sun .. 6=Sat

        $hourFactor = match (true) {
            $hour >= 19 && $hour <= 22 => 1.00,
            $hour >= 17 && $hour < 19 => 0.85,
            $hour >= 12 && $hour < 17 => 0.65,
            $hour >= 9 && $hour < 12 => 0.55,
            default => 0.40, // 23..08
        };

        $dowFactor = in_array($dow, [0, 5, 6], true) ? 1.20 : 1.00;

        $daysToEvent = $this->daysToEvent($event);
        $proximityFactor = $daysToEvent <= 0
            ? 1.00
            : min(1.5, 1.0 + (7 - min(7, $daysToEvent)) * 0.07);

        $base = 9.0;

        $jitter = (int) (abs(crc32($event->id . '_viewers_' . $now->format('Y-m-d-H-i'))) % 7) - 3;

        $value = (int) round($base * $hourFactor * $dowFactor * $proximityFactor + $jitter);

        return max(4, min(20, $value));
    }

    /**
     * Monotonic "seats remaining" counter.
     *
     * The naive approach (display = real_remaining × 0.45) yo-yos when
     * inventory fluctuates: a refund returns 5 seats to the pool and the
     * page jumps from "8 left" to "12 left" — the exact failure mode the
     * user flagged ("azi 18, mâine 23, poimâine 5").
     *
     * Solution: persist the value on the events row and ratchet down only.
     *   1. Bootstrap = 50% of real_remaining (rounded down, floor 1).
     *   2. On each refresh, candidate = min(prior, target, real_remaining)
     *      where target = round(real_remaining × 0.45). Ensures display
     *      tracks scarcity but never exceeds the last shown value.
     *   3. Forced daily decrement so the number keeps moving even when
     *      no real sales happen (and no refunds bump real up). Caps so
     *      we don't blow through the supply.
     *
     * The persisted column survives Redis flushes and cache rebuilds.
     */
    protected function computeRemainingDisplayed(Event $event, int $realRemaining): int
    {
        $prior = $event->fomo_displayed_remaining;
        $priorAt = $event->fomo_displayed_remaining_updated_at;

        // First-time bootstrap.
        if ($prior === null || $prior <= 0) {
            $new = max(1, (int) floor($realRemaining * 0.5));
            $event->forceFill([
                'fomo_displayed_remaining' => $new,
                'fomo_displayed_remaining_updated_at' => now(),
            ])->save();
            return $new;
        }

        $target = max(1, (int) round($realRemaining * 0.45));

        // Daily forced decrement: 1..3 seats per elapsed day, scaled
        // a little to prior so we don't make a 200-seat hall drop by 1.
        $hoursSinceUpdate = $priorAt instanceof Carbon
            ? $priorAt->diffInHours(now(), false)
            : 24;
        $decayDays = $hoursSinceUpdate >= 24 ? (int) floor($hoursSinceUpdate / 24) : 0;
        $forcedDecrement = $decayDays > 0
            ? min(5 * $decayDays, max($decayDays, (int) round($prior * 0.04 * $decayDays)))
            : 0;

        $candidate = max(1, $prior - $forcedDecrement);
        $new = max(1, min($candidate, $target, max(1, $realRemaining)));

        if ($new !== $prior || $decayDays > 0) {
            $event->forceFill([
                'fomo_displayed_remaining' => $new,
                'fomo_displayed_remaining_updated_at' => now(),
            ])->save();
        }

        return $new;
    }

    // ── toast library ────────────────────────────────────────────────────

    /**
     * Mixed library: vague-but-evergreen + concrete (with the actual 24h
     * count). Front-end rotates these every ~16s.
     */
    protected function buildToastMessages(Event $event, int $soldLast24h): array
    {
        $city = $this->resolveCity($event);
        $cityLabel = $city ? "în {$city}" : '';

        $vague = [
            ['title' => trim("Eveniment popular {$cityLabel}"), 'text' => 'Cerere crescută în ultimele ore.'],
            ['title' => '3 locuri au fost rezervate recent', 'text' => 'Disponibilitatea se poate modifica rapid.'],
            ['title' => 'Cerere ridicată în acest moment', 'text' => 'Mai multe persoane se uită la acest eveniment.'],
        ];

        $concrete = [
            ['title' => "{$soldLast24h} bilete vândute în ultimele 24h", 'text' => 'Mai sunt locuri disponibile.'],
            ['title' => 'O persoană a cumpărat 2 bilete', 'text' => 'Achiziție confirmată recent.'],
            ['title' => 'Cineva tocmai și-a rezervat locul', 'text' => 'Asigură-ți și tu biletele.'],
        ];

        // Trim by city availability and randomize-stable for the day.
        $library = array_merge($vague, $concrete);
        $seed = abs(crc32($event->id . '_toast_' . now()->format('Y-m-d')));
        $library = $this->pickWithSeed($library, 4, $seed);

        return array_values($library);
    }

    protected function pickWithSeed(array $items, int $count, int $seed): array
    {
        // Deterministic shuffle: Fisher-Yates with seeded mt_rand.
        mt_srand($seed);
        $arr = $items;
        for ($i = count($arr) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
        mt_srand();
        return array_slice($arr, 0, min($count, count($arr)));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    protected function totalCapacity(Event $event): int
    {
        $sum = TicketType::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNull('quota_total')
                    ->orWhere('quota_total', '>=', 0);
            })
            ->sum('quota_total');

        // quota_total < 0 or null means unlimited — for FOMO purposes we
        // treat unlimited tiers as "huge but not infinite" so the scarcity
        // bar still renders something sensible.
        $hasUnlimited = TicketType::where('event_id', $event->id)
            ->where(function ($q) {
                $q->whereNull('quota_total')
                    ->orWhere('quota_total', '<', 0);
            })
            ->exists();

        if ($hasUnlimited) {
            return max((int) $sum, 200);
        }
        return (int) $sum;
    }

    protected function totalSold(Event $event): int
    {
        return (int) TicketType::where('event_id', $event->id)->sum('quota_sold');
    }

    protected function daysToEvent(Event $event): int
    {
        $date = $event->event_date ?? $event->range_start_date ?? null;
        if (!$date) {
            return 30; // unknown — treat as far away.
        }
        $target = Carbon::parse($date)->endOfDay();
        return (int) max(0, now()->diffInDays($target, false));
    }

    protected function resolveCity(Event $event): ?string
    {
        $venue = $event->venue ?? null;
        if ($venue && !empty($venue->city)) {
            return $venue->city;
        }
        return null;
    }
}
