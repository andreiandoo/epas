<?php

namespace App\Console\Commands;

use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;

class ImportAmbiletEventsCommand extends Command
{
    protected $signature = 'import:ambilet-events
        {file : Path to events.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run : Simulate without writing to DB}
        {--fresh : Ignore existing map and re-process all rows}';

    protected $description = 'Import AmBilet events from CSV into Tixello MarketplaceEvent';

    private string $mapFile;
    private array $map = []; // wp_event_id => tixello_event_id

    public function handle(): int
    {
        $file      = $this->argument('file');
        $clientId  = (int) $this->option('marketplace');
        $dryRun    = $this->option('dry-run');
        $fresh     = $this->option('fresh');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $dir            = dirname($file);
        $this->mapFile  = $dir . '/events_map.json';

        if (!$fresh && file_exists($this->mapFile)) {
            $this->map = json_decode(file_get_contents($this->mapFile), true) ?? [];
            $this->info('Loaded existing events map: ' . count($this->map) . ' entries.');
        } elseif ($fresh && file_exists($this->mapFile)) {
            unlink($this->mapFile);
            $this->map = [];
            $this->info('Deleted existing events map (--fresh).');
        }

        // Build organizer lookup: email => id
        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->pluck('id', 'email')
            ->all();
        $this->info('Loaded ' . count($organizers) . ' organizers for marketplace ' . $clientId . '.');

        $handle  = fopen($file, 'r');
        $header  = fgetcsv($handle);
        $created = $skipped = $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data      = array_combine($header, $row);
            $wpEventId = $data['wp_event_id'];

            // Already imported
            if (isset($this->map[$wpEventId])) {
                $skipped++;
                continue;
            }

            $organizerEmail = strtolower(trim($data['organizer_email'] ?? ''));
            $organizerId    = $organizers[$organizerEmail] ?? null;

            if (!$organizerId) {
                $this->warn("No organizer for email '{$organizerEmail}' (event: {$data['name']}) — skipping.");
                $failed++;
                continue;
            }

            // Parse "Venue Name, City" location string
            $location  = $this->n($data['location']);
            $venueName = null;
            $venueCity = null;
            if ($location) {
                $parts = array_map('trim', explode(',', $location));
                if (count($parts) >= 2) {
                    $venueCity = array_pop($parts);
                    $venueName = implode(', ', $parts);
                } else {
                    $venueCity = $location;
                }
            }

            $createdAt = $this->parseDate($data['created_at']);

            $eventData = [
                'marketplace_client_id'    => $clientId,
                'marketplace_organizer_id' => $organizerId,
                'name'                     => $data['name'],
                'description'              => $this->n($data['description']),
                'ticket_terms'             => $this->n($data['ticket_terms']),
                'starts_at'                => $this->parseDate($data['starts_at']),
                'ends_at'                  => $this->parseDate($data['ends_at']),
                'venue_name'               => $venueName,
                'venue_city'               => $venueCity,
                'venue_address'            => $location,
                'image'                    => $this->n($data['image_url']),
                'status'                   => $data['post_status'] === 'publish' ? 'published' : 'draft',
                'is_public'                => true,
                'submitted_at'             => $createdAt,
                'approved_at'              => $createdAt,
            ];

            if ($dryRun) {
                $this->line("[DRY RUN] Would create event: {$data['name']}");
                $this->map[$wpEventId] = 0;
                $created++;
                continue;
            }

            try {
                $event                    = MarketplaceEvent::create($eventData);
                $this->map[$wpEventId]   = $event->id;
                $created++;

                if ($created % 100 === 0) {
                    $this->saveMap();
                    $this->line("Progress: {$created} created, {$skipped} skipped...");
                }
            } catch (\Exception $e) {
                $this->error("Failed event '{$data['name']}': " . $e->getMessage());
                $failed++;
            }
        }

        fclose($handle);
        $this->saveMap();

        $this->info("Done! Created: {$created} | Skipped: {$skipped} | Failed: {$failed}");
        $this->info("Map saved to: {$this->mapFile}");

        return 0;
    }

    private function saveMap(): void
    {
        file_put_contents($this->mapFile, json_encode($this->map, JSON_PRETTY_PRINT));
    }

    private function n(?string $v): ?string
    {
        return ($v !== null && $v !== '' && $v !== 'NULL') ? $v : null;
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v || $v === 'NULL' || $v === '0000-00-00 00:00:00' || $v === '0000-00-00') {
            return null;
        }
        return $v;
    }
}
