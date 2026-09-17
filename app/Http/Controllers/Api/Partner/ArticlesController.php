<?php

namespace App\Http\Controllers\Api\Partner;

use App\Services\Partners\PartnerApiException;
use App\Services\Partners\PartnerArticleWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Articles a media partner publishes about artists, shown on the marketplace artist page.
 */
class ArticlesController extends PartnerController
{
    private const SOURCE_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(private readonly PartnerArticleWriter $writer)
    {
    }

    /**
     * PUT /external-articles/{source}/{sourceId}
     */
    public function upsert(Request $request, string $source, string $sourceId): JsonResponse
    {
        $partner = $this->partner($request);

        if ($source !== $partner->slug) {
            return $this->fail('source_mismatch', "This key writes articles for source {$partner->slug}.", 403);
        }

        try {
            $result = $this->writer->upsert($partner, $sourceId, $request->all());
        } catch (PartnerApiException $e) {
            return $this->fail($e->errorCode, $e->getMessage(), $e->status, $e->errors);
        }

        return response()->json(
            ['data' => $this->resource($sourceId, $result)],
            $result['status'] === 'created' ? 201 : 200
        );
    }

    /**
     * DELETE /external-articles/{source}/{sourceId}
     */
    public function destroy(Request $request, string $source, string $sourceId): JsonResponse
    {
        $partner = $this->partner($request);

        if ($source !== $partner->slug) {
            return $this->fail('source_mismatch', "This key writes articles for source {$partner->slug}.", 403);
        }

        if (!$this->writer->delete($partner, $sourceId)) {
            return $this->fail('article_not_found', 'Article not found.', 404);
        }

        return response()->json(['data' => ['source_id' => $sourceId, 'deleted' => true]]);
    }

    /**
     * POST /external-articles/{source}/batch  { "articles": [ … ] }
     *
     * Each article is stored or rejected on its own; results keep the request order.
     */
    public function batch(Request $request, string $source): JsonResponse
    {
        $partner = $this->partner($request);

        if ($source !== $partner->slug) {
            return $this->fail('source_mismatch', "This key writes articles for source {$partner->slug}.", 403);
        }

        $articles = $request->input('articles');
        if (!is_array($articles) || !array_is_list($articles) || $articles === []) {
            return $this->fail('invalid_parameter', 'articles must be a non-empty list.', 422);
        }

        if (count($articles) > PartnerArticleWriter::MAX_BATCH) {
            return $this->fail('invalid_parameter', 'At most ' . PartnerArticleWriter::MAX_BATCH . ' articles per batch.', 422);
        }

        $results = [];
        foreach ($articles as $article) {
            $sourceId = is_array($article) && is_scalar($article['source_id'] ?? null) ? (string) $article['source_id'] : '';

            if (!preg_match(self::SOURCE_ID_PATTERN, $sourceId)) {
                $results[] = [
                    'source_id' => $sourceId ?: null,
                    'status' => 'error',
                    'error' => ['code' => 'invalid_source_id', 'message' => 'source_id must be 1-64 letters, digits, "-" or "_".'],
                ];
                continue;
            }

            try {
                $results[] = $this->resource($sourceId, $this->writer->upsert($partner, $sourceId, $article));
            } catch (PartnerApiException $e) {
                $results[] = [
                    'source_id' => $sourceId,
                    'status' => 'error',
                    'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                ];
            }
        }

        return response()->json(['data' => $results]);
    }

    private function resource(string $sourceId, array $result): array
    {
        return [
            'source_id' => $sourceId,
            'status' => $result['status'],
            'id' => $result['article']->id,
            'artist_ids' => $result['article']->artists()->pluck('artists.id')->map(fn ($id) => (int) $id)->all(),
            'unknown_artist_ids' => $result['unknown_artist_ids'],
            'is_hidden' => (bool) $result['article']->is_hidden,
        ];
    }
}
