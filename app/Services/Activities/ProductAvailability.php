<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\ActivityLocation;
use App\Models\ActivityVariant;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Availability of a product (access ticket, experience) on a date.
 *
 * Opening hours, in order of precedence:
 *   1. a schedule exception for the date (closed, or custom hours);
 *   2. the location's closed dates;
 *   3. the location's seasons, when the product uses them;
 *   4. the product's weekly schedule rows, each optionally limited to a
 *      season ("MM-DD" → "MM-DD", repeated every year).
 * A day-mode product with no schedule at all is open every day.
 *
 * Capacity:
 *   day mode              daily_capacity per covered date (multi-day tickets
 *                         use a seat on every date they cover); null = unlimited
 *   slot mode, per_slot   capacity_per_slot for each start time (tours)
 *   slot mode, concurrent capacity_per_slot units shared over time: a 60-min
 *                         and a 30-min boat overlap and use two boats (rentals)
 *   daily_capacity also caps slot-mode products when set.
 *
 * Seats are counted from bookings in CAPACITY_CONSUMING_STATUSES, ignoring
 * pending bookings whose hold ran out. Lines of the cart being checked out are
 * added with stage() so two lines can't take the same last seat.
 */
class ProductAvailability
{
    public const TIMEZONE = 'Europe/Bucharest';

    /** Seats taken by cart lines checked earlier in this checkout. */
    private array $staged = ['day' => [], 'slot' => [], 'span' => []];

    // ================================================================
    // Hours and booking window
    // ================================================================

    /**
     * @return array{open: bool, intervals: array<int, array{0: string, 1: string}>, last_entry: ?string, all_day: bool}
     */
    public function hours(Activity $activity, CarbonImmutable $date): array
    {
        $closed = ['open' => false, 'intervals' => [], 'last_entry' => null, 'all_day' => false];

        $exception = $activity->scheduleExceptions
            ->first(fn ($x) => Carbon::parse($x->exception_date)->isSameDay($date));
        if ($exception) {
            if ($exception->is_closed || !$exception->open_time || !$exception->close_time) {
                return $closed;
            }
            return [
                'open' => true,
                'intervals' => [[self::time($exception->open_time), self::time($exception->close_time)]],
                'last_entry' => null,
                'all_day' => false,
            ];
        }

        $location = $activity->location;
        if ($location && $location->isClosedOn($date)) {
            return $closed;
        }

        if ($activity->use_location_schedule && $location && !empty($location->seasons)) {
            $hours = $location->hoursOn($date);
            if (!$hours) {
                return $closed;
            }
            return [
                'open' => true,
                'intervals' => [[$hours['open'], $hours['close']]],
                'last_entry' => $hours['last_entry'],
                'all_day' => false,
            ];
        }

        $rows = $activity->schedules->where('is_active', true);
        if ($rows->isEmpty()) {
            // Nothing defined: a day ticket is valid any day; a timed product
            // has no start times.
            return $activity->isDayMode()
                ? ['open' => true, 'intervals' => [], 'last_entry' => null, 'all_day' => true]
                : $closed;
        }

        $dow = (int) $date->dayOfWeekIso;
        $md  = $date->format('m-d');
        $intervals = $rows
            ->filter(fn ($s) => (int) $s->day_of_week === $dow && self::inSeason($md, $s->season_start, $s->season_end))
            ->sortBy('sort_order')
            ->map(fn ($s) => [self::time($s->open_time), self::time($s->close_time)])
            ->filter(fn ($pair) => $pair[0] < $pair[1])
            ->values()
            ->all();

        return $intervals
            ? ['open' => true, 'intervals' => $intervals, 'last_entry' => null, 'all_day' => false]
            : $closed;
    }

    /** At the operator's desk (POS): no lead time before a start time or before the day's last entry. */
    private bool $desk = false;

    public function atDesk(): static
    {
        $this->desk = true;
        return $this;
    }

    private function leadHours(Activity $activity): int
    {
        return $this->desk ? 0 : (int) ($activity->booking_lead_time_hours ?? 0);
    }

