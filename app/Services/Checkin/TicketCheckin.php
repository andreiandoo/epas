<?php

namespace App\Services\Checkin;

use App\Models\Event;
use App\Models\Performance;
use App\Models\Ticket;
use App\Models\TicketDayCheckin;
use App\Support\MarketplaceTz;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regulile de intrare la poarta, comune pentru toate endpoint-urile de check-in.
 *
 * Orele evenimentului sunt ora locala (RO), salvate fara fus. Serverul merge
 * pe UTC, asa ca fiecare fereastra se construieste in fusul marketplace-ului
 * si se compara cu momentul real. Asa 21:00 inseamna 21:00 la Bucuresti.
 *
 * Fereastra unei zile: de la start - 6h (sau 00:00 fara ora de start) pana la
 * ora de final (a doua zi daca finalul e dupa miezul noptii; 23:59 fara final).
 *
 * Tipuri de bilet:
 *   single - eveniment de o zi: o singura intrare
 *   day    - bilet pe o zi dintr-un eveniment pe mai multe zile: doar in ziua lui
 *   pass   - abonament: cate o intrare in fiecare zi a evenimentului
 *   open   - nu stim datele evenimentului: nu blocam (comportamentul vechi)
 *
 * tickets.checked_in_at ramane prima intrare (rapoartele nu se schimba).
 * Intrarile pe zile stau in ticket_day_checkins. Cand cineva sterge
 * checked_in_at pe alta cale (admin, tinker), intrarile vechi se ignora.
 */
class TicketCheckin
{
    public const OPEN_BEFORE_HOURS = 6;

