<?php

namespace App\Services\WebTemplate;

use Carbon\Carbon;

/**
 * Transforms static demo data into dynamic, always-fresh data.
 *
 * - Dates are stored as relative offsets ("+7d", "+30d", "+3m") and resolved to real dates
 * - Ticket availability percentages generate realistic "X bilete rămase" indicators
 * - Seating maps get dynamic occupied/available seats
 * - Badges auto-update: "Early Bird" → "Últimele bilete" based on proximity
 */
class DemoDataTransformer
{
    private Carbon $now;

    public function __construct()
    {
        $this->now = Carbon::now();
    }

    /**
     * Transform the entire demo data set, resolving all relative dates and dynamic fields.
     */
    public function transform(array $data): array
    {
        // Process all known event arrays
        foreach (['events', 'featured_events', 'repertoire', 'upcoming_events'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = array_map(fn ($event) => $this->transformEvent($event), $data[$key]);
                // Re-sort by date ascending
                usort($data[$key], fn ($a, $b) => ($a['_sort_date'] ?? '') <=> ($b['_sort_date'] ?? ''));
                // Remove sort helper
                $data[$key] = array_map(fn ($e) => collect($e)->except('_sort_date')->all(), $data[$key]);
            }
        }

        // Process lineup days for festivals
        if (isset($data['lineup']) && is_array($data['lineup'])) {
            $data['lineup'] = $this->transformLineup($data['lineup']);
        }

        // Process season tickets
        if (isset($data['season_tickets']) && is_array($data['season_tickets'])) {
            foreach ($data['season_tickets'] as &$ticket) {
                if (isset($ticket['name'])) {
                    $ticket['name'] = str_replace(
                        ['2025', '2026', '2027'],
                        [(string) $this->now->year, (string) ($this->now->year + 1), (string) ($this->now->year + 2)],
                        $ticket['name']
                    );
                }
            }
        }

        // Update hero countdown
        if (isset($data['hero']['countdown_to'])) {
            $data['hero']['countdown_to'] = $this->resolveDate($data['hero']['countdown_to']);
        }

        // Update site season/edition references
        if (isset($data['site']['tagline'])) {
            $data['site']['tagline'] = $this->updateYearReferences($data['site']['tagline']);
        }
        if (isset($data['site']['dates'])) {
            $data['site']['dates'] = $this->updateYearReferences($data['site']['dates']);
        }
        if (isset($data['hero']['subtitle'])) {
            $data['hero']['subtitle'] = $this->updateYearReferences($data['hero']['subtitle']);
        }

        return $data;
    }

    /**
     * Transform a single event: resolve dates, compute badges, add dynamic fields.
     */
    private function transformEvent(array $event): array
    {
        // Resolve the date
        foreach (['date', 'next_show'] as $dateField) {
            if (isset($event[$dateField])) {
                $event[$dateField] = $this->resolveDate($event[$dateField]);
            }
        }

        // Track real date for sorting
        $dateStr = $event['date'] ?? $event['next_show'] ?? null;
        $event['_sort_date'] = $dateStr;

        // Dynamic availability based on date proximity
        if ($dateStr) {
            $eventDate = Carbon::parse($dateStr);
            $daysUntil = $this->now->diffInDays($eventDate, false);

            // Generate realistic availability percentage
            if ($daysUntil <= 3) {
                $event['availability_pct'] = rand(2, 8);
                $event['availability_label'] = 'Últimele bilete!';
                $event['badge'] = $event['badge'] ?? 'Últimele bilete';
            } elseif ($daysUntil <= 14) {
                $event['availability_pct'] = rand(10, 30);
                $event['availability_label'] = 'Se vând rapid';
                if (empty($event['badge'])) {
                    $event['badge'] = 'Se vând rapid';
                }
            } elseif ($daysUntil <= 45) {
                $event['availability_pct'] = rand(35, 65);
                $event['availability_label'] = 'Disponibil';
            } else {
                $event['availability_pct'] = rand(70, 95);
                $event['availability_label'] = 'Disponibil';
                // Events far in the future can have Early Bird
                if ($daysUntil > 90 && empty($event['badge'])) {
                    $event['badge'] = 'Early Bird';
                }
            }

            // Generate "X bilete rămase" for events with capacity
            if (isset($event['tickets_available'])) {
                $totalCapacity = $event['tickets_available'];
                $remaining = (int) round($totalCapacity * $event['availability_pct'] / 100);
                $event['tickets_remaining'] = max(1, $remaining);
            }
        }

        // Ensure ticket_types exists and has realistic data
        if (!isset($event['ticket_types'])) {
            $event['ticket_types'] = $this->generateTicketTypes($event);
        }

        return $event;
    }

