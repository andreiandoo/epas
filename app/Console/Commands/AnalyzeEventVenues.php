<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Console\Command;

class AnalyzeEventVenues extends Command
{
    protected $signature = 'events:analyze-venues {--export : Export results to CSV}';
    protected $description = 'Cross-reference event locations (title @, address, suggested_venue_name) with venues in DB';

    public function handle(): int
    {
        // Load all venues with their names (translatable) and addresses
        $venues = Venue::all(['id', 'name', 'address', 'city']);
        $this->info("Venues in DB: {$venues->count()}");

        // Build a lookup: normalized venue name => venue record
        $venueIndex = [];
        foreach ($venues as $venue) {
            $names = is_array($venue->name) ? array_values(array_filter($venue->name)) : [$venue->name];
            foreach ($names as $name) {
                if (empty($name) || mb_strlen(trim($name)) < 2) continue;
                $key = $this->normalize($name);
                $venueIndex[$key] = [
                    'id' => $venue->id,
                    'name' => $name,
                    'address' => $venue->address,
                    'city' => $venue->city,
                ];
            }
        }

        $this->info("Unique venue name variants: " . count($venueIndex));

        // Load events with relevant fields
        $events = Event::whereNotNull('address')
            ->orWhereNotNull('suggested_venue_name')
            ->get(['id', 'title', 'address', 'suggested_venue_name', 'venue_id']);

        $this->info("Events with address or suggested_venue_name: {$events->count()}");

        $matched = [];    // Location found in DB venues
        $unmatched = [];  // Location NOT found in DB venues

        foreach ($events as $event) {
            // Extract location name from multiple sources
            $locationName = $this->extractLocationName($event);
            if (empty($locationName)) continue;

            $normalizedLocation = $this->normalize($locationName);
            $city = $this->extractCity($event);

            // Try to match against venue index
            $foundVenue = $this->findMatchingVenue($normalizedLocation, $locationName, $venueIndex);

            if ($foundVenue) {
                $key = $foundVenue['id'];
                if (!isset($matched[$key])) {
                    $matched[$key] = [
                        'venue_name' => $foundVenue['name'],
                        'venue_id' => $foundVenue['id'],
                        'venue_city' => $foundVenue['city'],
                        'event_count' => 0,
                        'sample_event_id' => $event->id,
                        'has_venue_id' => 0,
                        'missing_venue_id' => 0,
                    ];
                }
                $matched[$key]['event_count']++;
                if ($event->venue_id) {
                    $matched[$key]['has_venue_id']++;
                } else {
                    $matched[$key]['missing_venue_id']++;
                }
            } else {
                $normKey = $normalizedLocation;
                if (!isset($unmatched[$normKey])) {
                    $unmatched[$normKey] = [
                        'location_name' => $locationName,
                        'city' => $city,
                        'event_count' => 0,
                        'sample_event_id' => $event->id,
                        'from_address' => !empty($event->address),
                        'from_suggested' => !empty($event->suggested_venue_name),
                    ];
                }
                $unmatched[$normKey]['event_count']++;
            }
        }

        // Also check events with @ in title
        $allEvents = Event::all(['id', 'title', 'venue_id']);
        foreach ($allEvents as $event) {
            $titles = is_array($event->title) ? array_values(array_filter($event->title)) : [$event->title];
            foreach ($titles as $title) {
                if (empty($title)) continue;
                if (preg_match('/@\s*(.+?)(?:\s*[-–|]|$)/u', $title, $m)) {
                    $locationFromTitle = trim($m[1]);
                    if (mb_strlen($locationFromTitle) < 3) continue;

                    $normalized = $this->normalize($locationFromTitle);
                    $foundVenue = $this->findMatchingVenue($normalized, $locationFromTitle, $venueIndex);

                    if ($foundVenue) {
                        $key = $foundVenue['id'];
                        if (!isset($matched[$key])) {
                            $matched[$key] = [
                                'venue_name' => $foundVenue['name'],
                                'venue_id' => $foundVenue['id'],
                                'venue_city' => $foundVenue['city'],
                                'event_count' => 0,
                                'sample_event_id' => $event->id,
                                'has_venue_id' => 0,
                                'missing_venue_id' => 0,
                            ];
                        }
                        // Don't double-count
                    } else {
                        $normKey = $normalized;
                        if (!isset($unmatched[$normKey])) {
                            $unmatched[$normKey] = [
                                'location_name' => $locationFromTitle,
                                'city' => '',
                                'event_count' => 0,
                                'sample_event_id' => $event->id,
                                'from_address' => false,
                                'from_suggested' => false,
                            ];
                        }
                    }
                    break; // Only first title language
                }
            }
        }

        // Sort matched by event count desc
        usort($matched, fn ($a, $b) => $b['event_count'] <=> $a['event_count']);

        // Sort unmatched by event count desc
        uasort($unmatched, fn ($a, $b) => $b['event_count'] <=> $a['event_count']);
        $unmatched = array_values($unmatched);

        // Display matched
        $this->newLine();
        $this->info("=== LOCATIONS MATCHED TO DB VENUES ({$this->count($matched)}) ===");
        if (!empty($matched)) {
            $this->table(
                ['Venue Name', 'Venue ID', 'City', 'Events', 'With venue_id', 'Without venue_id'],
                array_map(fn ($r) => [
                    $r['venue_name'], $r['venue_id'], $r['venue_city'],
                    $r['event_count'], $r['has_venue_id'], $r['missing_venue_id'],
                ], $matched)
            );
        }

        // Display unmatched
        $this->newLine();
        $this->info("=== LOCATIONS NOT IN DB VENUES ({$this->count($unmatched)}) ===");
        if (!empty($unmatched)) {
            $this->table(
                ['Location Name', 'City', 'Events', 'Sample Event ID', 'From Address', 'From Suggested'],
                array_map(fn ($r) => [
                    mb_substr($r['location_name'], 0, 60),
                    $r['city'],
                    $r['event_count'],
                    $r['sample_event_id'],
                    $r['from_address'] ? 'Yes' : '',
                    $r['from_suggested'] ? 'Yes' : '',
                ], $unmatched)
            );
        }

        // Export
        if ($this->option('export')) {
            $this->exportCsv('venues_matched.csv',
                ['Venue Name', 'Venue ID', 'City', 'Events', 'With venue_id', 'Without venue_id'],
                array_map(fn ($r) => [
                    $r['venue_name'], $r['venue_id'], $r['venue_city'],
                    $r['event_count'], $r['has_venue_id'], $r['missing_venue_id'],
                ], $matched)
            );

            $this->exportCsv('venues_unmatched.csv',
                ['Location Name', 'City', 'Events', 'Sample Event ID', 'From Address', 'From Suggested'],
                array_map(fn ($r) => [
                    $r['location_name'], $r['city'], $r['event_count'],
                    $r['sample_event_id'],
                    $r['from_address'] ? 'Yes' : '',
                    $r['from_suggested'] ? 'Yes' : '',
                ], $unmatched)
            );
        }

        return 0;
    }