    private const DAYS = ['dum.', 'lun.', 'mar.', 'mie.', 'joi', 'vin.', 'sâm.'];
    private const MONTHS = [1 => 'ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    private static ?bool $hasTable = null;
    private static array $windowCache = [];

    /**
     * Incearca intrarea. Intoarce:
     *   status  ok | duplicate | rejected
     *   code    ok | already | already_today | wrong_day | too_early | ended
     *   message text pentru operator
     *   validity blocul afisat in aplicatie
     *   entry   intrarea gasita (la duplicate)
     */
    public static function attempt(Ticket $ticket, ?Event $event, ?string $by, ?string $via, ?CarbonInterface $at = null, bool $retried = false): array
    {
        $now = $at ? Carbon::instance($at) : now();
        $tz = self::tz($event);
        $info = self::windows($ticket, $event);
        $windows = $info['windows'];
        $kind = $info['kind'];

        $current = self::windowAt($windows, $now);

        if (!empty($windows) && !$current) {
            [$code, $message] = self::rejection($kind, $windows, $now, $tz);
            return [
                'status' => 'rejected',
                'code' => $code,
                'message' => $message,
                'validity' => self::validity($ticket, $event, $now, $info),
            ];
        }

        $dayKey = $current ? $current['date'] : $now->copy()->setTimezone($tz)->toDateString();
        $entries = self::entries($ticket, $windows, $tz);

        $duplicate = null;
        if ($kind === 'pass') {
            $duplicate = collect($entries)->firstWhere('date', $dayKey);
        } elseif ($ticket->checked_in_at) {
            $duplicate = $entries[0] ?? ['date' => $dayKey, 'at' => $ticket->checked_in_at, 'by' => $ticket->checked_in_by];
        }

        if ($duplicate) {
            return [
                'status' => 'duplicate',
                'code' => $kind === 'pass' ? 'already_today' : 'already',
                'message' => 'Ticket already checked in at ' . Carbon::instance($duplicate['at'])->setTimezone($tz)->toIso8601String(),
                'entry' => $duplicate,
                'validity' => self::validity($ticket, $event, $now, $info),
            ];
        }

        $inserted = DB::transaction(function () use ($ticket, $event, $by, $via, $now, $dayKey, $kind) {
            // doua porti in acelasi timp: a doua asteapta aici si vede intrarea primei
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first(['id', 'checked_in_at']);
            $first = !$locked?->checked_in_at;
            if (!$first && $kind !== 'pass') {
                return false;
            }

            if (self::hasTable()) {
                if ($first) {
                    // intrari ramase dupa o resetare facuta pe alta cale
                    TicketDayCheckin::where('ticket_id', $ticket->id)->delete();
                }
                try {
                    DB::transaction(fn () => TicketDayCheckin::create([
                        'ticket_id' => $ticket->id,
                        'event_id' => $event?->id,
                        'day' => $dayKey,
                        'checked_in_at' => $now,
                        'checked_in_by' => $by,
                        'checked_in_via' => $via,
                    ]));
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    return false; // alta poarta a fost mai rapida
                }
            }

            if ($first) {
                $ticket->update([
                    'checked_in_at' => $now,
                    'checked_in_by' => $by,
                    'checked_in_via' => $via,
                ]);
            }

            return true;
        });

        if (!$inserted) {
            $ticket->refresh();
            if (!$retried) {
                return self::attempt($ticket, $event, $by, $via, $at, true);
            }
            return [
                'status' => 'duplicate',
                'code' => 'already',
                'message' => 'Ticket already checked in at ' . ($ticket->checked_in_at ?? $now)->copy()->setTimezone($tz)->toIso8601String(),
                'entry' => null,
                'validity' => self::validity($ticket, $event, $now, $info),
            ];
        }

        return [
            'status' => 'ok',
            'code' => 'ok',
            'message' => 'Ticket checked in successfully',
            'validity' => self::validity($ticket->fresh() ?? $ticket, $event, $now, $info),
        ];
    }

    /**
     * Anuleaza ultima intrare. Daca nu mai ramane niciuna, biletul e nescanat.
     */
    public static function undo(Ticket $ticket): bool
    {
        if (!$ticket->checked_in_at) {
            return false;
        }

        DB::transaction(function () use ($ticket) {
            $remaining = collect();
            if (self::hasTable()) {
                $last = TicketDayCheckin::where('ticket_id', $ticket->id)->orderByDesc('checked_in_at')->first();
                $last?->delete();
                $remaining = TicketDayCheckin::where('ticket_id', $ticket->id)->orderBy('checked_in_at')->get();
            }

            if ($remaining->isEmpty()) {
                $ticket->update(['checked_in_at' => null, 'checked_in_by' => null, 'checked_in_via' => null]);
            } else {
                $first = $remaining->first();
                $ticket->update([
                    'checked_in_at' => $first->checked_in_at,
                    'checked_in_by' => $first->checked_in_by,
                    'checked_in_via' => $first->checked_in_via,
                ]);
            }
        });

        return true;
    }

    /**
     * Blocul de validitate pentru aplicatie, fara sa scaneze.
     */
    public static function validity(Ticket $ticket, ?Event $event, ?CarbonInterface $at = null, ?array $info = null): array
    {
        $now = $at ? Carbon::instance($at) : now();
        $tz = self::tz($event);
        $info ??= self::windows($ticket, $event);
        $windows = $info['windows'];
        $kind = $info['kind'];
        $current = self::windowAt($windows, $now);
        $todayKey = $current ? $current['date'] : $now->copy()->setTimezone($tz)->toDateString();

        $entries = array_map(fn ($e) => [
            'date' => $e['date'],
            'day_label' => self::dayLabelFor($e['date'], $windows),
            'at' => Carbon::instance($e['at'])->setTimezone($tz)->toIso8601String(),
            'at_label' => self::dateLabel(Carbon::instance($e['at'])->setTimezone($tz)) . ', ' . Carbon::instance($e['at'])->setTimezone($tz)->format('H:i'),
            'by' => $e['by'],
        ], self::entries($ticket, $windows, $tz));

        $label = null;
        if ($kind === 'single' && $windows) {
            $label = 'Valabil ' . $windows[0]['label'];
        } elseif ($kind === 'day' && $windows) {
            $label = 'Valabil doar ' . $windows[0]['label'];
        } elseif ($kind === 'pass' && $windows) {
            $label = 'Valabil pe toată durata · ' . self::rangeLabel($windows);
        }

        return [
            'kind' => $kind,
            'label' => $label,
            'days' => array_map(fn ($w) => [
                'date' => $w['date'],
                'label' => $w['label'],
                'opens_at' => $w['opens']->toIso8601String(),
                'closes_at' => $w['closes']->toIso8601String(),
            ], $windows),
            'today' => $current ? ['date' => $current['date'], 'label' => $current['label']] : null,
            'entries' => $entries,
            'scanned_today' => collect($entries)->contains('date', $todayKey),
        ];
    }

    /**
     * Varianta scurta pentru lista de participanti (scanarea offline):
     * tipul, zilele cu fereastra lor si zilele in care a intrat deja.
     */
    public static function offlineInfo(Ticket $ticket, ?Event $event): array
    {
        $info = self::windows($ticket, $event);
        $tz = self::tz($event);
        $entered = [];
        if ($ticket->checked_in_at) {
            $entered = $info['kind'] === 'pass'
                ? array_values(array_unique(array_column(self::entries($ticket, $info['windows'], $tz), 'date')))
                : [self::dayKeyFor($ticket->checked_in_at, $info['windows'], $tz)];
        }

        return [
            'kind' => $info['kind'],
            'days' => array_map(fn ($w) => [
                'date' => $w['date'],
                'label' => $w['label'],
                'opens_at' => $w['opens']->toIso8601String(),
                'closes_at' => $w['closes']->toIso8601String(),
            ], $info['windows']),
            'entered_days' => $entered,
        ];
    }

    /**
     * Zilele valide pentru bilet: ['kind' => ..., 'windows' => [...]].
     * Fiecare fereastra: date, label, opens, closes (Carbon, fusul marketplace).
     */
    public static function windows(Ticket $ticket, ?Event $event): array
    {
        if (!$event) {
            return ['kind' => 'open', 'windows' => []];
        }

        $ticketType = $ticket->relationLoaded('ticketType') ? $ticket->ticketType : $ticket->ticketType()->first();
        $validDate = $ticketType?->valid_date?->toDateString();
        $cacheKey = $event->id . ':' . ($ticket->performance_id ?: 0) . ':' . ($validDate ?: '-');
        if (isset(self::$windowCache[$cacheKey])) {
            return self::$windowCache[$cacheKey];
        }

        $tz = self::tz($event);
        $days = self::eventDays($event, $tz);

        // Bilet pe o reprezentatie anume
        if ($ticket->performance_id) {
            $perf = Performance::find($ticket->performance_id);
            if ($perf?->starts_at) {
                $date = $perf->starts_at->format('Y-m-d');
                $start = $perf->starts_at->format('H:i');
                $end = $perf->ends_at?->format('H:i');
                $index = self::indexOf($days, $date);
                $window = self::window($date, $start, $end, $tz, $index);
                return self::$windowCache[$cacheKey] = [
                    'kind' => count($days) > 1 ? 'day' : 'single',
                    'windows' => [$window],
                ];
            }
        }

        if (empty($days)) {
            return self::$windowCache[$cacheKey] = ['kind' => 'open', 'windows' => []];
        }

        // Bilet de o zi la un eveniment pe mai multe zile
        if ($validDate && count($days) > 1) {
            $match = array_values(array_filter($days, fn ($d) => $d['date'] === $validDate));
            if ($match) {
                return self::$windowCache[$cacheKey] = ['kind' => 'day', 'windows' => $match];
            }
        }

        return self::$windowCache[$cacheKey] = [
            'kind' => count($days) > 1 ? 'pass' : 'single',
            'windows' => $days,
        ];
    }

    /**
     * Zilele evenimentului, fiecare cu fereastra ei.
     */
    private static function eventDays(Event $event, string $tz): array
    {
        $mode = $event->duration_mode ?: 'single_day';
        $raw = []; // [date, start, end]

        if ($mode === 'range' && $event->range_start_date) {
            $start = $event->range_start_date->copy();
            $end = ($event->range_end_date ?? $event->range_start_date)->copy();
            if ($end->lt($start) || $start->diffInDays($end) > 366) {
                $end = $start->copy();
            }
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $raw[] = [$d->toDateString(), $event->range_start_time, $event->range_end_time];
            }
        } elseif ($mode === 'multi_day' && !empty($event->multi_slots)) {
            foreach (collect($event->multi_slots)->filter(fn ($s) => !empty($s['date']))->sortBy('date') as $slot) {
                $raw[] = [Carbon::parse($slot['date'])->toDateString(), $slot['start_time'] ?? null, $slot['end_time'] ?? null];
            }
        } elseif ($event->event_date) {
            $raw[] = [$event->event_date->toDateString(), $event->start_time, $event->end_time];
        }

        if (empty($raw)) {
            return [];
        }

        // Amanat: o zi -> data si orele noi; mai multe zile -> se muta toate
        if ($event->is_postponed && $event->postponed_date) {
            $newDate = $event->postponed_date->toDateString();
            if (count($raw) === 1) {
                $raw = [[
                    $newDate,
                    $event->postponed_start_time ?: $raw[0][1],
                    $event->postponed_end_time ?: $raw[0][2],
                ]];
            } else {
                $shift = Carbon::parse($raw[0][0])->diffInDays(Carbon::parse($newDate), false);
                $raw = array_map(fn ($r) => [Carbon::parse($r[0])->addDays((int) $shift)->toDateString(), $r[1], $r[2]], $raw);
            }
        }

        $multi = count($raw) > 1;
        $out = [];
        foreach ($raw as $i => [$date, $start, $end]) {
            $out[] = self::window($date, $start, $end, $tz, $multi ? $i + 1 : null);
        }
        return $out;
    }

