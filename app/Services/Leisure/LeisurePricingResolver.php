<?php

namespace App\Services\Leisure;

use App\Models\TicketType;
use DateTimeInterface;

/**
 * Resolves the effective price (in cents) of a leisure TicketType for a given
 * date, optionally applying a duration variant multiplier.
 *
 * Pricing pipeline (in order):
 *   1. base = ticket_type.price_cents (resolved from price attribute)
 *   2. apply duration_variant.price_multiplier (if matching duration provided)
 *   3. apply leisure_pricing_rules whose `days` matches the weekday
 *   4. apply leisure_seasons whose [start_date, end_date] contains the date
 *
 * Rules can be `type=percent` (delta %) or `type=fixed` (delta in RON).
 * Multiple rules are additive on the running total — the order of definition
 * is preserved so the operator can reason about cumulative effects.
 */
class LeisurePricingResolver
{
    /**
     * @param  TicketType  $ticketType
     * @param  DateTimeInterface  $forDate
     * @param  int|null  $durationMinutes  pick this duration_variant if set
     * @return int  effective price in cents (>= 0)
     */
    public function resolvePrice(
        TicketType $ticketType,
        DateTimeInterface $forDate,
        ?int $durationMinutes = null,
    ): int {
        $price = $this->basePriceCents($ticketType);

        // 1. Duration variant multiplier
        if ($durationMinutes !== null) {
            $variant = $ticketType->getDurationVariantsCollection()
                ->firstWhere('duration_minutes', $durationMinutes);
            if ($variant !== null) {
                $multiplier = (float) ($variant->price_multiplier ?? 1.0);
                $price = (int) round($price * $multiplier);
            }
        }

        // 2. Per-weekday pricing rules
        $dayOfWeek = (int) $forDate->format('N'); // 1=Mon, 7=Sun
        foreach (($ticketType->leisure_pricing_rules ?? []) as $rule) {
            $days = $rule['days'] ?? [];
            if (! is_array($days) || ! in_array($dayOfWeek, array_map('intval', $days), true)) {
                continue;
            }
            $price = $this->applyDelta($price, $rule);
        }

        // 3. Active seasons (date in [start_date, end_date])
        $dateStr = $forDate->format('Y-m-d');
        foreach (($ticketType->leisure_seasons ?? []) as $season) {
            $start = $season['start_date'] ?? null;
            $end = $season['end_date'] ?? null;
            if (! $start || ! $end || $dateStr < $start || $dateStr > $end) {
                continue;
            }
            // Season can carry its own rule shape (type/value) or its own
            // pricing_rules nested array — we honor whichever is provided.
            if (isset($season['type']) && isset($season['value'])) {
                $price = $this->applyDelta($price, $season);
            }
            foreach (($season['pricing_rules'] ?? []) as $rule) {
                $days = $rule['days'] ?? [];
                if (! is_array($days) || ! in_array($dayOfWeek, array_map('intval', $days), true)) {
                    continue;
                }
                $price = $this->applyDelta($price, $rule);
            }
        }

        return max(0, $price);
    }

    /**
     * Apply a single delta rule { type: percent|fixed, value: number } to a price.
     * - percent: price * (1 + value/100), value can be negative
     * - fixed:   price + value*100 cents
     */
    protected function applyDelta(int $priceCents, array $rule): int
    {
        $type = $rule['type'] ?? null;
        $value = $rule['value'] ?? 0;

        return match ($type) {
            'percent' => (int) round($priceCents * (1 + (float) $value / 100)),
            'fixed'   => $priceCents + (int) round((float) $value * 100),
            default   => $priceCents,
        };
    }

    /**
     * Extract base price in cents. The TicketType model stores monetary value
     * in `price_cents` (raw column). `price` accessor returns sale_price_cents
     * not price_cents, so we go directly to the raw attribute here.
     */
    protected function basePriceCents(TicketType $ticketType): int
    {
        $cents = $ticketType->getAttributes()['price_cents']
            ?? $ticketType->getRawOriginal('price_cents')
            ?? null;
        if ($cents !== null && is_numeric($cents)) {
            return (int) $cents;
        }
        // Last-resort fallback: try price accessor (sale_price) as a degraded base.
        $price = $ticketType->price;
        return is_numeric($price) ? (int) round(((float) $price) * 100) : 0;
    }
}
