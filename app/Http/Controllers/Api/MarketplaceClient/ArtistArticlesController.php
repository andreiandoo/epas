<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Artist;
use App\Models\MarketplacePartner;
use App\Models\PartnerArticle;
use App\Support\MarketplaceTz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Media-partner articles about an artist, for the "În presă" section of the
 * artist page. Empty unless the media-partners microservice is active.
 */
class ArtistArticlesController extends BaseController
{
    /**
     * GET /marketplace-client/artists/{slug}/articles?page=&per_page=
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $perPage = max(1, min(24, (int) $request->input('per_page', 6)));

        $artistId = $client->hasMicroservice(MarketplacePartner::MICROSERVICE)
            ? Artist::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->whereHas('marketplaceClients', fn ($q) => $q
                    ->where('marketplace_artist_partners.marketplace_client_id', $client->id)
                    ->where('marketplace_artist_partners.is_partner', true))
                ->value('id')
            : null;

        if (!$artistId) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ]);
        }

        $articles = PartnerArticle::query()
            ->visible()
            ->where('partner_articles.marketplace_client_id', $client->id)
            ->whereHas('artists', fn ($q) => $q->where('artists.id', $artistId))
            ->with('partner:id,name')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $timezone = MarketplaceTz::tz($client);

        return $this->paginated($articles, fn (PartnerArticle $article) => [
            'id' => $article->id,
            'type' => $article->type,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'url' => $article->url,
            'image_url' => $article->image_url,
            'author' => $article->author,
            'published_at' => $article->published_at->copy()->setTimezone($timezone)->toIso8601String(),
            'source' => $article->partner?->name,
        ]);
    }
}