    private static function window(string $date, ?string $start, ?string $end, string $tz, ?int $index): array
    {
        $start = self::cleanTime($start);
        $end = self::cleanTime($end);

        $startAt = $start ? Carbon::parse("$date $start", $tz) : null;
        $opens = $startAt ? $startAt->copy()->subHours(self::OPEN_BEFORE_HOURS) : Carbon::parse("$date 00:00", $tz);

        if ($end) {
            $closes = Carbon::parse("$date $end", $tz);
            if ($startAt && $closes->lte($startAt)) {
                $closes->addDay(); // se termina dupa miezul noptii
            }
        } else {
            $closes = Carbon::parse("$date 23:59:59", $tz);
        }

        $day = Carbon::parse($date, $tz);
        $label = ($index ? 'Ziua ' . $index . ' · ' : '') . self::dateLabel($day);

        return [
            'date' => $date,
            'label' => $label,
            'start' => $startAt,
            'opens' => $opens,
            'closes' => $closes,
        ];
    }

    private static function rejection(string $kind, array $windows, Carbon $now, string $tz): array
    {
        $localToday = $now->copy()->setTimezone($tz)->toDateString();
        $first = $windows[0];
        $last = end($windows);

        if ($kind === 'day') {
            $w = $windows[0];
            if ($w['date'] === $localToday && $now->lt($w['opens'])) {
                return ['too_early', 'Bilet valabil doar ' . $w['label'] . '. Se poate scana de la ' . $w['opens']->format('H:i')];
            }
            return ['wrong_day', 'Bilet valabil doar ' . $w['label']];
        }

        if ($now->lt($first['opens'])) {
            $when = $first['date'] === $localToday ? 'azi' : $first['label'];
            return ['too_early', 'Prea devreme. Se poate scana ' . $when . ' de la ' . $first['opens']->format('H:i')];
        }

        if ($now->gt($last['closes'])) {
            return ['ended', 'Evenimentul s-a încheiat (' . $last['label'] . ', ' . $last['closes']->format('H:i') . ')'];
        }

        // intre doua zile ale unui abonament
        foreach ($windows as $w) {
            if ($now->lt($w['opens'])) {
                if ($w['date'] === $localToday) {
                    return ['too_early', 'Prea devreme. Se poate scana azi de la ' . $w['opens']->format('H:i')];
                }
                return ['too_early', 'Azi nu e zi de eveniment. Următoarea: ' . $w['label'] . ', de la ' . $w['opens']->format('H:i')];
            }
        }

        return ['ended', 'Evenimentul s-a încheiat'];
    }

