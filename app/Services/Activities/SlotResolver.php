<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityBooking;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compute bookable time slots for an Activity on a given calendar date.
 *
 * Input model:
 *   - Activity carries the recurring intervals (weekly schedule), per-date
 *     overrides (exceptions), and the timing knobs:
 *       duration_minutes, slot_interval_minutes, buffer_minutes,
 *       capacity_per_slot, booking_lead_time_hours, booking_max_advance_days
 *   - Existing bookings consume capacity per (activity, date, start_time).
 *
 * Output:
 *   - A flat collection of slot objects per requested date:
 *       [
 *         'start_time'         => '10:00:00',
 *         'end_time'           => '11:00:00',
 *         'start_at_iso'       => '2026-05-26T10:00:00+03:00',
 *         'capacity_total'     => 6,
 *         'capacity_remaining' => 3,
 *         'is_bookable'        => true,
 *         'unavailable_reason' => null,   // 'past' | 'lead_time' | 'full' | 'closed' | null
 *       ]
 *
 * Decisions baked in:
 *   - All times are interpreted in Europe/Bucharest. The Activity has no
 *     timezone column yet (single-country marketplace); making that
 *     per-activity is a future change. Hardcoded here so every caller
 *     gets the same answer.
 *   - Exceptions WIN over the weekly schedule. An is_closed=true exception
 *     blanks the day; an is_closed=false with overridden open/close
 *     replaces the weekly intervals.
 *   - Bookings that consume capacity = the CAPACITY_CONSUMING_STATUSES set
 *     on ActivityBooking. Pending-payment with an expired hold (held_until
 *     in the past) is NOT consumed — those are released by the scheduled
 *     job in A5 and shouldn't block new shoppers in the meantime.
 *   - `is_bookable=false` slots are still returned (so the UI can show
 *     them greyed out) but with a populated `unavailable_reason`.
 */
class SlotResolver
{
    /**
     * The application-wide timezone for activities. All slot times stored
     * on activity_schedules / activity_schedule_exceptions are interpreted
     * in this zone. Future per-marketplace timezone support would replace
     * this with a runtime resolver.
     */
    private const TIMEZONE = 'Europe/Bucharest';

    /**
     * @return Collection<int, array{
     *   start_time: string,
     *   end_time: string,
     *   start_at_iso: string,
     *   capacity_total: int,
     *   capacity_remaining: int,
     *   is_bookable: bool,
     *   unavailable_reason: ?string,
     * }>
     */
    public static function slotsFor(Activity $activity, CarbonImmutable $date): Collection
    {
        $date = $date->setTimezone(self::TIMEZONE)->startOfDay();
        $now  = CarbonImmutable::now(self::TIMEZONE);

        // 1. Window check: is this date even allowed?
        $maxAdvance = $now->copy()->addDays((int) ($activity->booking_max_advance_days ?? 60))->endOfDay();
        $today      = $now->copy()->startOfDay();
        $outsideWindow = $date->lt($today) || $date->gt($maxAdvance);

        // 2. Resolve operating intervals (exception > weekly schedule).
        $intervals = self::resolveIntervals($activity, $date);
        if (empty($intervals)) {
            return collect();   // closed day → no slot rows at all
        }

        // 3. Pull existing booking consumption for this (activity, date) in
        //    a single query to avoid N+1 in the slot loop.
        $consumed = self::consumedCapacityMap($activity->id, $date);

        // 4. Materialise slots from each interval.
        $slots = collect();
        $duration = max(5, (int) ($activity->duration_minutes ?? 60));
        $interval = max($duration, (int) ($activity->slot_interval_minutes ?? 60));
        $buffer   = max(0, (int) ($activity->buffer_minutes ?? 0));
        $capTotal = max(1, (int) ($activity->capacity_per_slot ?? 1));
        $leadTime = max(0, (int) ($activity->booking_lead_time_hours ?? 0));
        $leadCutoff = $now->copy()->addHours($leadTime);

        foreach ($intervals as [$openTime, $closeTime]) {
            // The cursor walks `open` → `close - duration` in `interval` steps.
            // We use CarbonImmutable per iteration so the start point isn't mutated.
            $cursor = $date->copy()
                ->setTime((int) substr($openTime, 0, 2), (int) substr($openTime, 3, 2));
            $closeAt = $date->copy()
                ->setTime((int) substr($closeTime, 0, 2), (int) substr($closeTime, 3, 2));

            while (true) {
                $endAt = $cursor->copy()->addMinutes($duration);
                if ($endAt->gt($closeAt)) {
                    break;
                }

                $key = $cursor->format('H:i:s');
                $consumedHere = (int) ($consumed[$key] ?? 0);
                $remaining = max(0, $capTotal - $consumedHere);

                $reason = null;
                if ($outsideWindow) {
                    $reason = $date->lt($today) ? 'past' : 'too_far_in_future';
                } elseif ($cursor->lt($leadCutoff)) {
                    $reason = 'lead_time';
                } elseif ($remaining === 0) {
                    $reason = 'full';
                }

                $slots->push([
                    'start_time'         => $cursor->format('H:i:s'),
                    'end_time'           => $endAt->format('H:i:s'),
                    'start_at_iso'       => $cursor->toIso8601String(),
                    'capacity_total'     => $capTotal,
                    'capacity_remaining' => $remaining,
                    'is_bookable'        => $reason === null,
                    'unavailable_reason' => $reason,
                ]);

                $cursor = $cursor->copy()->addMinutes($interval + $buffer);
            }
        }

        return $slots;
    }

