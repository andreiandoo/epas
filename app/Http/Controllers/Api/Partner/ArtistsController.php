<?php

namespace App\Http\Controllers\Api\Partner;

use App\Models\Artist;
use App\Models\MarketplaceClient;
use App\Services\Partners\PartnerEventPresenter;
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
     * GET /artists?search=&page=&per_page=
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

        $artists = $query->orderBy('name')->orderBy('id')->paginate($this->perPage($request, 20, 50));
        $pageIds = $this->pageArtistIds($client, $artists->getCollection()->pluck('id')->all());

        return $this->page($artists, $artists->getCollection()
            ->map(fn (Artist $artist) => $this->resource($artist, $client, $pageIds))
            ->all());
    }

    /**
     * GET /artists/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $client = $this->client($request);
        $artist = $this->scoped($client)->with('artistGenres')->find($id);

        if (!$artist) {
            return $this->fail('artist_not_found', 'Artist not found.', 404);
        }

        return response()->json([
            'data' => $this->resource($artist, $client, $this->pageArtistIds($client, [$artist->id])),
        ]);
    }

    private function scoped(MarketplaceClient $client): Builder
    {
        return Artist::query()
            ->where('artists.is_active', true)
            ->where(fn ($q) => $q
                ->whereExists(fn ($sub) => $sub->selectRaw('1')
                    ->from('marketplace_artist_partners')
                    ->whereColumn('marketplace_artist_partners.artist_id', 'artists.id')
                    ->where('marketplace_artist_partners.marketplace_client_id', $client->id))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                    ->from('event_artist')
                    ->join('events', 'events.id', '=', 'event_artist.event_id')
                    ->whereColumn('event_artist.artist_id', 'artists.id')
                    ->where('events.marketplace_client_id', $client->id)
                    ->where('events.is_published', true)));
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

    private function resource(Artist $artist, MarketplaceClient $client, array $pageIds): array
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
            'updated_at' => $artist->updated_at?->copy()->setTimezone(PartnerEventPresenter::timezoneFor($client))->toIso8601String(),
        ];
    }
}