    /**
     * Intrarile reale ale biletului, cea mai veche prima.
     */
    private static function entries(Ticket $ticket, array $windows, string $tz): array
    {
        if (!$ticket->checked_in_at) {
            return [];
        }

        $entries = [];
        if (self::hasTable()) {
            foreach (TicketDayCheckin::where('ticket_id', $ticket->id)->orderBy('checked_in_at')->get() as $row) {
                $entries[] = ['date' => $row->day->toDateString(), 'at' => $row->checked_in_at, 'by' => $row->checked_in_by];
            }
        }

        // intrare facuta inainte de tabel sau din POS (doar checked_in_at)
        $firstDay = self::dayKeyFor($ticket->checked_in_at, $windows, $tz);
        if (!collect($entries)->contains('date', $firstDay)) {
            array_unshift($entries, ['date' => $firstDay, 'at' => $ticket->checked_in_at, 'by' => $ticket->checked_in_by]);
        }

        return $entries;
    }

    private static function dayKeyFor(CarbonInterface $at, array $windows, string $tz): string
    {
        $w = self::windowAt($windows, Carbon::instance($at));
        return $w ? $w['date'] : Carbon::instance($at)->setTimezone($tz)->toDateString();
    }

    private static function windowAt(array $windows, Carbon $at): ?array
    {
        foreach ($windows as $w) {
            if ($at->gte($w['opens']) && $at->lte($w['closes'])) {
                return $w;
            }
        }
        return null;
    }

