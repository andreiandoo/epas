<?php

namespace App\Concerns;

use Carbon\Carbon;

/**
 * Trait IsLeisureVenue
 *
 * Add leisure-venue support (seasons, schedule, pricing rules, last entry) on
 * any Event-like model that exposes:
 *   - $display_template (string)
 *   - $venue_config (array, casted)
 *
 * Origin: extracted from MarketplaceEvent so the same logic also runs on the
 * core Event model used in Filament admin/marketplace.
 */
trait IsLeisureVenue
{
    public function isLeisureVenue(): bool
    {
        return $this->display_template === 'leisure_venue';
    }

    /**
     * Get the active season for a specific date.
     */
    public function getSeasonForDate(string $date): ?array
    {
        $config = $this->venue_config ?? [];
        $seasons = $config['seasons'] ?? [];

        if (empty($seasons)) {
            $schedule = $config['operating_schedule'] ?? [];
            if (!empty($schedule)) {
                return [
                    'name' => 'Default',
                    'start' => '01-01',
                    'end' => '12-31',
                    'schedule' => $schedule,
                    'last_entry' => null,
                ];
            }
            return null;
        }

        $md = Carbon::parse($date)->format('m-d');

        foreach ($seasons as $season) {
            $start = $season['start'] ?? '01-01';
            $end = $season['end'] ?? '12-31';

            if ($start <= $end) {
                if ($md >= $start && $md <= $end) {
                    return $season;
                }
            } else {
                // Wrap-around season (e.g. Nov–Mar)
                if ($md >= $start || $md <= $end) {
                    return $season;
                }
            }
        }

        return null;
    }

    public function isDateOpen(string $date): bool
    {
        if (!$this->isLeisureVenue()) {
            return false;
        }

        $config = $this->venue_config ?? [];
        $closedDates = $config['closed_dates'] ?? [];

        if (in_array($date, $closedDates)) {
            return false;
        }

        $season = $this->getSeasonForDate($date);
        if (!$season) {
            return false;
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));
        $schedule = $season['schedule'] ?? [];

        if (empty($schedule)) {
            return true;
        }

        return isset($schedule[$dayOfWeek]) && $schedule[$dayOfWeek] !== null;
    }

    public function getOperatingHours(string $date): ?array
    {
        $season = $this->getSeasonForDate($date);
        if (!$season) {
            return null;
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));
        $schedule = $season['schedule'] ?? [];
        $hours = $schedule[$dayOfWeek] ?? null;

        if ($hours && !empty($season['last_entry'])) {
            $hours['last_entry'] = $season['last_entry'];
        }

        return $hours;
    }

    public function isPastLastEntry(string $date): bool
    {
        $season = $this->getSeasonForDate($date);
        if (!$season) {
            return true;
        }

        $lastEntry = $season['last_entry'] ?? null;
        if (!$lastEntry) {
            return false;
        }

        if ($date !== now()->format('Y-m-d')) {
            return false;
        }

        return now()->format('H:i') > $lastEntry;
    }

    /**
     * Calculate effective price for a ticket type on a given date.
     * Accepts both TicketType (cu accessor `price` care returneaza sale price)
     * si MarketplaceTicketType (cu `price` ca decimal column).
     */
    public function getEffectivePrice($ticketType, string $date, ?float $dateOverride = null): float
    {
        if ($dateOverride !== null) {
            return $dateOverride;
        }

        // Resolva pretul de baza: TicketType are accessor `price` care da sale price (poate 0),
        // dar pretul real e in price_max (accessor pe price_cents) sau direct in price_cents.
        // MarketplaceTicketType are direct `price` ca decimal column.
        $basePrice = 0.0;
        if (!empty($ticketType->price_max)) {
            $basePrice = (float) $ticketType->price_max;
        } elseif (isset($ticketType->price_cents) && $ticketType->price_cents > 0) {
            $basePrice = $ticketType->price_cents / 100;
        } elseif (!empty($ticketType->price)) {
            $basePrice = (float) $ticketType->price;
        }

        $config = $this->venue_config ?? [];
        $pricingRules = $config['pricing_rules'] ?? [];

        if (empty($pricingRules)) {
            return $basePrice;
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('D'));

        foreach ($pricingRules as $rule) {
            $days = $rule['days'] ?? [];
            if (in_array($dayOfWeek, $days)) {
                $type = $rule['type'] ?? 'percent';
                $value = (float) ($rule['value'] ?? 0);

                if ($type === 'percent') {
                    return round($basePrice * (1 + $value / 100), 2);
                } elseif ($type === 'fixed') {
                    return round($basePrice + $value, 2);
                }
            }
        }

        return $basePrice;
    }
}
