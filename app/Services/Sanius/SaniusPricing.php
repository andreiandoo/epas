<?php

namespace App\Services\Sanius;

use Carbon\Carbon;

/**
 * Calculele de bani ale circuitului. Fara acces la baza de date, ca sa poata fi
 * testate direct. Preturile vin din produsele setate de organizator.
 */
final class SaniusPricing
{
    public const TZ = 'Europe/Bucharest';

    /**
     * Parcarea, cu regulile Sf. Ana:
     *  - cu bilet de acces: primele N minute (implicit 240) gratuite, se plateste doar ce depaseste;
     *  - fara bilet: primele minute (implicit 10) gratuite; peste ele se plateste toata durata;
     *  - se taxeaza pe unitati incepute (implicit 30 min).
     *
     * @return array{minutes: int, billable_minutes: int, units: int, unit_price: float, amount: float}
     */
    public static function parking(int $minutes, bool $hasAccess, array $rules, float $unitPrice): array
    {
        $minutes = max(0, $minutes);
        $unit = max(1, (int) ($rules['unit_minutes'] ?? 30));

        if ($hasAccess) {
            $billable = max(0, $minutes - max(0, (int) ($rules['free_minutes_with_access'] ?? 240)));
        } else {
            $billable = $minutes <= max(0, (int) ($rules['free_minutes'] ?? 10)) ? 0 : $minutes;
        }

        $units = $billable > 0 ? (int) ceil($billable / $unit) : 0;

        return [
            'minutes' => $minutes,
            'billable_minutes' => $billable,
            'units' => $units,
            'unit_price' => round($unitPrice, 2),
            'amount' => round($units * $unitPrice, 2),
        ];
    }

    /**
     * Ciclurile incepute ale unei inchirieri. Primul ciclu se plateste mereu;
     * toleranta se scade din durata (o intarziere de cateva minute nu inseamna
     * inca un ciclu).
     */
    public static function cycles(int $minutes, int $cycleMinutes, int $graceMinutes): int
    {
        $cycleMinutes = max(1, $cycleMinutes);
        $billable = max(0, $minutes - max(0, $graceMinutes));

        return max(1, (int) ceil($billable / $cycleMinutes));
    }

    /** Pret de weekend: sambata, duminica si zilele alese de organizator (ora Romaniei). */
    public static function isWeekend(Carbon $at, array $extraDates = []): bool
    {
        $local = $at->copy()->setTimezone(self::TZ);

        return $local->isWeekend() || in_array($local->toDateString(), $extraDates, true);
    }

    public static function dayType(Carbon $at, array $extraDates = []): string
    {
        return self::isWeekend($at, $extraDates) ? 'weekend' : 'weekday';
    }

    /**
     * O inchiriere, la valoare intreaga (inainte de creditul platit la intrare):
     * cicluri incepute, tractarile peste cele incluse pe ciclu, avaria.
     *
     * @return array{day_type: string, minutes: int, cycles_used: int, cycle_unit: float, cycles: float, tows_count: int, tows_per_cycle: int, tows_included: int, tows_paid: int, tow_unit: float, tows: float, damage_level: ?string, damage: float, total: float}
     */
    public static function rental(
        string $dayType,
        int $minutes,
        array $rules,
        float $cycleUnit,
        float $towUnit,
        int $towsCount,
        ?string $damageLevel = null,
        float $damagePrice = 0.0
    ): array {
        $minutes = max(0, $minutes);
        $cycles = self::cycles($minutes, (int) ($rules['cycle_minutes'] ?? 60), (int) ($rules['grace_minutes'] ?? 0));
        $perCycle = max(0, (int) ($rules['tows_per_cycle'] ?? 0));
        $towsCount = max(0, $towsCount);
        $included = $cycles * $perCycle;
        $paid = max(0, $towsCount - $included);
        $cyclesAmount = round($cycles * $cycleUnit, 2);
        $tows = round($paid * $towUnit, 2);
        $damage = $damageLevel ? round($damagePrice, 2) : 0.0;

        return [
            'day_type' => $dayType,
            'minutes' => $minutes,
            'cycles_used' => $cycles,
            'cycle_unit' => round($cycleUnit, 2),
            'cycles' => $cyclesAmount,
            'tows_count' => $towsCount,
            'tows_per_cycle' => $perCycle,
            'tows_included' => min($included, $towsCount),
            'tows_paid' => $paid,
            'tow_unit' => round($towUnit, 2),
            'tows' => $tows,
            'damage_level' => $damageLevel,
            'damage' => $damage,
            'total' => round($cyclesAmount + $tows + $damage, 2),
        ];
    }

    /**
     * Creditul platit la intrare (sanie-cicluri) se consuma din ciclurile tuturor
     * saniilor contului, in ordinea inchirierilor. Ce ramane neacoperit se
     * plateste la iesire. Creditul nefolosit nu se returneaza.
     *
     * @param  array<int|string, int>  $cyclesByRental  id inchiriere => cicluri folosite (in ordine)
     * @return array{to_pay: array<int|string, int>, credit: int, credit_used: int, credit_left: int}
     */
    public static function applyCredit(array $cyclesByRental, int $credit): array
    {
        $credit = max(0, $credit);
        $left = $credit;
        $toPay = [];
        foreach ($cyclesByRental as $id => $cycles) {
            $cycles = max(0, (int) $cycles);
            $covered = min($left, $cycles);
            $left -= $covered;
            $toPay[$id] = $cycles - $covered;
        }

        return [
            'to_pay' => $toPay,
            'credit' => $credit,
            'credit_used' => $credit - $left,
            'credit_left' => $left,
        ];
    }
}