    private static function indexOf(array $days, string $date): ?int
    {
        if (count($days) < 2) {
            return null;
        }
        foreach ($days as $i => $d) {
            if ($d['date'] === $date) {
                return $i + 1;
            }
        }
        return null;
    }

    private static function dayLabelFor(string $date, array $windows): string
    {
        foreach ($windows as $w) {
            if ($w['date'] === $date) {
                return $w['label'];
            }
        }
        return self::dateLabel(Carbon::parse($date));
    }

    public static function dateLabel(CarbonInterface $d): string
    {
        return self::DAYS[$d->dayOfWeek] . ' ' . $d->format('j') . ' ' . self::MONTHS[(int) $d->format('n')];
    }

    private static function rangeLabel(array $windows): string
    {
        $a = Carbon::parse($windows[0]['date']);
        $b = Carbon::parse(end($windows)['date']);
        if ($a->format('n Y') === $b->format('n Y')) {
            return $a->format('j') . '–' . $b->format('j') . ' ' . self::MONTHS[(int) $b->format('n')];
        }
        return $a->format('j') . ' ' . self::MONTHS[(int) $a->format('n')] . ' – ' . $b->format('j') . ' ' . self::MONTHS[(int) $b->format('n')];
    }

    private static function cleanTime($time): ?string
    {
        if (!$time) {
            return null;
        }
        $time = trim((string) $time);
        return preg_match('/^\d{1,2}:\d{2}/', $time) ? substr($time, 0, 5) : null;
    }

    private static function tz(?Event $event): string
    {
        if (!$event) {
            return MarketplaceTz::DEFAULT_TIMEZONE;
        }
        $client = $event->relationLoaded('marketplaceClient') ? $event->marketplaceClient : $event->marketplaceClient()->first();
        return $client ? MarketplaceTz::tz($client) : MarketplaceTz::DEFAULT_TIMEZONE;
    }

    private static function hasTable(): bool
    {
        return self::$hasTable ??= Schema::hasTable('ticket_day_checkins');
    }

    /**
     * Ora scanarii trimisa de aplicatie la sincronizarea offline. Acceptata
     * doar daca e plauzibila (ultimele 48h, nu in viitor).
     */
    public static function scanTime($raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            $at = is_numeric($raw)
                ? Carbon::createFromTimestampMs((int) $raw)
                : Carbon::parse((string) $raw);
        } catch (\Throwable $e) {
            return null;
        }
        if ($at->gt(now()->addMinutes(5)) || $at->lt(now()->subHours(48))) {
            return null;
        }
        return $at;
    }
}
