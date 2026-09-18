<?php

namespace App\Http\Controllers\Api\Partner;

use App\Models\Artist;
use App\Models\MarketplaceClient;
use App\Services\Partners\PartnerArtistScope;
use App\Services\Partners\PartnerEventPresenter;
use App\Services\Partners\PartnerFeedQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Artists a media partner can link to: the marketplace's partner artists plus
 * any artist booked on one of its events.
 */
class ArtistsController extends PartnerController
{
    /**
     * GET /artists?search=&genre=&has_upcoming_events=1&page=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $query = $this->scoped($client)->with('artistGenres');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $slug = Str::slug($search);
            $query->where(fn ($q) => $q
                ->where('name', 'like', '%' . $search . '%')
                ->when($slug !== '', fn ($w) => $w->orWhere('slug', 'like', '%' . $slug . '%')));
        }

        if ($request->filled('genre')) {
            $slugs = array_filter(array_map('trim', explode(',', (string) $request->query('genre'))));
            $query->whereHas('artistGenres', fn ($q) => $q->whereIn('artist_genres.slug', $slugs));
        }

        if ($request->boolean('has_upcoming_events')) {
            $query->whereExists(fn ($sub) => $this->upcomingEventsQuery($sub, $client)
                ->whereColumn('ea.artist_id', 'artists.id'));
        }

        $artists = $query->orderBy('name')->orderBy('id')->paginate($this->perPage($request, 20, 50));
        $ids = $artists->getCollection()->pluck('id')->all();
        $pageIds = $this->pageArtistIds($client, $ids);
        $counts = $this->upcomingEventCounts($client, $ids);

        return $this->page($artists, $artists->getCollection()
            ->map(fn (Artist $artist) => $this->resource($artist, $client, $pageIds, $counts))
            ->all());
    }

    /**
     * GET /artists/{id} — the full profile, for a partner's own artist page.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $this->client($request);
        $artist = $this->scoped($client)->with('artistGenres', 'artistTypes')->find($id);

        if (!$artist) {
            return $this->fail('artist_not_found', 'Artist not found.', 404);
        }

        $language = $client->language ?? 'ro';
        $data = $this->resource(
            $artist,
            $client,
            $this->pageArtistIds($client, [$artist->id]),
            $this->upcomingEventCounts($client, [$artist->id])
        );

        return response()->json([
            'data' => $data + [
                // HTML, as entered in the admin (may be empty).
                'biography' => $artist->getTranslation('bio_html', $language),
                'biography_format' => 'html',
                'types' => $artist->artistTypes->map(fn ($type) => $type->getTranslation('name', $language))->values()->all(),
                'images' => [
                    'main' => $artist->main_image_full_url,
                    'portrait' => $artist->portrait_full_url,
                    'logo' => $artist->logo_full_url,
                ],
                'social' => [
                    'website' => $artist->website,
                    'facebook' => $artist->facebook_url,
                    'instagram' => $artist->instagram_url,
                    'youtube' => $artist->youtube_url,
                    'tiktok' => $artist->tiktok_url,
                    'spotify' => $artist->spotify_url,
                    'twitter' => $artist->twitter_url,
                ],
                'external_ids' => [
                    'spotify_id' => $artist->spotify_id,
                    'youtube_id' => $artist->youtube_id,
                ],
            ],
        ]);
    }

    private function scoped(MarketplaceClient $client): Builder
    {
        return PartnerArtistScope::query($client);
    }

    /**
     * Artists with a public page on the marketplace site.
     *
     * @return array<int, true>
     */
    private function pageArtistIds(MarketplaceClient $client, array $artistIds): array
    {
        if (!$artistIds) {
            return [];
        }

        return DB::table('marketplace_artist_partners')
            ->where('marketplace_client_id', $client->id)
            ->where('is_partner', true)
            ->whereIn('artist_id', $artistIds)
            ->pluck('artist_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Upcoming events of this marketplace joined to their artists (the parent
     * event's artists count too, as on /events?artist_id=).
     */
    private function upcomingEventsQuery($query, MarketplaceClient $client)
    {
        return PartnerFeedQueries::joinPivot(
            PartnerFeedQueries::upcoming($query->selectRaw('1'), $client),
            'event_artist',
            'ea'
        );
    }

    /**
     * @return array<int, int> artist id => upcoming events
     */
    private function upcomingEventCounts(MarketplaceClient $client, array $artistIds): array
    {
        if (!$artistIds) {
            return [];
        }

        return $this->upcomingEventsQuery(DB::query(), $client)
            ->whereIn('ea.artist_id', $artistIds)
            ->groupBy('ea.artist_id')
            ->selectRaw('ea.artist_id as artist_id, count(distinct f.event_id) as total')
            ->pluck('total', 'artist_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function resource(Artist $artist, MarketplaceClient $client, array $pageIds, array $counts = []): array
    {
        $siteUrl = PartnerEventPresenter::siteUrl($client);
        $language = $client->language ?? 'ro';

        return [
            'id' => $artist->id,
            'name' => $artist->name,
            'slug' => $artist->slug,
            'url' => ($siteUrl && isset($pageIds[$artist->id])) ? $siteUrl . '/artist/' . $artist->slug : null,
            'image' => $artist->portrait_full_url ?? $artist->main_image_full_url,
            'genres' => $artist->artistGenres->map(fn ($genre) => $genre->getTranslation('name', $language))->values()->all(),
            'city' => $artist->city,
            'country' => $artist->country,
            'upcoming_events_count' => $counts[$artist->id] ?? 0,
            'updated_at' => $artist->updated_at?->copy()->setTimezone(PartnerEventPresenter::timezoneFor($client))->toIso8601String(),
        ];
    }
}