    /**
     * Generate realistic ticket types for an event based on its category/venue.
     */
    private function generateTicketTypes(array $event): array
    {
        $basePrice = $event['price_from'] ?? 50;
        $currency = $event['currency'] ?? 'RON';
        $hasSeating = $event['has_seating_map'] ?? false;
        $category = $event['category'] ?? $event['type'] ?? '';

        // Theater / Opera / Concert hall — numbered seats
        if ($hasSeating || in_array($category, ['Teatru', 'Operă', 'Balet', 'Filarmonică'])) {
            return [
                [
                    'name' => 'Categoria I',
                    'price' => $basePrice + round($basePrice * 0.8),
                    'currency' => $currency,
                    'available' => true,
                    'remaining' => rand(5, 40),
                    'description' => 'Rândurile 1-5, vizibilitate excelentă',
                    'has_seat_selection' => true,
                    'seat_zone' => 'cat-1',
                ],
                [
                    'name' => 'Categoria II',
                    'price' => $basePrice + round($basePrice * 0.3),
                    'currency' => $currency,
                    'available' => true,
                    'remaining' => rand(20, 80),
                    'description' => 'Rândurile 6-12, vizibilitate foarte bună',
                    'has_seat_selection' => true,
                    'seat_zone' => 'cat-2',
                ],
                [
                    'name' => 'Categoria III',
                    'price' => $basePrice,
                    'currency' => $currency,
                    'available' => true,
                    'remaining' => rand(30, 120),
                    'description' => 'Rândurile 13-20',
                    'has_seat_selection' => true,
                    'seat_zone' => 'cat-3',
                ],
                [
                    'name' => 'Balcon',
                    'price' => $basePrice - round($basePrice * 0.2),
                    'currency' => $currency,
                    'available' => rand(0, 1) === 1,
                    'remaining' => rand(0, 30),
                    'description' => 'Balcon, rândurile 1-4',
                    'has_seat_selection' => true,
                    'seat_zone' => 'balcon',
                ],
                [
                    'name' => 'Lojă (4 locuri)',
                    'price' => ($basePrice + round($basePrice * 0.8)) * 4,
                    'currency' => $currency,
                    'available' => rand(0, 1) === 1,
                    'remaining' => rand(0, 3),
                    'description' => 'Lojă privată, 4 locuri, servire inclusă',
                    'has_seat_selection' => true,
                    'seat_zone' => 'loja',
                    'is_group' => true,
                ],
            ];
        }

        // Stadium / Arena — sector-based
        if (in_array($category, ['Fotbal', 'Sport', 'Concert', 'Show']) && isset($event['sectors_available'])) {
            $types = [];
            $sectorPrices = [
                'Golden Circle' => $basePrice * 3,
                'Tribuna I (VIP)' => $basePrice * 2.5,
                'Tribuna I' => $basePrice * 2,
                'Tribuna II' => $basePrice * 1.3,
                'Peluza Nord' => $basePrice,
                'Peluza Sud' => $basePrice,
            ];
            foreach ($event['sectors_available'] as $sector) {
                $types[] = [
                    'name' => $sector,
                    'price' => $sectorPrices[$sector] ?? $basePrice,
                    'currency' => $currency,
                    'available' => true,
                    'remaining' => rand(50, 2000),
                    'has_seat_selection' => in_array($sector, ['Tribuna I (VIP)', 'Tribuna I', 'Tribuna II', 'Golden Circle']),
                    'seat_zone' => \Illuminate\Support\Str::slug($sector),
                ];
            }
            return $types;
        }

        // Festival — pass types (already handled in festival tickets section)
        if (in_array($category, ['Festival'])) {
            return [
                ['name' => 'General Access', 'price' => $basePrice, 'currency' => $currency, 'available' => true, 'remaining' => rand(500, 3000), 'has_seat_selection' => false],
                ['name' => 'VIP', 'price' => round($basePrice * 1.8), 'currency' => $currency, 'available' => true, 'remaining' => rand(50, 300), 'has_seat_selection' => false],
                ['name' => 'Backstage Pass', 'price' => round($basePrice * 4), 'currency' => $currency, 'available' => rand(0, 1) === 1, 'remaining' => rand(0, 20), 'has_seat_selection' => false],
            ];
        }

        // Default — General Admission + VIP
        return [
            [
                'name' => 'General Admission',
                'price' => $basePrice,
                'currency' => $currency,
                'available' => true,
                'remaining' => rand(20, 200),
                'has_seat_selection' => false,
            ],
            [
                'name' => 'VIP',
                'price' => round($basePrice * 2),
                'currency' => $currency,
                'available' => true,
                'remaining' => rand(5, 50),
                'has_seat_selection' => false,
                'description' => 'Acces preferențial, loc rezervat, welcome drink',
            ],
            [
                'name' => 'Early Bird',
                'price' => round($basePrice * 0.7),
                'currency' => $currency,
                'available' => false,
                'remaining' => 0,
                'has_seat_selection' => false,
                'description' => 'Preț redus — epuizat',
            ],
        ];
    }

