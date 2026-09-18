<?php

namespace App\Http\Controllers\Api\Partner;

use App\Models\ArtistGenre;
use App\Models\EventGenre;
use App\Models\MarketplaceClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Partners\PartnerFeedQueries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The genre vocabulary a partner can filter by, with how many upcoming events
 * each event genre currently has on this marketplace.
 */
class GenresController extends PartnerController
{
    /**
     * GET /genres
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $language = $client->language ?? 'ro';
        $counts = $this->upcomingEventCounts($client);

        // event_genres and artist_genres are one shared vocabulary; only the counts are per marketplace.
        $eventGenres = EventGenre::query()
            ->with('parent:id,slug')
            ->orderBy('slug')
            ->get()
            ->map(fn (EventGenre $genre) => [
                'slug' => $genre->slug,
                'name' => $genre->getTranslation('name', $language),
                'parent_slug' => $genre->parent?->slug,
                'upcoming_events' => $counts[$genre->id] ?? 0,
            ])
            ->values()
            ->all();

        $artistGenres = ArtistGenre::query()
            ->with('parent:id,slug')
            ->orderBy('slug')
            ->get()
            ->map(fn (ArtistGenre $genre) => [
                'slug' => $genre->slug,
                'name' => $genre->getTranslation('name', $language),
                'parent_slug' => $genre->parent?->slug,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                // Filter /events with ?genre=<slug>
                'event_genres' => $eventGenres,
                // Filter /artists with ?genre=<slug>
                'artist_genres' => $artistGenres,
            ],
        ]);
    }

    /**
     * @return array<int, int> event genre id => upcoming events
     */
    private function upcomingEventCounts(MarketplaceClient $client): array
    {
        // Aggregates every upcoming event; the vocabulary changes slowly.
        return Cache::remember(
            "partner_genre_counts_{$client->id}",
            now()->addMinutes(5),
            fn () => PartnerFeedQueries::joinPivot(
                PartnerFeedQueries::upcoming(DB::query(), $client),
                'event_event_genre',
                'eg'
            )
                ->groupBy('eg.event_genre_id')
                ->selectRaw('eg.event_genre_id as genre_id, count(distinct f.event_id) as total')
                ->pluck('total', 'genre_id')
                ->map(fn ($total) => (int) $total)
                ->all()
        );
    }
}
