<?php

namespace App\Services\Sanius;

use Carbon\Carbon;

/**
 * Codul de bare de pe tichetul de parcare Mohos (Code 39, 15 cifre):
 *
 *   2610281104 9629 1
 *   AALLZZHHMM  |    └ mereu „1” pe toate tichetele vazute (terminal / poarta)
 *               └ ultimele 4 cifre din „numar” (19629 -> 9629)
 *
 * Numarul tichetului se reseteaza, deci cheia unica e sirul intreg.
 * Ora din cod e ora locala (Europe/Bucharest).
 */
final class ParkingTicketCode
{
    public const TZ = 'Europe/Bucharest';

    /**
     * @return array{code: string, entry_at: Carbon, number_suffix: string, terminal: string}|null
     */
    public static function parse(?string $raw): ?array
    {
        $digits = preg_replace('/[\s*]/', '', (string) $raw);
        if (! is_string($digits) || ! preg_match('/^\d{15}$/', $digits)) {
            return null;
        }

        $yy = (int) substr($digits, 0, 2);
        $mm = (int) substr($digits, 2, 2);
        $dd = (int) substr($digits, 4, 2);
        $hh = (int) substr($digits, 6, 2);
        $mi = (int) substr($digits, 8, 2);

        if (! checkdate($mm, $dd, 2000 + $yy) || $hh > 23 || $mi > 59) {
            return null;
        }

        $entry = Carbon::create(2000 + $yy, $mm, $dd, $hh, $mi, 0, self::TZ)->utc();

        return [
            'code' => $digits,
            'entry_at' => $entry,
            'number_suffix' => substr($digits, 10, 4),
            'terminal' => substr($digits, 14, 1),
        ];
    }

    /** Tichetul e din ziua curenta (ora Romaniei) si nu e din viitor (toleranta 15 min). */
    public static function isFromToday(Carbon $entryAt, ?Carbon $now = null): bool
    {
        $now = ($now ?? Carbon::now())->copy();
        if ($entryAt->gt($now->copy()->addMinutes(15))) {
            return false;
        }

        return $entryAt->copy()->setTimezone(self::TZ)->toDateString()
            === $now->copy()->setTimezone(self::TZ)->toDateString();
    }
}
