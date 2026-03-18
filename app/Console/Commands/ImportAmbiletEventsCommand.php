<?php

namespace App\Console\Commands;

use App\Models\MarketplaceOrganizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAmbiletEventsCommand extends Command
{
    protected $signature = 'import:ambilet-events
        {file : Path to events.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run : Simulate without writing to DB}
        {--fresh : Ignore existing map and re-process all rows}
        {--fallback-organizer= : Organizer ID to use when email is missing/null}';

    protected $description = 'Import AmBilet events from CSV into Tixello events table';

    private string $mapFile;
    private array $map = []; // wp_event_id => tixello_event_id

    public function handle(): int
    {
        $file     = $this->argument('file');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $fresh    = $this->option('fresh');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $mapDir = storage_path('app/import_maps');
        if (!is_dir($mapDir)) {
            mkdir($mapDir, 0755, true);
        }
        $this->mapFile = $mapDir . '/events_map.json';

        if (!$fresh && file_exists($this->mapFile)) {
            $this->map = json_decode(file_get_contents($this->mapFile), true) ?? [];
            $this->info('Loaded existing events map: ' . count($this->map) . ' entries.');
        } elseif ($fresh && file_exists($this->mapFile)) {
            unlink($this->mapFile);
            $this->map = [];
            $this->info('Deleted existing events map (--fresh).');
        }

        $fallbackOrganizer = $this->option('fallback-organizer') ? (int) $this->option('fallback-organizer') : null;

        // Build organizer lookup: email => id
        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->pluck('id', 'email')
            ->all();

        // Email redirects: map old emails to existing organizer emails
        $emailRedirects = [
            'mihnea.grecu@xlab.ro'    => 'contact@grimus.ro',
            'mariusstoicea@gmail.com' => 'contact@grimus.ro',
        ];

        $this->info('Loaded ' . count($organizers) . ' organizers for marketplace ' . $clientId . '.');
        if ($fallbackOrganizer) {
            $this->info("Fallback organizer ID: {$fallbackOrganizer}");
        }

        $handle  = fopen($file, 'r');
        $header  = fgetcsv($handle);
        $created = $skipped = $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                $this->warn('Skipping malformed row (column count mismatch): ' . implode(',', array_slice($row, 0, 3)));
                $failed++;
                continue;
            }
            $data      = array_combine($header, $row);
            $wpEventId = $data['wp_event_id'];

            if (isset($this->map[$wpEventId])) {
                $skipped++;
                continue;
            }

            $organizerEmail = strtolower(trim($data['organizer_email'] ?? ''));

            // Apply email redirects
            if (isset($emailRedirects[$organizerEmail])) {
                $organizerEmail = $emailRedirects[$organizerEmail];
            }

            $organizerId = $organizers[$organizerEmail] ?? null;

            if (!$organizerId && $fallbackOrganizer) {
                $organizerId = $fallbackOrganizer;
            }

            if (!$organizerId) {
                $this->warn("No organizer for '{$organizerEmail}' (event: {$data['name']}) — skipping.");
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

            // Parse starts_at/ends_at datetime into event_date + start_time + end_time
            $startsAt  = $this->parseDate($data['starts_at']);
            $endsAt    = $this->parseDate($data['ends_at']);
            $eventDate = null;
            $startTime = null;
            $endTime   = null;
            $durationMode = 'single_day';
            $rangeStartDate = null;
            $rangeEndDate   = null;
            $rangeStartTime = null;
            $rangeEndTime   = null;

            if ($startsAt) {
                $startDt  = new \DateTime($startsAt);
                $eventDate = $startDt->format('Y-m-d');
                $startTime = $startDt->format('H:i');
            }
            if ($endsAt) {
                $endDt   = new \DateTime($endsAt);
                $endTime = $endDt->format('H:i');

                // If event spans multiple days, use range mode
                if ($eventDate && $endDt->format('Y-m-d') !== $eventDate) {
                    $durationMode   = 'range';
                    $rangeStartDate = $eventDate;
                    $rangeEndDate   = $endDt->format('Y-m-d');
                    $rangeStartTime = $startTime;
                    $rangeEndTime   = $endTime;
                    // Clear single-day fields for range events
                    $eventDate = null;
                    $startTime = null;
                    $endTime   = null;
                }
            }

            $createdAt   = $this->parseDate($data['created_at']) ?? now()->toDateTimeString();
            $isPublished = $data['post_status'] === 'publish';
            // Use original WordPress slug if available, otherwise generate from name
            $wpSlug = $this->n($data['wp_slug'] ?? null);
            $slug   = $wpSlug
                ? $this->ensureUniqueSlug($wpSlug)
                : $this->generateUniqueSlug($this->n($data['name']) ?? '');

            // events.title is JSON translatable: {"ro": "Event Name"}
            $name        = $data['name'];
            $description = $this->n($data['description']);
            $ticketTerms = $this->n($data['ticket_terms']);

            $eventData = [
                'tenant_id'                  => null,
                'marketplace_client_id'      => $clientId,
                'marketplace_organizer_id'   => $organizerId,
                'title'                      => json_encode(['ro' => $name]),
                'slug'                       => $slug,
                'description'                => $description ? json_encode(['ro' => $description]) : null,
                'ticket_terms'               => $ticketTerms ? json_encode(['ro' => $ticketTerms]) : null,
                'duration_mode'              => $durationMode,
                'event_date'                 => $eventDate,
                'start_time'                 => $startTime,
                'end_time'                   => $endTime,
                'range_start_date'           => $rangeStartDate,
                'range_end_date'             => $rangeEndDate,
                'range_start_time'           => $rangeStartTime,
                'range_end_time'             => $rangeEndTime,
                'venue_name'                 => $venueName,
                'city'                       => $venueCity,
                'address'                    => $location,
                'hero_image_url'             => $this->n($data['image_url']),
                'poster_url'                 => null,
                'status'                     => $isPublished ? 'published' : 'draft',
                'is_published'               => $isPublished ? 1 : 0,
                'is_featured'                => 0,
                'is_sold_out'                => 0,
                'is_cancelled'               => 0,
                'is_postponed'               => 0,
                'views_count'                => 0,
                'created_at'                 => $createdAt,
                'updated_at'                 => $createdAt,
            ];

            if ($dryRun) {
                $this->line("[DRY RUN] Would create event: {$name} ({$createdAt}) [slug: {$slug}]");
                $created++;
                continue;
            }

            try {
                $eventId = DB::table('events')->insertGetId($eventData);

                // Set event_series (normally done by Event model boot, but we use DB::table)
                DB::table('events')->where('id', $eventId)->update([
                    'event_series' => 'AMB-' . $eventId,
                ]);

                $this->map[$wpEventId] = $eventId;
                $created++;

                if ($created % 100 === 0) {
                    $this->saveMap();
                    $this->line("Progress: {$created} created, {$skipped} skipped...");
                }
            } catch (\Exception $e) {
                $this->error("Failed event '{$name}': " . $e->getMessage());
                $failed++;
            }
        }

        fclose($handle);

        if (!$dryRun) {
            $this->saveMap();
            $this->info("Map saved to: {$this->mapFile}");
        }

        $this->info("Done! Created: {$created} | Skipped: {$skipped} | Failed: {$failed}");

        return 0;
    }

    /**
     * Ensure an existing slug is unique, appending a suffix if needed.
     */
    private function ensureUniqueSlug(string $slug): string
    {
        $base = $slug;
        $i    = 1;

        while (DB::table('events')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Generate a globally unique slug for the events table.
     */
    private function generateUniqueSlug(string $name): string
    {
        return $this->ensureUniqueSlug(Str::slug($name) ?: 'event');
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