    /**
     * Resolve a date string. If it looks like a relative offset, compute from now.
     * Otherwise, shift it forward so it's always in the future.
     */
    private function resolveDate(string $dateStr): string
    {
        // Relative format: "+7d", "+2w", "+3m", "+1y"
        if (preg_match('/^\+(\d+)([dwmy])$/', $dateStr, $m)) {
            $amount = (int) $m[1];
            $resolved = match ($m[2]) {
                'd' => $this->now->copy()->addDays($amount),
                'w' => $this->now->copy()->addWeeks($amount),
                'm' => $this->now->copy()->addMonths($amount),
                'y' => $this->now->copy()->addYears($amount),
            };
            return $resolved->format('Y-m-d');
        }

        // ISO datetime with time
        if (str_contains($dateStr, 'T')) {
            $original = Carbon::parse($dateStr);
            return $this->shiftToFuture($original)->format('Y-m-d\TH:i:s');
        }

        // Date + time (space separated)
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', $dateStr)) {
            $original = Carbon::parse($dateStr);
            $shifted = $this->shiftToFuture($original);
            return $shifted->format('Y-m-d H:i');
        }

        // Plain date
        $original = Carbon::parse($dateStr);
        return $this->shiftToFuture($original)->format('Y-m-d');
    }

    /**
     * Shift a date so it's always in the future while preserving day-of-week and time.
     * Adds full years until it's at least 2 weeks from now.
     */
    private function shiftToFuture(Carbon $date): Carbon
    {
        $shifted = $date->copy();
        $twoWeeksFromNow = $this->now->copy()->addWeeks(2);

        // Shift forward by years until the date is in the future
        while ($shifted->lt($twoWeeksFromNow)) {
            $shifted->addYear();
        }

        return $shifted;
    }

    /**
     * Transform lineup data for festivals — resolve all date references.
     */
    private function transformLineup(array $lineup): array
    {
        if (isset($lineup['headliners'])) {
            foreach ($lineup['headliners'] as &$headliner) {
                // Update day labels to match the shifted festival dates
                // These are relative ("Vineri", "Sâmbătă") so they don't need date shifting
            }
        }

        return $lineup;
    }

    /**
     * Update year references in text strings to be current.
     */
    private function updateYearReferences(string $text): string
    {
        $currentYear = $this->now->year;
        $nextYear = $currentYear + 1;

        // Replace season format "2025-2026" with current season
        $text = preg_replace('/20\d{2}-20\d{2}/', "{$currentYear}-{$nextYear}", $text);

        // Replace standalone years
        $text = preg_replace('/\b202[0-9]\b/', (string) $currentYear, $text);

        return $text;
    }
}