    /** 'past', 'too_far' or null. */
    public function windowReason(Activity $activity, CarbonImmutable $date): ?string
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        if ($date->lt($today)) {
            return 'past';
        }
        if ($date->gt($today->addDays($this->maxAdvanceDays($activity)))) {
            return 'too_far';
        }
        return null;
    }

    public function maxAdvanceDays(Activity $activity): int
    {
        $days = (int) ($activity->booking_max_advance_days ?: 60);
        if ($activity->location && $activity->location->max_advance_days) {
            $days = min($days, (int) $activity->location->max_advance_days);
        }
        return max(0, $days);
    }

    // ================================================================
    // Day mode
    // ================================================================

    /**
     * @return array{bookable: bool, reason: ?string, capacity: ?int, remaining: ?int, hours: array}
     */
    public function day(Activity $activity, CarbonImmutable $date): array
    {
        $date  = $date->setTimezone(self::TIMEZONE)->startOfDay();
        $hours = $this->hours($activity, $date);
        $cap   = $activity->daily_capacity !== null ? (int) $activity->daily_capacity : null;
        $left  = $cap === null ? null : max(0, $cap - $this->takenOnDay($activity->id, $date->toDateString()));

        $reason = $this->windowReason($activity, $date);
        if (!$reason && !$hours['open']) {
            $reason = 'closed';
        }
        if (!$reason && $date->isToday() && !$hours['all_day']) {
            // Today: sellable until the last entry (or closing time), minus the lead time.
            $cutoff = $hours['last_entry'] ?? max(array_column($hours['intervals'], 1));
            $limit  = $date->setTimeFromTimeString($cutoff)->subHours($this->leadHours($activity));
            if (CarbonImmutable::now(self::TIMEZONE)->gt($limit)) {
                $reason = 'too_late';
            }
        }
        if (!$reason && $left !== null && $left <= 0) {
            $reason = 'full';
        }

        return [
            'bookable'  => $reason === null,
            'reason'    => $reason,
            'capacity'  => $cap,
            'remaining' => $left,
            'hours'     => $hours,
        ];
    }

    // ================================================================
    // Slot mode
    // ================================================================

    /**
     * Start times on a date. $variant sets the duration when it has its own
     * (30 vs 60 min rentals).
     *
     * @return array<int, array{start_time: string, end_time: string, capacity_total: int, capacity_remaining: int, is_bookable: bool, unavailable_reason: ?string}>
     */
    public function slots(Activity $activity, CarbonImmutable $date, ?ActivityVariant $variant = null): array
    {
        $date  = $date->setTimezone(self::TIMEZONE)->startOfDay();
        $hours = $this->hours($activity, $date);
        if (!$hours['open'] || !$hours['intervals']) {
            return [];
        }

        $duration   = $this->durationFor($activity, $variant);
        $interval   = max(5, (int) ($activity->slot_interval_minutes ?: 60));
        $capacity   = max(1, (int) ($activity->capacity_per_slot ?: 1));
        $window     = $this->windowReason($activity, $date);
        $leadCutoff = CarbonImmutable::now(self::TIMEZONE)->addHours($this->leadHours($activity));
        $lastEntry  = $hours['last_entry'] ? self::minutes($hours['last_entry']) : null;
        $dayLeft    = $this->dayCapLeft($activity, $date->toDateString());
        $bookings   = $this->bookingsOn($activity->id, $date->toDateString());

        $slots = [];
        foreach ($hours['intervals'] as [$open, $close]) {
            $closeMin = self::minutes($close);
            for ($start = self::minutes($open); $start + $duration <= $closeMin; $start += $interval) {
                if ($lastEntry !== null && $start > $lastEntry) {
                    break;
                }
                $left = $this->leftForSlot($activity, $date->toDateString(), $start, $start + $duration, $capacity, $bookings);
                if ($dayLeft !== null) {
                    $left = min($left, $dayLeft);
                }

                $startAt = $date->addMinutes($start);
                $reason  = $window;
                if (!$reason && $startAt->lt($leadCutoff)) {
                    $reason = 'lead_time';
                }
                if (!$reason && $left <= 0) {
                    $reason = 'full';
                }

                $slots[] = [
                    'start_time'         => self::clock($start),
                    'end_time'           => self::clock($start + $duration),
                    'capacity_total'     => $capacity,
                    'capacity_remaining' => max(0, $left),
                    'is_bookable'        => $reason === null,
                    'unavailable_reason' => $reason,
                ];
            }
        }

        return $slots;
    }

    public function durationFor(Activity $activity, ?ActivityVariant $variant): int
    {
        return max(5, (int) ($variant?->duration_minutes ?: $activity->duration_minutes ?: 60));
    }

    // ================================================================
    // Checkout
    // ================================================================

    /**
     * Can $consume seats be booked? Returns null when yes, otherwise the
     * reason in Romanian for the customer. For slot mode $start must be one
     * of the product's start times; for day mode every date covered by
     * $validityDays must be open and have room.
     *
     * @return array{error: ?string, end_date: ?string, slot_end: ?string}
     */
    public function check(Activity $activity, ?ActivityVariant $variant, string $date, ?string $start, int $consume, int $validityDays = 1): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, self::TIMEZONE)->startOfDay();

        if ($activity->isDayMode()) {
            $validityDays = max(1, $validityDays);
            for ($i = 0; $i < $validityDays; $i++) {
                $d      = $day->addDays($i);
                $status = $this->day($activity, $d);
                // Only the first day has to respect the sales window and "too late".
                $reason = $status['reason'];
                if ($i > 0 && in_array($reason, ['too_far', 'too_late'], true)) {
                    $reason = null;
                }
                if ($reason && $reason !== 'full') {
                    return ['error' => $this->dayReasonText($reason, $d), 'end_date' => null, 'slot_end' => null];
                }
                $left = $status['remaining'];
                if ($left !== null) {
                    $left -= $this->staged['day'][$activity->id . '|' . $d->toDateString()] ?? 0;
                    if ($consume > $left) {
                        return [
                            'error' => $left > 0
                                ? sprintf('Mai sunt doar %d locuri pe %s.', $left, $d->format('d.m.Y'))
                                : sprintf('Nu mai sunt locuri pe %s.', $d->format('d.m.Y')),
                            'end_date' => null,
                            'slot_end' => null,
                        ];
                    }
                }
            }
            return [
                'error'    => null,
                'end_date' => $validityDays > 1 ? $day->addDays($validityDays - 1)->toDateString() : null,
                'slot_end' => null,
            ];
        }

        $start = $start ? self::time($start) : null;
        if (!$start) {
            return ['error' => 'Alege ora.', 'end_date' => null, 'slot_end' => null];
        }
        $slot = collect($this->slots($activity, $day, $variant))->firstWhere('start_time', $start);
        if (!$slot) {
            return ['error' => sprintf('Ora %s nu e disponibilă pe %s.', substr($start, 0, 5), $day->format('d.m.Y')), 'end_date' => null, 'slot_end' => null];
        }
        if (!$slot['is_bookable'] && $slot['unavailable_reason'] !== 'full') {
            return ['error' => sprintf('Ora %s nu se mai poate rezerva.', substr($start, 0, 5)), 'end_date' => null, 'slot_end' => null];
        }

        $left = $slot['capacity_remaining'] - $this->stagedForSlot($activity, $date, self::minutes($start), self::minutes($slot['end_time']));
        if ($activity->daily_capacity !== null) {
            $left = min($left, (int) $this->dayCapLeft($activity, $date) - ($this->staged['day'][$activity->id . '|' . $date] ?? 0));
        }
        if ($consume > $left) {
            return [
                'error' => $left > 0
                    ? sprintf('La ora %s mai sunt doar %d locuri.', substr($start, 0, 5), $left)
                    : sprintf('Ora %s s-a ocupat.', substr($start, 0, 5)),
                'end_date' => null,
                'slot_end' => null,
            ];
        }

        return ['error' => null, 'end_date' => null, 'slot_end' => $slot['end_time']];
    }

    /** Record seats of a checked cart line so the next lines see them. */
    public function stage(Activity $activity, string $date, ?string $endDate, ?string $start, ?string $end, int $consume): void
    {
        if ($consume <= 0) {
            return;
        }
        if ($activity->isDayMode()) {
            $d    = CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay();
            $last = $endDate ? CarbonImmutable::createFromFormat('Y-m-d', $endDate)->startOfDay() : $d;
            for (; $d->lte($last); $d = $d->addDay()) {
                $key = $activity->id . '|' . $d->toDateString();
                $this->staged['day'][$key] = ($this->staged['day'][$key] ?? 0) + $consume;
            }
            return;
        }
        $dayKey = $activity->id . '|' . $date;
        $this->staged['day'][$dayKey] = ($this->staged['day'][$dayKey] ?? 0) + $consume;
        $this->staged['span'][$dayKey][] = [self::minutes($start), self::minutes($end), $consume];
    }

    /**
     * Would this booking overflow its slot / day if it held seats now?
     * (Late payment on a released booking.)
     */
    public function bookingOverflows(ActivityBooking $booking): bool
    {
        $activity = $booking->activity;
        if (!$activity) {
            return false;
        }
        $date = $booking->booking_date->toDateString();

        if ($activity->isDayMode()) {
            if ($activity->daily_capacity === null) {
                return false;
            }
            $last = $booking->end_date ?? $booking->booking_date;
            for ($d = CarbonImmutable::parse($date); $d->lte($last); $d = $d->addDay()) {
                $taken = $this->takenOnDay($activity->id, $d->toDateString(), $booking->id);
                if ($taken + (int) $booking->participants_count > (int) $activity->daily_capacity) {
                    return true;
                }
            }
            return false;
        }

        $start = $booking->getRawOriginal('slot_start_time');
        $end   = $booking->getRawOriginal('slot_end_time');
        if (!$start || !$end) {
            return false;
        }
        $capacity = max(1, (int) ($activity->capacity_per_slot ?: 1));
        $others   = array_filter($this->bookingsOn($activity->id, $date), fn ($b) => (int) $b->id !== (int) $booking->id);
        $left     = $this->leftForSlot($activity, $date, self::minutes($start), self::minutes($end), $capacity, $others);

        return (int) $booking->participants_count > $left;
    }

    // ================================================================
    // Calendar
    // ================================================================

    /**
     * Status per date: available | limited | full | closed, and the lowest
     * online price that day.
     *
     * @return array<int, array{date: string, status: string, remaining: ?int, min_price_cents: ?int}>
     */
    public function calendar(Activity $activity, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $minPrice = $activity->variants
            ->filter(fn ($v) => $v->is_active && !$v->pos_only)
            ->min('price_cents');

        $out = [];
        for ($d = $from->startOfDay(); $d->lte($to); $d = $d->addDay()) {
            if ($activity->isDayMode()) {
                $st = $this->day($activity, $d);
                $status = $st['bookable']
                    ? (($st['remaining'] !== null && $st['capacity'] && $st['remaining'] <= max(1, (int) floor($st['capacity'] * 0.1))) ? 'limited' : 'available')
                    : ($st['reason'] === 'full' ? 'full' : 'closed');
                $remaining = $st['remaining'];
            } else {
                $slots = collect($this->slots($activity, $d));
                $open  = $slots->where('is_bookable', true);
                $status = $open->isNotEmpty()
                    ? ($open->count() <= max(1, (int) floor($slots->count() * 0.2)) ? 'limited' : 'available')
                    : ($slots->isNotEmpty() && $slots->every(fn ($s) => $s['unavailable_reason'] === 'full') ? 'full' : 'closed');
                $remaining = $open->sum('capacity_remaining');
            }
            $out[] = [
                'date'            => $d->toDateString(),
                'status'          => $status,
                'remaining'       => $remaining,
                'min_price_cents' => in_array($status, ['available', 'limited'], true) ? $minPrice : null,
            ];
        }
        return $out;
    }

    // ================================================================
    // Internals
    // ================================================================

    /** Seats taken on a date by day-mode bookings covering it. */
    public function takenOnDay(int $activityId, string $date, ?int $excludeBookingId = null): int
    {
        return (int) $this->consuming()
            ->where('activity_id', $activityId)
            ->whereDate('booking_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereDate('end_date', '>=', $date)
                    ->orWhere(fn ($q2) => $q2->whereNull('end_date')->whereDate('booking_date', $date));
            })
            ->when($excludeBookingId, fn ($q) => $q->where('id', '<>', $excludeBookingId))
            ->sum('participants_count');
    }

    /** Remaining daily seats for a slot-mode product with a daily cap (null = no cap). */
    private function dayCapLeft(Activity $activity, string $date): ?int
    {
        if ($activity->daily_capacity === null || $activity->isDayMode()) {
            return null;
        }
        $taken = (int) $this->consuming()
            ->where('activity_id', $activity->id)
            ->whereDate('booking_date', $date)
            ->sum('participants_count');
        return max(0, (int) $activity->daily_capacity - $taken);
    }

    /** Timed bookings of a product on a date: objects with id, start, end (minutes), seats. */
    private function bookingsOn(int $activityId, string $date): array
    {
        return $this->consuming()
            ->where('activity_id', $activityId)
            ->whereDate('booking_date', $date)
            ->whereNotNull('slot_start_time')
            ->get(['id', 'slot_start_time', 'slot_end_time', 'participants_count'])
            ->map(fn ($b) => (object) [
                'id'    => (int) $b->id,
                'start' => self::minutes($b->slot_start_time),
                'end'   => $b->slot_end_time ? self::minutes($b->slot_end_time) : self::minutes($b->slot_start_time) + 1,
                'seats' => (int) $b->participants_count,
            ])
            ->all();
    }

    /**
     * Seats left for [start, end): per_slot counts bookings at the same start
     * time; concurrent takes the busiest moment inside the interval.
     */
    private function leftForSlot(Activity $activity, string $date, int $start, int $end, int $capacity, array $bookings): int
    {
        if ($activity->capacity_mode === Activity::CAPACITY_CONCURRENT) {
            return $capacity - $this->peak($start, $end, $bookings);
        }
        $taken = 0;
        foreach ($bookings as $b) {
            if ($b->start === $start) {
                $taken += $b->seats;
            }
        }
        return $capacity - $taken;
    }

    private function stagedForSlot(Activity $activity, string $date, int $start, int $end): int
    {
        $spans = $this->staged['span'][$activity->id . '|' . $date] ?? [];
        if (!$spans) {
            return 0;
        }
        $as = array_map(fn ($s) => (object) ['id' => 0, 'start' => $s[0], 'end' => $s[1], 'seats' => $s[2]], $spans);
        if ($activity->capacity_mode === Activity::CAPACITY_CONCURRENT) {
            return $this->peak($start, $end, $as);
        }
        return array_sum(array_map(fn ($s) => $s->start === $start ? $s->seats : 0, $as));
    }

    /** Most seats in use at any moment of [start, end). */
    private function peak(int $start, int $end, array $bookings): int
    {
        $points = [$start];
        foreach ($bookings as $b) {
            if ($b->start > $start && $b->start < $end) {
                $points[] = $b->start;
            }
        }
        $max = 0;
        foreach ($points as $t) {
            $inUse = 0;
            foreach ($bookings as $b) {
                if ($b->start <= $t && $b->end > $t) {
                    $inUse += $b->seats;
                }
            }
            $max = max($max, $inUse);
        }
        return $max;
    }

    private function consuming()
    {
        return DB::table('activity_bookings')
            ->whereIn('status', ActivityBooking::CAPACITY_CONSUMING_STATUSES)
            ->where(function ($q) {
                $q->where('status', '<>', ActivityBooking::STATUS_PENDING_PAYMENT)
                    ->orWhereNull('held_until')
                    ->orWhere('held_until', '>=', now());
            })
            ->whereNull('deleted_at');
    }

    private function dayReasonText(string $reason, CarbonImmutable $date): string
    {
        return match ($reason) {
            'past'     => 'Data aleasă a trecut.',
            'too_far'  => 'Data aleasă e prea departe; rezervările se deschid mai aproape de zi.',
            'too_late' => 'Pentru azi nu se mai vând bilete.',
            'closed'   => sprintf('Locația e închisă pe %s.', $date->format('d.m.Y')),
            default    => 'Data aleasă nu e disponibilă.',
        };
    }

    public static function inSeason(string $md, ?string $start, ?string $end): bool
    {
        if (!$start || !$end) {
            return true;
        }
        return $start <= $end ? ($md >= $start && $md <= $end) : ($md >= $start || $md <= $end);
    }

    /** "HH:MM" / "HH:MM:SS" / Carbon → "HH:MM:SS". */
    public static function time($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }
        $value = (string) $value;
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $value, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
        }
        return '00:00:00';
    }

    public static function minutes($value): int
    {
        [$h, $m] = array_map('intval', explode(':', self::time($value)));
        return $h * 60 + $m;
    }

    public static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
