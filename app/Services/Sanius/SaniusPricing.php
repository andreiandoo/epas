<?php

namespace App\Services\Sanius;

/**
 * Calculele de bani ale circuitului. Fara acces la baza de date, ca sa poata fi
 * testate direct. Preturile vin din produsele setate de organizator.
 */
final class SaniusPricing
{
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
     * Inchirierea unei sanii: chirie + tractarile peste cele incluse + avaria.
     *
     * @return array{rent: float, tows_count: int, tows_included: int, tows_paid: int, tow_unit: float, tows: float, damage_level: ?string, damage: float, total: float}
     */
    public static function rental(
        float $rent,
        float $towUnit,
        int $towsCount,
        int $included,
        ?string $damageLevel = null,
        float $damagePrice = 0.0
    ): array {
        $towsCount = max(0, $towsCount);
        $included = max(0, $included);
        $paid = max(0, $towsCount - $included);
        $tows = round($paid * $towUnit, 2);
        $damage = $damageLevel ? round($damagePrice, 2) : 0.0;

        return [
            'rent' => round($rent, 2),
            'tows_count' => $towsCount,
            'tows_included' => min($included, $towsCount),
            'tows_paid' => $paid,
            'tow_unit' => round($towUnit, 2),
            'tows' => $tows,
            'damage_level' => $damageLevel,
            'damage' => $damage,
            'total' => round($rent + $tows + $damage, 2),
        ];
    }
}