    /**
     * Extract venue/location name from event's address and suggested_venue_name.
     * Address format is typically: "Venue Name, City" or just "Venue Name"
     */
    private function extractLocationName(Event $event): ?string
    {
        // Prefer suggested_venue_name
        if (!empty($event->suggested_venue_name)) {
            return trim($event->suggested_venue_name);
        }

        // Parse address: take the part before first comma (venue name)
        if (!empty($event->address)) {
            $address = trim($event->address);
            // If it contains a comma, the first part is usually the venue name
            if (str_contains($address, ',')) {
                $parts = explode(',', $address, 2);
                $venuePart = trim($parts[0]);
                if (mb_strlen($venuePart) >= 3) {
                    return $venuePart;
                }
            }
            // Return full address if no comma
            return $address;
        }

        return null;
    }

    /**
     * Extract city from event address (part after first comma)
     */
    private function extractCity(Event $event): string
    {
        if (!empty($event->address) && str_contains($event->address, ',')) {
            $parts = explode(',', $event->address);
            if (count($parts) >= 2) {
                return trim($parts[1]);
            }
        }
        return '';
    }

    /**
     * Try to find a matching venue from the index.
     * Uses exact normalized match, then substring containment.
     */
    private function findMatchingVenue(string $normalized, string $original, array $venueIndex): ?array
    {
        // 1. Exact normalized match
        if (isset($venueIndex[$normalized])) {
            return $venueIndex[$normalized];
        }

        // 2. Check if any venue name is contained in the location string (or vice versa)
        foreach ($venueIndex as $key => $venue) {
            if (mb_strlen($key) < 4 || mb_strlen($normalized) < 4) continue;

            // Venue name found inside the location string
            if (mb_strlen($key) >= 5 && str_contains($normalized, $key)) {
                return $venue;
            }

            // Location string found inside venue name
            if (mb_strlen($normalized) >= 5 && str_contains($key, $normalized)) {
                return $venue;
            }
        }

        return null;
    }

    private function normalize(string $str): string
    {
        $str = mb_strtolower(trim($str));
        // Remove common prefixes/suffixes that don't help matching
        $str = preg_replace('/\s+/', ' ', $str);
        // Transliterate common Romanian chars
        $str = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
            ['a', 'a', 'i', 's', 't', 's', 't', 'a', 'a', 'i', 's', 't'],
            $str
        );
        return $str;
    }

    private function count(array $arr): int
    {
        return \count($arr);
    }

    private function exportCsv(string $filename, array $headers, array $rows): void
    {
        $path = storage_path("app/{$filename}");
        $fp = fopen($path, 'w');
        // UTF-8 BOM for Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        $this->info("Exported to: {$path}");
    }
}
