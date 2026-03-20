<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Console\Command;

class AnalyzeEventNames extends Command
{
    protected $signature = 'events:analyze-names {--type=both : venues, artists, or both}';
    protected $description = 'Analyze event titles for venue/artist names';

    public function handle(): int
    {
        $type = $this->option('type');

        // Get all event titles as flat strings
        $events = Event::all(['id', 'title']);
        $this->info("Total events: {$events->count()}");

        $eventTitles = $events->map(function ($event) {
            $title = $event->title;
            if (is_array($title)) {
                // Combine all language versions for matching
                return [
                    'id' => $event->id,
                    'titles' => array_values(array_filter($title)),
                ];
            }
            return [
                'id' => $event->id,
                'titles' => [$title],
            ];
        });

        if (in_array($type, ['venues', 'both'])) {
            $this->analyzeVenues($eventTitles);
        }

        if (in_array($type, ['artists', 'both'])) {
            $this->analyzeArtists($eventTitles);
        }

        return 0;
    }

    private function analyzeVenues($eventTitles): void
    {
        $this->newLine();
        $this->info('=== VENUE NAMES IN EVENT TITLES ===');

        $venues = Venue::all(['id', 'name']);
        $this->info("Total venues in DB: {$venues->count()}");

        $results = [];

        foreach ($venues as $venue) {
            $venueName = $venue->name;
            $names = is_array($venueName) ? array_values(array_filter($venueName)) : [$venueName];

            foreach ($names as $name) {
                if (empty($name) || mb_strlen($name) < 3) continue;

                $matchCount = 0;
                $matchedEventIds = [];

                foreach ($eventTitles as $event) {
                    foreach ($event['titles'] as $title) {
                        if (empty($title)) continue;
                        if (mb_stripos($title, $name) !== false) {
                            $matchCount++;
                            $matchedEventIds[] = $event['id'];
                            break; // Count each event once per venue
                        }
                    }
                }

                if ($matchCount > 0) {
                    $results[] = [
                        'name' => $name,
                        'venue_id' => $venue->id,
                        'matches' => $matchCount,
                    ];
                }
            }
        }

        // Sort by matches descending
        usort($results, fn ($a, $b) => $b['matches'] <=> $a['matches']);

        if (empty($results)) {
            $this->warn('No venue names found in event titles.');
        } else {
            $this->table(
                ['Venue Name', 'Venue ID', 'Events Matched'],
                array_map(fn ($r) => [$r['name'], $r['venue_id'], $r['matches']], $results)
            );
            $this->info('Total venue names found: ' . count($results));
            $totalMatches = array_sum(array_column($results, 'matches'));
            $this->info("Total event-venue matches: {$totalMatches}");
        }

        // Also find @ patterns
        $this->newLine();
        $this->info('=== LOCATION PATTERNS AFTER @ ===');
        $atPatterns = [];
        foreach ($eventTitles as $event) {
            foreach ($event['titles'] as $title) {
                if (empty($title)) continue;
                if (preg_match('/@\s*(.+?)(?:\s*[-–|,]|$)/u', $title, $m)) {
                    $location = trim($m[1]);
                    if (!empty($location)) {
                        $atPatterns[$location] = ($atPatterns[$location] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($atPatterns);

        if (empty($atPatterns)) {
            $this->warn('No @ location patterns found.');
        } else {
            $rows = [];
            foreach ($atPatterns as $location => $count) {
                $rows[] = [$location, $count];
            }
            $this->table(['Location after @', 'Count'], $rows);
            $this->info('Unique locations after @: ' . count($atPatterns));
        }
    }

    private function analyzeArtists($eventTitles): void
    {
        $this->newLine();
        $this->info('=== ARTIST NAMES IN EVENT TITLES ===');

        $artists = Artist::all(['id', 'name']);
        $this->info("Total artists in DB: {$artists->count()}");

        $results = [];

        foreach ($artists as $artist) {
            $artistName = $artist->name;
            $names = is_array($artistName) ? array_values(array_filter($artistName)) : [$artistName];

            foreach ($names as $name) {
                if (empty($name) || mb_strlen($name) < 3) continue;

                $matchCount = 0;

                foreach ($eventTitles as $event) {
                    foreach ($event['titles'] as $title) {
                        if (empty($title)) continue;
                        if (mb_stripos($title, $name) !== false) {
                            $matchCount++;
                            break;
                        }
                    }
                }

                if ($matchCount > 0) {
                    $results[] = [
                        'name' => $name,
                        'artist_id' => $artist->id,
                        'matches' => $matchCount,
                    ];
                }
            }
        }

        usort($results, fn ($a, $b) => $b['matches'] <=> $a['matches']);

        if (empty($results)) {
            $this->warn('No artist names found in event titles.');
        } else {
            $this->table(
                ['Artist Name', 'Artist ID', 'Events Matched'],
                array_map(fn ($r) => [$r['name'], $r['artist_id'], $r['matches']], $results)
            );
            $this->info('Total artist names found: ' . count($results));
            $totalMatches = array_sum(array_column($results, 'matches'));
            $this->info("Total event-artist matches: {$totalMatches}");
        }
    }
}
