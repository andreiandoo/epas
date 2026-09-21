<?php

namespace App\Services\Activities;

use App\Models\MarketplaceOrganizer;

/**
 * The bilete.online commission on an activities-module sale: the operator's percentage, but never less than a fixed
 * amount per unit sold.
 *
 * The minimum is the operator's `fixed_commission_default` (1,50 lei when the column was never filled in, which is what
 * bilete.online charges); an admin turns it off for an operator with `commission_use_floor`. Events keep their own rule
 * inside the checkout controller — this class is used only by the activities module, so Ambilet is untouched.
 */
class ActivityCommission
{
    /** What an operator pays per ticket at least, when nothing else is set on the account. */
    public const DEFAULT_FLOOR = 1.5;

    /** The minimum per unit for this operator, 0 when the admin turned it off. */
    public static function floor(?MarketplaceOrganizer $organizer): float
    {
        if (!$organizer) {
            return 0.0;
        }
        $use = $organizer->commission_use_floor;
        if ($use !== null && !$use) {
            return 0.0;
        }
        $fixed = $organizer->fixed_commission_default;

        return $fixed === null ? self::DEFAULT_FLOOR : max(0.0, (float) $fixed);
    }

    /** The commission of one sold line: the percentage of its total, raised to the floor times the units sold. */
    public static function forLine(float $lineTotal, float $rate, float $floor, int $quantity): float
    {
        $percent = round($lineTotal * $rate / 100, 2);
        if ($floor <= 0) {
            return $percent;
        }

        return max($percent, round($floor * max(1, $quantity), 2));
    }
}