    /**
     * Sweep across a date range and return only those dates that have at
     * least one bookable slot. Used by the public page to enable/disable
     * cells on the calendar picker without firing one API call per day.
     *
     * @return Collection<int, string> Y-m-d strings.
     */
    public static function bookableDatesBetween(Activity $activity, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $dates = collect();
        $cursor = $start->copy()->startOfDay();
        $end    = $end->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $slots = self::slotsFor($activity, $cursor);
            if ($slots->where('is_bookable', true)->isNotEmpty()) {
                $dates->push($cursor->format('Y-m-d'));
            }
            $cursor = $cursor->copy()->addDay();
        }

        return $dates;
    }

    // ============================================================
    // INTERNALS
    // ============================================================

    /**
     * @return array<int, array{0: string, 1: string}>  Pairs of [openTime, closeTime] (H:i:s).
     */
    private static function resolveIntervals(Activity $activity, CarbonImmutable $date): array
    {
        // Exception override (single row per date).
        $exception = $activity->scheduleExceptions
            ->first(fn ($x) => Carbon::parse($x->exception_date)->isSameDay($date));

        if ($exception) {
            if ($exception->is_closed) {
                return [];   // hard closed for the day
            }
            // Custom hours for this date.
            if (! $exception->open_time || ! $exception->close_time) {
                return [];   // malformed exception — treat as closed (defensive)
            }
            return [[
                self::asTimeString($exception->open_time),
                self::asTimeString($exception->close_time),
            ]];
        }

        // Fall through to weekly schedule for this day_of_week.
        // Carbon::dayOfWeekIso() returns 1..7 (Mon..Sun) — matches our column convention.
        $isoDow = (int) $date->dayOfWeekIso;

        return $activity->schedules
            ->where('day_of_week', $isoDow)
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->map(fn ($s) => [
                self::asTimeString($s->open_time),
                self::asTimeString($s->close_time),
            ])
            ->values()
            ->all();
    }

    /**
     * Map of slot_start_time → participants_count for the given activity+date.
     * One query covers the whole day so the slot loop is constant-time per slot.
     *
     * @return array<string, int>
     */
    private static function consumedCapacityMap(int $activityId, CarbonImmutable $date): array
    {
        $rows = DB::table('activity_bookings')
            ->where('activity_id', $activityId)
            ->whereDate('booking_date', $date->toDateString())
            ->whereIn('status', ActivityBooking::CAPACITY_CONSUMING_STATUSES)
            // Pending bookings with an expired hold don't consume capacity. The hold-
            // sweep job will release them; until it runs, we treat them as released
            // already so a customer who just abandoned checkout doesn't block the slot.
            ->where(function ($q) {
                $q->where('status', '<>', ActivityBooking::STATUS_PENDING_PAYMENT)
                    ->orWhereNull('held_until')
                    ->orWhere('held_until', '>=', now());
            })
            ->whereNull('deleted_at')
            ->select('slot_start_time', DB::raw('SUM(participants_count) AS consumed'))
            ->groupBy('slot_start_time')
            ->pluck('consumed', 'slot_start_time')
            ->toArray();

        // Normalise keys to H:i:s — DB returns whatever the column shape is.
        $out = [];
        foreach ($rows as $time => $count) {
            $key = self::asTimeString($time);
            $out[$key] = (int) $count;
        }
        return $out;
    }

    /**
     * Normalise time inputs (Carbon datetime, raw "HH:MM:SS" string, etc.) to
     * a "HH:MM:SS" string. Activity casts store these as datetime:H:i:s which
     * gives a full Carbon object — we only care about the time portion.
     */
    private static function asTimeString($value): string
    {
        if ($value === null || $value === '') {
            return '00:00:00';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        // Already a "HH:MM" or "HH:MM:SS" string.
        $s = (string) $value;
        return strlen($s) === 5 ? $s . ':00' : $s;
    }
}
