<?php

namespace App\Jobs\Tracking;

use App\Models\Event;
use App\Models\FeatureStore\FsPersonAffinityArtist;
use App\Models\FeatureStore\FsPersonAffinityGenre;
use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\TxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Calculates person affinities for artists and genres based on their interactions.
 *
 * Uses recency-weighted scoring: score = base_points * exp(-days_since/τ)
 * where τ (tau) is the decay constant (default 60 days).
 */
class CalculatePersonAffinitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    protected ?int $tenantId;
    protected ?int $personId;
    protected int $lookbackDays;

    /**
     * Create a new job instance.
     *
     * @param int|null $tenantId Process only this tenant (null = all tenants)
     * @param int|null $personId Process only this person (null = all persons with recent activity)
     * @param int $lookbackDays How far back to look for events (default 365)
     */
    public function __construct(?int $tenantId = null, ?int $personId = null, int $lookbackDays = 365)
    {
        $this->tenantId = $tenantId;
        $this->personId = $personId;
        $this->lookbackDays = $lookbackDays;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('CalculatePersonAffinities started', [
            'tenant_id' => $this->tenantId,
            'person_id' => $this->personId,
            'lookback_days' => $this->lookbackDays,
        ]);

        $startTime = microtime(true);
        $processed = 0;
        $errors = 0;

        try {
            // Get persons to process
            $persons = $this->getPersonsToProcess();

            foreach ($persons as $person) {
                try {
                    $this->calculateAffinitiesForPerson($person->tenant_id, $person->person_id);
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to calculate affinities for person', [
                        'tenant_id' => $person->tenant_id,
                        'person_id' => $person->person_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('CalculatePersonAffinities completed', [
                'processed' => $processed,
                'errors' => $errors,
                'duration_seconds' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('CalculatePersonAffinities job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Get persons who have recent activity and need affinity recalculation.
     */
    protected function getPersonsToProcess()
    {
        $query = TxEvent::query()
            ->select('tenant_id', 'person_id')
            ->whereNotNull('person_id')
            ->where('occurred_at', '>=', now()->subDays($this->lookbackDays))
            ->groupBy('tenant_id', 'person_id');

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        if ($this->personId) {
            $query->where('person_id', $this->personId);
        }

        return $query->get();
    }

    /**
     * Calculate all affinities for a specific person.
     */
    protected function calculateAffinitiesForPerson(int $tenantId, int $personId): void
    {
        // Get all relevant events for this person
        $events = TxEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->where('occurred_at', '>=', now()->subDays($this->lookbackDays))
            ->whereIn('event_name', array_keys(FsPersonAffinityArtist::EVENT_WEIGHTS))
            ->select('event_name', 'entities', 'occurred_at')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        // Collect event_entity_ids to lookup artists/genres
        $eventEntityIds = $events
            ->map(fn($e) => $e->entities['event_entity_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($eventEntityIds)) {
            return;
        }

        // Get event → artists mapping
        $eventArtists = DB::table('event_artist')
            ->join('events', 'events.id', '=', 'event_artist.event_id')
            ->whereIn('events.id', $eventEntityIds)
            ->select('events.id as event_id', 'event_artist.artist_id')
            ->get()
            ->groupBy('event_id');

        // Get event → genres mapping
        $eventGenres = DB::table('event_event_genre')
            ->join('events', 'events.id', '=', 'event_event_genre.event_id')
            ->join('event_genres', 'event_genres.id', '=', 'event_event_genre.event_genre_id')
            ->whereIn('events.id', $eventEntityIds)
            ->select('events.id as event_id', 'event_genres.name as genre_name')
            ->get()
            ->groupBy('event_id');

        // Calculate artist affinities
        $artistScores = [];
        $artistCounts = [];

        foreach ($events as $event) {
            $eventEntityId = $event->entities['event_entity_id'] ?? null;
            if (!$eventEntityId) continue;

            $artists = $eventArtists[$eventEntityId] ?? collect();
            $daysSince = max(0, now()->diffInDays($event->occurred_at));
            $weight = FsPersonAffinityArtist::calculateDecayedWeight($event->event_name, $daysSince);

            foreach ($artists as $artist) {
                $artistId = $artist->artist_id;

                if (!isset($artistScores[$artistId])) {
                    $artistScores[$artistId] = 0;
                    $artistCounts[$artistId] = [
                        'views' => 0,
                        'purchases' => 0,
                        'attendance' => 0,
                        'last_interaction' => null,
                    ];
                }

                $artistScores[$artistId] += $weight;

                // Track event type counts
                match ($event->event_name) {
                    'event_view' => $artistCounts[$artistId]['views']++,
                    'order_completed' => $artistCounts[$artistId]['purchases']++,
                    'entry_granted' => $artistCounts[$artistId]['attendance']++,
                    default => null,
                };

                // Track last interaction
                if (!$artistCounts[$artistId]['last_interaction'] ||
                    $event->occurred_at > $artistCounts[$artistId]['last_interaction']) {
                    $artistCounts[$artistId]['last_interaction'] = $event->occurred_at;
                }
            }
        }

        // Upsert artist affinities
        foreach ($artistScores as $artistId => $score) {
            FsPersonAffinityArtist::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'person_id' => $personId,
                    'artist_id' => $artistId,
                ],
                [
                    'affinity_score' => round($score, 4),
                    'views_count' => $artistCounts[$artistId]['views'],
                    'purchases_count' => $artistCounts[$artistId]['purchases'],
                    'attendance_count' => $artistCounts[$artistId]['attendance'],
                    'last_interaction_at' => $artistCounts[$artistId]['last_interaction'],
                ]
            );
        }

        // Calculate genre affinities
        $genreScores = [];
        $genreCounts = [];

        foreach ($events as $event) {
            $eventEntityId = $event->entities['event_entity_id'] ?? null;
            if (!$eventEntityId) continue;

            $genres = $eventGenres[$eventEntityId] ?? collect();
            $daysSince = max(0, now()->diffInDays($event->occurred_at));
            $weight = FsPersonAffinityArtist::calculateDecayedWeight($event->event_name, $daysSince);

            foreach ($genres as $genre) {
                $genreName = $genre->genre_name;

                if (!isset($genreScores[$genreName])) {
                    $genreScores[$genreName] = 0;
                    $genreCounts[$genreName] = [
                        'views' => 0,
                        'purchases' => 0,
                        'attendance' => 0,
                        'last_interaction' => null,
                    ];
                }

                $genreScores[$genreName] += $weight;

                match ($event->event_name) {
                    'event_view' => $genreCounts[$genreName]['views']++,
                    'order_completed' => $genreCounts[$genreName]['purchases']++,
                    'entry_granted' => $genreCounts[$genreName]['attendance']++,
                    default => null,
                };

                if (!$genreCounts[$genreName]['last_interaction'] ||
                    $event->occurred_at > $genreCounts[$genreName]['last_interaction']) {
                    $genreCounts[$genreName]['last_interaction'] = $event->occurred_at;
                }
            }
        }

        // Upsert genre affinities
        foreach ($genreScores as $genre => $score) {
            FsPersonAffinityGenre::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'person_id' => $personId,
                    'genre' => $genre,
                ],
                [
                    'affinity_score' => round($score, 4),
                    'views_count' => $genreCounts[$genre]['views'],
                    'purchases_count' => $genreCounts[$genre]['purchases'],
                    'attendance_count' => $genreCounts[$genre]['attendance'],
                    'last_interaction_at' => $genreCounts[$genre]['last_interaction'],
                ]
            );
        }
    }

    /**
     * Dispatch job for a specific person (e.g., after order completion).
     */
    public static function dispatchForPerson(int $tenantId, int $personId): void
    {
        static::dispatch($tenantId, $personId, 365);
    }

    /**
     * Dispatch job for a specific tenant (e.g., nightly batch).
     */
    public static function dispatchForTenant(int $tenantId): void
    {
        static::dispatch($tenantId, null, 365);
    }

    /**
     * Dispatch job for all tenants (e.g., weekly recalculation).
     */
    public static function dispatchForAll(): void
    {
        static::dispatch(null, null, 365);
    }
}
