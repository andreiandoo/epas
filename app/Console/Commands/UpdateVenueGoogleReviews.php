<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Services\GooglePlacesService;
use App\Jobs\FetchVenueGoogleReviews;
use Illuminate\Console\Command;

class UpdateVenueGoogleReviews extends Command
{
    protected $signature = 'venues:update-google-reviews
                            {--venue= : Update specific venue by ID}
                            {--force : Force update even if recently updated}
                            {--queue : Use queue jobs instead of synchronous processing}
                            {--skip-days=7 : Skip venues updated within N days}
                            {--dry-run : Show what would be processed without actually fetching}';

    protected $description = 'Fetch Google Places reviews (rating, count, top 5 reviews) for all venues';

    public function handle(): int
    {
        $service = new GooglePlacesService();

        if (!$service->isConfigured()) {
            $this->error('Google Places API key is not configured. Set GOOGLE_MAPS_API_KEY in .env');
            return 1;
        }

        $skipDays = $this->option('force') ? 0 : (int) $this->option('skip-days');
        $isDryRun = $this->option('dry-run');
        $useQueue = $this->option('queue');

        $query = Venue::query();

        if ($venueId = $this->option('venue')) {
            $query->where('id', $venueId);
        }

        // Skip venues that were recently updated
        if ($skipDays > 0) {
            $query->where(function ($q) use ($skipDays) {
                $q->whereNull('google_reviews_updated_at')
                  ->orWhere('google_reviews_updated_at', '<', now()->subDays($skipDays));
            });
        }

        $totalCount = (clone $query)->count();

        if ($totalCount === 0) {
            $this->info('No venues found matching criteria.');
            return 0;
        }

        $this->info("Found {$totalCount} venues to process.");

        if ($isDryRun) {
            $this->showDryRunStats($query);
            return 0;
        }

        if ($useQueue) {
            return $this->processWithQueue($query, $totalCount);
        }

        return $this->processSynchronously($query, $service, $totalCount);
    }

    protected function showDryRunStats($query): void
    {
        $this->info("\n--- DRY RUN MODE ---\n");

        $venues = $query->get();

        $withPlaceId = $venues->filter(fn ($v) => !empty($v->google_place_id))->count();
        $withoutPlaceId = $venues->filter(fn ($v) => empty($v->google_place_id))->count();
        $withReviews = $venues->filter(fn ($v) => !empty($v->google_reviews))->count();
        $withMapsUrl = $venues->filter(fn ($v) => !empty($v->google_maps_url))->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total venues to process', $venues->count()],
                ['With google_place_id (skip search)', $withPlaceId],
                ['Without google_place_id (need search)', $withoutPlaceId],
                ['With google_maps_url (can extract)', $withMapsUrl],
                ['Already have reviews', $withReviews],
            ]
        );

        $this->newLine();
        $this->info("API calls estimated: {$withPlaceId} details-only + up to " . ($withoutPlaceId * 2) . " (search + details) for venues without place_id");
    }

    protected function processWithQueue($query, int $totalCount): int
    {
        $this->info("Queueing {$totalCount} venues...");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $queued = 0;

        $query->chunkById(100, function ($venues) use (&$queued, $bar) {
            foreach ($venues as $venue) {
                FetchVenueGoogleReviews::dispatch($venue->id);
                $queued++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Queued {$queued} venues for processing.");
        $this->info("Run 'php artisan queue:work --queue=google-reviews' to process the jobs.");

        return 0;
    }

    protected function processSynchronously($query, GooglePlacesService $service, int $totalCount): int
    {
        $this->info("Processing venues synchronously...");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $updated = 0;
        $errors = 0;
        $skipped = 0;

        $query->chunkById(50, function ($venues) use (&$updated, &$errors, &$skipped, $bar, $service) {
            foreach ($venues as $venue) {
                $result = $this->fetchVenueReviews($venue, $service);

                if ($result === 'updated') {
                    $updated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $errors++;
                }

                $bar->advance();

                // Rate limiting - 300ms between requests
                usleep(300000);
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Updated {$updated} venues.");
        if ($skipped > 0) {
            $this->info("Skipped {$skipped} venues (no place_id found).");
        }
        if ($errors > 0) {
            $this->warn("{$errors} errors occurred. Check logs for details.");
        }

        return 0;
    }

    protected function fetchVenueReviews(Venue $venue, GooglePlacesService $service): string
    {
        $placeId = $venue->google_place_id;

        // If no place_id, try to find it
        if (empty($placeId)) {
            // Try extracting from Google Maps URL
            if (!empty($venue->google_maps_url)) {
                $placeId = GooglePlacesService::extractPlaceIdFromUrl($venue->google_maps_url);
            }

            // Search by name + city
            if (empty($placeId)) {
                $venueName = is_array($venue->name)
                    ? ($venue->name['ro'] ?? $venue->name['en'] ?? reset($venue->name))
                    : $venue->name;

                if (!empty($venueName)) {
                    try {
                        $placeId = $service->findPlaceId($venueName, $venue->city, $venue->country);
                    } catch (\Exception $e) {
                        $this->error("\nSearch error for {$venueName}: {$e->getMessage()}");
                        return 'error';
                    }
                }
            }

            if (empty($placeId)) {
                return 'skipped';
            }

            // Save place_id so we don't search again
            $venue->update(['google_place_id' => $placeId]);
        }

        // Fetch details
        try {
            $details = $service->getPlaceDetails($placeId);

            if (!$details) {
                return 'error';
            }

            $venue->update([
                'google_rating' => $details['rating'],
                'google_reviews_count' => $details['reviews_count'],
                'google_reviews' => $details['reviews'],
                'google_reviews_updated_at' => now(),
            ]);

            return 'updated';
        } catch (\Exception $e) {
            $venueName = is_array($venue->name) ? ($venue->name['en'] ?? '') : $venue->name;
            $this->error("\nFetch error for {$venueName}: {$e->getMessage()}");
            return 'error';
        }
    }
}
